<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Compilers\SubqueryQueryCompiler;
use protich\AutoJoinEloquent\Support\SubQueryExpression;
use protich\AutoJoinEloquent\Support\CompiledExpression;
use Illuminate\Database\Query\Expression;
use RuntimeException;

/**
 * Class: SubqueryCompiler
 *
 * Compile correlated subqueries for model-defined descriptors that
 * require isolated inner query state.
 */
class SubqueryCompiler extends BaseCompiler
{
    /**
     * Outer query builder.
     *
     * @var AutoJoinQueryBuilder
     */
    protected AutoJoinQueryBuilder $outerBuilder;

    /**
     * Create a new subquery compiler.
     *
     * @param  AutoJoinQueryBuilder $outerBuilder
     * @return void
     */
    public function __construct(AutoJoinQueryBuilder $outerBuilder)
    {
        $this->outerBuilder = $outerBuilder;

        parent::__construct($this->makeInnerBuilder());
    }

    /**
     * Create a fresh inner builder for subquery compilation.
     *
     * The inner builder shares the same base model and connection context
     * as the outer builder, but it does not inherit mutable query state
     * such as joins, where clauses, selected columns, or alias tracking.
     *
     * A deterministic subquery prefix is applied to the inner alias manager
     * so aliases generated inside subqueries do not collide with aliases
     * from the outer query or sibling subqueries.
     *
     * @return AutoJoinQueryBuilder
     */
    protected function makeInnerBuilder(): AutoJoinQueryBuilder
    {
        $model  = $this->outerBuilder->getBaseModel();
        $prefix = $this->outerBuilder->nextSubqueryPrefix();
        $query  = $model->newQuery()->getQuery();

        $builder = $model->newAutoJoinBuilder($query);

        $builder->setModel($model);
        $builder->setBaseModel($model);
        $builder->setDefaultJoinType($this->outerBuilder->getDefaultJoinType());
        $builder->setUseSimpleAliases(
            $this->outerBuilder->getAliasManager()->getUseSimpleAliases()
        );
        $builder->getAliasManager()->setAliasPrefix($prefix);

        return $builder;
    }

    /**
     * Get the outer query base alias.
     *
     * @return string
     */
    protected function getOuterAlias(): string
    {
        return $this->outerBuilder->getBaseAlias();
    }

    /**
     * Get the outer query base model key name.
     *
     * @return string
     */
    protected function getOuterKeyName(): string
    {
        return $this->outerBuilder->getBaseModel()->getKeyName();
    }

    /**
     * Describe a normalized path chain for EXISTS compilation.
     *
     * @param  string $path
     * @return array{
     *     chain: array<int, array{relation: string, join: string}>,
     *     field: string|null,
     *     alias: string|null
     * }
     */
    protected function describePathChain(string $path): array
    {
        return $this->outerBuilder->describeColumnChain($path, null, false);
    }


    /**
     * Build a correlated inner query for a single path.
     *
     * The returned query selects the terminal value for the provided path
     * and correlates the inner query to the current outer row using the
     * base model key.
     *
     * @param  string $path
     * @return \Illuminate\Database\Query\Builder
     */
    protected function buildPathSelectSubquery(string $path)
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException(
                'Subquery path must not be empty.'
            );
        }

        $builder = $this->builder;
        $grammar = $builder->getGrammar();

        $innerAlias = $builder->getBaseAlias();
        $innerKey   = $builder->getBaseModel()->getKeyName();

        $builder->select($path);

        $builder->whereRaw(sprintf(
            '%s.%s = %s.%s',
            $grammar->wrap($innerAlias),
            $grammar->wrap($innerKey),
            $grammar->wrap($this->getOuterAlias()),
            $grammar->wrap($this->getOuterKeyName())
        ));

        $query = $builder->getQuery();

        $builder->autoJoinQuery(
            $query,
            SubqueryQueryCompiler::class
        );

        return $query;
    }

    /**
     * Compile a correlated select subquery for a single path.
     *
     * The returned expression is wrapped and intended for use anywhere a
     * final subquery expression is required.
     *
     * @param  string $path
     * @return SubQueryExpression
     */
    public function compilePathSelectSubquery(string $path): SubQueryExpression
    {
        $query = $this->buildPathSelectSubquery($path);

        return new SubQueryExpression(sprintf(
            '(%s)',
            $query->toSql()
        ));
    }

    /**
     * Compile a correlated select subquery for a single path into raw SQL.
     *
     * The returned SQL is not wrapped in outer parentheses and is intended
     * for use in UNION composition.
     *
     * @param  string $path
     * @return string
     */
    public function compilePathSelectSubquerySql(string $path): string
    {
        return $this->buildPathSelectSubquery($path)->toSql();
    }

    /**
     * Compile a target-anchored EXISTS count subquery.
     *
     * All paths must terminate on the same target relation and field.
     * The target model becomes the driving table and each path is
     * compiled into an EXISTS predicate correlated to:
     *
     * - the outer record
     * - the current target row
     *
     * Example shape:
     *
     *   (
     *     select count(*)
     *     from target as T
     *     where exists(path1 for outer row -> T)
     *        or exists(path2 for outer row -> T)
     *   )
     *
     * @param  array<int,string> $paths
     * @return string
     */
    public function compileExistsCountSubquerySql(array $paths): string
    {
        if ($paths === []) {
            throw new RuntimeException(
                'EXISTS count subquery requires at least one path.'
            );
        }

        $grammar = $this->builder->getGrammar();
        $target  = $this->resolveExistsCountTarget($paths);
        $model   = $this->resolveExistsCountTargetModel($target['chain']);
        $alias   = $this->outerBuilder->nextSubqueryPrefix() . 'T';

        $predicates = array_map(
            fn (string $path) => $this->compilePathExistsPredicateSql(
                $path,
                $alias,
                $target['field']
            ),
            $paths
        );

        return sprintf(
            '(select count(*) from %s as %s where %s)',
            $grammar->wrapTable($model->getTable()),
            $grammar->wrap($alias),
            implode("\nor\n", $predicates)
        );
    }

    /**
     * Compile an EXISTS predicate for a single path.
     *
     * The path is resolved from the outer builder base model and
     * correlated against:
     *
     * - the current outer record
     * - the current target row being counted
     *
     * Example shape:
     *
     *   exists (
     *     select 1
     *     from ...
     *     where inner_base.id = outer_base.id
     *       and terminal_value = target_alias.target_field
     *   )
     *
     * @param  string $path
     * @param  string $targetAlias
     * @param  string $targetField
     * @return string
     */
    protected function compilePathExistsPredicateSql(
        string $path,
        string $targetAlias,
        string $targetField
    ): string {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException(
                'EXISTS predicate path must not be empty.'
            );
        }

        $builder = $this->makeInnerBuilder();
        $grammar = $builder->getGrammar();

        $column = $this->normalizeColumn($path);
        $resolved = $builder->resolveColumnExpression($column, null, false);
        $terminal = $resolved instanceof Expression
            ? $resolved->getValue($grammar) // @phpstan-ignore-line
            : (string) $resolved;

        $innerAlias = $builder->getBaseAlias();
        $innerKey   = $builder->getBaseModel()->getKeyName();

        $builder->select(new CompiledExpression('1'));

        $builder->whereRaw(sprintf(
            '%s.%s = %s.%s',
            $grammar->wrap($innerAlias),
            $grammar->wrap($innerKey),
            $grammar->wrap($this->getOuterAlias()),
            $grammar->wrap($this->getOuterKeyName())
        ));

        $builder->whereRaw(sprintf(
            '%s = %s.%s',
            $terminal,
            $grammar->wrap($targetAlias),
            $grammar->wrap($targetField)
        ));

        $query = $builder->getQuery();

        $builder->autoJoinQuery(
            $query,
            SubqueryQueryCompiler::class
        );

        return sprintf(
            'exists (%s)',
            $query->toSql()
        );
    }

    /**
     * Resolve the target model for an EXISTS count chain.
     *
     * The target model is derived by traversing the normalized chain
     * from the outer builder base model and returning the related model
     * of the final relation.
     *
     * @param  array<int, array{relation: string, join: string}> $chain
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function resolveExistsCountTargetModel(array $chain)
    {
        $model = $this->outerBuilder->getBaseModel();

        foreach ($chain as $step) {
            $relation = $step['relation'];
            $rel      = $model->{$relation}();

            $model = $rel->getRelated();
        }

        return $model;
    }

    /**
     * Resolve the shared terminal target for an EXISTS count.
     *
     * All paths must terminate on the same final relation and field.
     *
     * @param  array<int,string> $paths
     * @return array{
     *     relation: string,
     *     field: string,
     *     chain: array<int, array{relation: string, join: string}>
     * }
     */
    protected function resolveExistsCountTarget(array $paths): array
    {
        $targetRelation = null;
        $targetField    = null;
        $targetChain    = null;

        foreach ($paths as $path) {
            $parts = $this->describePathChain($this->normalizeColumn($path));
            $chain = $parts['chain'];
            $field = $parts['field'];

            if ($chain === [] || ! is_string($field) || $field === '') {
                throw new RuntimeException(sprintf(
                    'Invalid EXISTS count path [%s].',
                    $path
                ));
            }

            $final = end($chain);
            $relation = $final['relation'];

            if ($targetRelation === null) {
                $targetRelation = $relation;
                $targetField    = $field;
                $targetChain    = $chain; // keep first for model resolution
                continue;
            }

            if ($targetRelation !== $relation || $targetField !== $field) {
                throw new RuntimeException(sprintf(
                    'EXISTS count paths must share the same terminal relation and field. [%s] given.',
                    $path
                ));
            }
        }

        return [
            'relation' => $targetRelation,
            'field'    => $targetField,
            'chain'    => $targetChain,
        ];
    }

    /**
     * Generate a deterministic alias for a subquery.
     *
     * The alias is built using the subquery type and a short hash derived
     * from the provided parts.
     *
     * Example:
     * - SubqueryCompiler::makeSubqueryAlias('count', ['departments.id', 'groups.departments.id'])
     *   => subquery_count_a1b2c3d4
     *
     * @param  string            $type
     * @param  array<int,string> $parts
     * @return string
     */
    public static function makeSubqueryAlias(string $type, array $parts): string
    {
        $normalized = implode('|', array_map(
            fn (string $part) => trim($part),
            $parts
        ));

        $hash = substr(md5($type . '|' . $normalized), 0, 8);

        return sprintf(
            'subquery_%s_%s',
            strtolower($type),
            $hash
        );
    }
}

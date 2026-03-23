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

        /** @var AutoJoinQueryBuilder $builder */
        $builder = $model->newQuery();

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

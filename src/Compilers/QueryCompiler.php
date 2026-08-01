<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Query\Builder;
use protich\AutoJoinEloquent\AutoJoinQueryBuilder;

/**
 * QueryCompiler
 *
 * Transforms query clauses (SELECT, WHERE, HAVING, GROUP BY, ORDER BY) using their
 * respective compiler classes. Each compiler implements BaseCompiler::compileClause().
 * This class normalizes clause input and applies auto-join and expression resolution.
 *
 * @phpstan-consistent-constructor
 */
class QueryCompiler
{
    /**
     * The auto-join builder instance.
     *
     * @var AutoJoinQueryBuilder
     */
    protected AutoJoinQueryBuilder $builder;

    /**
     * Constructor.
     *
     * @param AutoJoinQueryBuilder $builder
     */
    public function __construct(AutoJoinQueryBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Compile the full query using clause-specific compilers.
     *
     * @param  Builder $query
     * @return Builder
     */
    protected function compileQuery(Builder $query): Builder
    {
        $clauseMap = [
            'columns' => SelectCompiler::class,
            'wheres'  => WhereCompiler::class,
            'havings' => HavingCompiler::class,
            'groups'  => GroupByCompiler::class,
            'orders'  => OrderByCompiler::class,
        ];

        foreach ($clauseMap as $clauseKey => $compilerClass) {
            $clauses = $query->{$clauseKey} ?? null;

            if (empty($clauses)) {
                continue;
            }

            $compiler = new $compilerClass($this->builder);
            $compiled = $compiler->compileClause($clauses);

            $normalized = $this->normalizeCompiledClause(
                $clauseKey,
                $compiled
            );

            if ($clauseKey === 'columns') {
                $query->columns = $this->normalizeColumns($normalized);
                continue;
            }

            $query->{$clauseKey} = $normalized;
        }

        return $query;
    }

    /**
     * Normalize compiled clause entries.
     *
     * @param  string                   $clauseKey
     * @param  array<int|string,mixed>  $clauses
     * @return array<int|string,mixed>
     */
    protected function normalizeCompiledClause(string $clauseKey, array $clauses): array
    {
        return $clauses;
    }

    /**
     * Validate compiled SELECT entries for Laravel's query builder.
     *
     * @param  array<int|string,mixed> $columns
     * @return array<ExpressionContract|string>
     */
    protected function normalizeColumns(array $columns): array
    {
        $normalized = [];

        foreach ($columns as $key => $column) {
            if (
                ! is_string($column)
                && ! $column instanceof ExpressionContract
            ) {
                throw new \LogicException(sprintf(
                    'Compiled SELECT clauses must be strings or query expressions; [%s] given.',
                    get_debug_type($column)
                ));
            }

            $normalized[$key] = $column;
        }

        return $normalized;
    }

    /**
     * Static entry point for compiling a query builder with auto-join logic.
     *
     * @param AutoJoinQueryBuilder $builder The auto-join-enabled builder.
     * @param Builder $query The underlying query builder instance.
     * @return Builder The compiled query with resolved expressions.
     */
    public static function compile(AutoJoinQueryBuilder $builder, Builder $query): Builder
    {
        return (new static($builder))->compileQuery($query);
    }
}

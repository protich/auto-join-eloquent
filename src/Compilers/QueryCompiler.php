<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Builder;
use protich\AutoJoinEloquent\AutoJoinQueryBuilder;

/**
 * QueryCompiler
 *
 * Transforms query clauses (SELECT, WHERE, HAVING, GROUP BY, ORDER BY) using their
 * respective compiler classes. Each compiler implements BaseCompiler::compileClause().
 * This class normalizes clause input and applies auto-join and expression resolution.
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
     * @param Builder $query
     * @return Builder
     */
    protected function compileQuery(Builder $query): Builder
    {
        // Mapping of clause keys to compiler class + whether the input is scalar
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
            $query->{$clauseKey} =  $compiler->compileClause($clauses);
        }

        return $query;
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
        return (new self($builder))->compileQuery($query);
    }
}

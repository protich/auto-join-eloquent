<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Compilers\SelectCompiler;
use protich\AutoJoinEloquent\Compilers\WhereCompiler;
use protich\AutoJoinEloquent\Compilers\HavingCompiler;
use protich\AutoJoinEloquent\Compilers\GroupByCompiler;
use protich\AutoJoinEloquent\Compilers\OrderByCompiler;
use Illuminate\Database\Query\Builder;

class QueryCompiler extends AbstractCompiler
{
    /**
     * Compile the entire query builder.
     *
     * This instance method compiles SELECT columns, WHERE conditions,
     * HAVING conditions, and ORDER BY clauses using auto-join logic.
     * It assumes that the FROM clause has already been rewritten by the caller.
     *
     * @param Builder $query The query builder instance.
     * @return Builder
     */
    protected function compileQuery(Builder $query): Builder
    {
        if (!empty($query->columns)) {
            $query->columns = $this->compileSelectColumns($query->columns);
        }

        if (!empty($query->wheres)) {
            $query->wheres = $this->compileWhereColumns($query->wheres);
        }
        if (!empty($query->havings)) {
            $query->havings = $this->compileHavingColumns($query->havings);
        }

        if (!empty($query->groups)) {
            $query->groups = $this->compileGroupByColumns($query->groups);
        }

        if (!empty($query->orders)) {
            $query->orders = $this->compileOrderByColumns($query->orders);
        }

        return $query;
    }

    /**
     * Compile SELECT clause columns using auto-join logic.
     *
     * @param array $columns
     * @return array
     */
    private function compileSelectColumns(array $columns): array
    {
        $compiled = [];
        $compiler = new SelectCompiler($this->builder);
        foreach ($columns as $column) {
            $compiled[] = $compiler->compileColumn($column);
        }
        return $compiled;
    }

    /**
     * Compile WHERE clause conditions using auto-join logic.
     *
     * @param array $wheres
     * @return array
     */
    private function compileWhereColumns(array $wheres): array
    {
        $compiler = new WhereCompiler($this->builder);
        return collect($wheres)->map(function ($where) use ($compiler) {
            if (isset($where['column'])) {
                $where['column'] = $compiler->compileColumn($where['column']);
            }
            return $where;
        })->all();
    }

    /**
     * Compile HAVING clause conditions using auto-join logic.
     *
     * @param array $havings
     * @return array
     */
    private function compileHavingColumns(array $havings): array
    {
        $compiler = new HavingCompiler($this->builder);
        return collect($havings)->map(function ($having) use ($compiler) {
            return $compiler->compileClause($having);
        })->all();
    }

    /**
     * Compile GROUP BY clause columns using auto-join logic.
     *
     * @param array $groups
     * @return array
     */
    private function compileGroupByColumns(array $groups): array
    {
        $compiler = new GroupByCompiler($this->builder);
        return collect($groups)->map(function ($group) use ($compiler) {
            return $compiler->compileColumn($group);
        })->all();
    }

    /**
     * Compile ORDER BY clause conditions using auto-join logic.
     *
     * @param array $orders
     * @return array
     */
    private function compileOrderByColumns(array $orders): array
    {
        $compiler = new OrderByCompiler($this->builder);
        return collect($orders)->map(function ($order) use ($compiler) {
            if (isset($order['column'])) {
                $order['column'] = $compiler->compileColumn($order['column']);
            }
            return $order;
        })->all();
    }

    /**
     * Static factory method to compile the query with auto-join transformations.
     *
     * Usage: QueryCompiler::compile($builder, $query);
     *
     * @param AutoJoinQueryBuilder $builder The builder instance containing auto-join configuration.
     * @param Builder $query The query builder instance to transform.
     * @return Builder The compiled query.
     */
    public static function compile(AutoJoinQueryBuilder $builder, Builder $query): Builder
    {
        $compiler = new self($builder);
        return $compiler->compileQuery($query);
    }
}

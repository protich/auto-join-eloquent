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
            $query->columns = $this->compileSelectColumns($query->columns); // @phpstan-ignore-line
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
     * @param array<string> $columns
     * @return array<mixed>
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
     * @param array<mixed> $wheres
     * @return array<mixed>
     */
    private function compileWhereColumns(array $wheres): array
    {
        $compiler = new WhereCompiler($this->builder);
        /** @param array<string, string> $where */
        return collect($wheres)->map(function (array $where) use ($compiler) {
            if (isset($where['column'])) {
                $where['column'] = $compiler->compileColumn($where['column']); // @phpstan-ignore-line
            }
            return $where;
        })->all();
    }

    /**
     * Compile HAVING clause conditions using auto-join logic.
     *
     * @param array<mixed> $havings
     * @return array<mixed>
     */
    private function compileHavingColumns(array $havings): array
    {
        $compiler = new HavingCompiler($this->builder);
        return collect($havings)->map(function ($having) use ($compiler) {
            return $compiler->compileClause($having); // @phpstan-ignore-line
        })->all();
    }

    /**
     * Compile GROUP BY clause columns using auto-join logic.
     *
     * @param array<mixed> $groups
     * @return array<mixed>
     */
    private function compileGroupByColumns(array $groups): array
    {
        $compiler = new GroupByCompiler($this->builder);
        return collect($groups)->map(function (string $group) use ($compiler) {
            return $compiler->compileColumn($group);
        })->all();
    }

    /**
     * Compile ORDER BY clause conditions using auto-join logic.
     *
     * @param array<mixed> $orders
     * @return array<mixed>
     */
    private function compileOrderByColumns(array $orders): array
    {
        $compiler = new OrderByCompiler($this->builder);
        return collect($orders)->map(function ($order) use ($compiler) {
            if (isset($order['column'])) { // @phpstan-ignore-line
                $order['column'] = $compiler->compileColumn($order['column']); // @phpstan-ignore-line
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

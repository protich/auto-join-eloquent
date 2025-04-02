<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use Illuminate\Database\Query\Expression;

class SelectCompiler extends AbstractCompiler
{

    /**
     * Compile a SELECT column expression.
     *
     * If the column represents an aggregate (e.g., "COUNT(agent__departments.id) as dept_count"),
     * this method compiles it using compileAggregateExpression() (with useDefaultAlias true).
     * Otherwise, it parses the column expression (normalizing any dot notation to "__")
     * and delegates to the builder's resolveColumnExpression() method.
     *
     * @param string $column The raw column expression.
     * @return \Illuminate\Database\Query\Expression The compiled column expression.
     */
    public function compileColumn(string $column): Expression
    {
        if ($aggregateInfo = $this->parseAggregateExpression($column)) {
            return $this->compileAggregateExpression($aggregateInfo, true);
        }

        $parts = $this->parseColumnParts($column);
        return $this->builder->resolveColumnExpression($parts['column'], $parts['alias']);
    }
}

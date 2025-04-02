<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;

class GroupByCompiler extends AbstractCompiler
{
    /**
     * Compile a GROUP BY clause column expression.
     *
     * This method transforms a raw GROUP BY column expression into a fully qualified SQL
     * expression. It first checks if the expression is an aggregate function and, if so,
     * compiles the aggregate using compileAggregateExpression() with the default alias flag
     * set to false. Otherwise, it parses the expression to normalize it and then delegates
     * to the builder's resolveColumnExpression() method.
     *
     * @param string $column The raw GROUP BY column expression.
     * @return Expression The compiled GROUP BY column expression.
     */
    public function compileColumn(string $column): Expression
    {
        if ($aggregateInfo = $this->parseAggregateExpression($column)) {
            return $this->compileAggregateExpression($aggregateInfo, false);
        }
        $parts = $this->parseColumnParts($column);
        return $this->builder->resolveColumnExpression($parts['column']);
    }
}

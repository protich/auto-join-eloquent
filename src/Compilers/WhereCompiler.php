<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;

class WhereCompiler extends AbstractCompiler
{

    /**
     * Compile a WHERE clause column expression.
     *
     * Checks if the column represents an aggregate function.
     * If an aggregate is detected, an exception is thrown since aggregates are not allowed in WHERE clauses.
     * Otherwise, the method normalizes the column expression and delegates to the builder's resolveColumnExpression()
     * method to produce the fully qualified expression.
     *
     * @param string $column The raw WHERE clause column expression.
     * @return \Illuminate\Database\Query\Expression The compiled column expression.
     * @throws \Exception If an aggregate expression is detected.
     */
    public function compileColumn(string $column): Expression
    {
        if ($aggregateInfo = $this->parseAggregateExpression($column)) {
            throw new \Exception("Aggregate expressions are not allowed in WHERE clauses.");
        }

        $parts = $this->parseColumnParts($column);
        return $this->builder->resolveColumnExpression($parts['column']);
    }
}

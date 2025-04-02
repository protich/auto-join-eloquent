<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;

class OrderByCompiler extends AbstractCompiler
{

    /**
     * Compile an ORDER BY clause column expression.
     *
     * This method transforms a raw ORDER BY column expression into a fully qualified SQL expression.
     * It first checks if the expression is an aggregate (e.g., COUNT(...)); if so, it compiles the aggregate
     * using compileAggregateExpression() with the useDefaultAlias flag set to false (thus not appending a default alias).
     * If the expression is not an aggregate, it parses the expression to normalize it and then delegates to the builder's
     * resolveColumnExpression() method to obtain the final fully qualified column expression.
     *
     * @param string $column The raw ORDER BY column expression.
     * @return \Illuminate\Database\Query\Expression The compiled ORDER BY column expression.
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

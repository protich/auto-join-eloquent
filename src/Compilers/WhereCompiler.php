<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;
use Exception;

/**
 * WhereCompiler
 *
 * Compiles WHERE clause column expressions.
 * Disallows aggregates (e.g. COUNT, SUM) and supports COALESCE with aliasing disabled.
 */
class WhereCompiler extends BaseCompiler
{
    /**
     * Compile a WHERE clause column expression.
     *
     * WHERE clauses cannot include aggregate functions.
     * This override throws if an aggregate is detected, but otherwise delegates
     * to the parent compiler with aliasing disabled (as WHERE does not accept aliases).
     *
     * @param string $column The raw column expression.
     * @param bool $allowAlias Required by signature, ignored here.
     * @return Expression The compiled expression for the WHERE clause.
     * @throws Exception If an aggregate expression is used in WHERE.
     */
    public function compileColumn(string $column, bool $allowAlias = false): Expression
    {
        if ($this->parseAggregateExpression($column)) {
            throw new Exception("Aggregate expressions are not allowed in WHERE clauses.");
        }

        // Aliasing not allowed — pass false explicitly
        return parent::compileColumn($column, false);
    }
}

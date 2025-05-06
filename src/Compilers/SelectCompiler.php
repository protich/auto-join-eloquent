<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;

/**
 * SelectCompiler
 *
 * Compiles SELECT clause column expressions with alias support.
 * Inherits all COALESCE and aggregate handling from BaseCompiler.
 */
class SelectCompiler extends BaseCompiler
{
    /**
     * Compile a SELECT column expression.
     *
     * This override forces aliasing to be allowed, which is appropriate for SELECT.
     * The second parameter is required for method signature compatibility.
     *
     * @param string $column
     * @param bool $allowAlias Required for compatibility; ignored here.
     * @return Expression
     */
    public function compileColumn(string $column, bool $allowAlias = false): Expression
    {
        return parent::compileColumn($column, true);
    }
}

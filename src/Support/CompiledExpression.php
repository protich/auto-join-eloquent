<?php

namespace protich\AutoJoinEloquent\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * Class: CompiledExpression
 *
 * Represent a compiler-generated SQL expression that is already final.
 *
 * This marker type distinguishes compiler output from user-provided raw
 * Expression instances, which may still need to pass through the
 * auto-join compiler pipeline.
 */
class CompiledExpression implements Expression
{
    /**
     * Compiler-generated SQL.
     */
    private string $compiledValue;

    /**
     * Create a new compiled expression.
     *
     * @param  string $value
     * @return void
     */
    public function __construct(string $value)
    {
        $this->compiledValue = $value;
    }

    /**
     * Get the compiler-generated SQL.
     *
     * @param  Grammar $grammar
     * @return string
     */
    public function getValue(Grammar $grammar): string
    {
        return $this->compiledValue;
    }
}

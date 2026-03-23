<?php

namespace protich\AutoJoinEloquent\Support;

use Illuminate\Database\Query\Expression;

/**
 * Class: CompiledExpression
 *
 * Represent a compiler-generated SQL expression that is already final.
 *
 * This marker type distinguishes compiler output from user-provided raw
 * Expression instances, which may still need to pass through the
 * auto-join compiler pipeline.
 */
class CompiledExpression extends Expression
{
    /**
     * Create a new compiled expression.
     *
     * @param  string $value
     * @return void
     */
    public function __construct(string $value)
    {
        parent::__construct($value);
    }
}

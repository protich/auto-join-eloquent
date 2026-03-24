<?php

namespace protich\AutoJoinEloquent\Support;

/**
 * Class: SubQueryExpression
 *
 * Represent a compiled SQL subquery expression.
 *
 * This marker type is used to distinguish already-compiled subqueries
 * from normal column expressions that should still pass through the
 * auto-join compiler pipeline.
 */
class SubQueryExpression extends CompiledExpression
{
    /**
     * Create a new subquery expression.
     *
     * @param  string $value
     * @return void
     */
    public function __construct(string $value)
    {
        parent::__construct($value);
    }
}

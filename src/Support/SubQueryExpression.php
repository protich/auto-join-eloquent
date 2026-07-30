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
     * Compiled SQL text.
     *
     * @var string
     */
    private string $sql;

    /**
     * Bindings referenced by placeholders inside the compiled subquery.
     *
     * @var list<mixed>
     */
    private array $bindings;

    /**
     * Create a new subquery expression.
     *
     * @param  string       $value
     * @param  list<mixed>  $bindings
     * @return void
     */
    public function __construct(string $value, array $bindings = [])
    {
        parent::__construct($value);

        $this->sql = $value;
        $this->bindings = $bindings;
    }

    /**
     * Get the compiled SQL text.
     *
     * @return string
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * Get bindings in the same order as their SQL placeholders.
     *
     * @return list<mixed>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }
}

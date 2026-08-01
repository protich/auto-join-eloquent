<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\Support\CompiledExpression;

/**
 * OrderByCompiler
 *
 * Compiles ORDER BY clause expressions.
 *
 * When an order expression matches a previously selected expression with
 * a registered alias, the alias is reused instead of recompiling the
 * expression. This avoids duplicate subqueries in ORDER BY.
 */
class OrderByCompiler extends BaseCompiler
{
    /**
     * {@inheritDoc}
     */
    protected const BINDING_TYPE = 'order';

    /**
     * Compile an ORDER BY column expression.
     *
     * @param  string $column
     * @param  bool   $allowAlias Required for compatibility; ignored here.
     * @return CompiledExpression
     */
    public function compileColumn(
        string $column,
        bool $allowAlias = false
    ): CompiledExpression
    {
        $alias = $this->resolveSelectionAlias($column);

        if ($alias !== null) {
            return $this->makeCompiledExpression(
                $this->builder->getGrammar()->wrap($alias)
            );
        }

        return parent::compileColumn($column, false);
    }

    /**
     * Resolve a registered select alias for an order expression.
     *
     * @param  string $column
     * @return string|null
     */
    protected function resolveSelectionAlias(string $column): ?string
    {
        $parsed = $this->parseColumnParts($column);

        return $this->builder->getSelectAlias($parsed['column']);
    }
}

<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\Support\CompiledExpression;

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
     * This override forces aliasing to be allowed and registers the
     * compiled selection for later alias reuse.
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
        $expression = parent::compileColumn($column, true);

        $this->registerSelection($column, $expression);

        return $expression;
    }

    /**
     * Register a compiled select expression for later alias reuse.
     *
     * @param  string      $column
     * @param  CompiledExpression $expression
     * @return void
     */
    protected function registerSelection(
        string $column,
        CompiledExpression $expression
    ): void
    {
        $parsed = $this->parseColumnParts($column);
        $key    = $parsed['column'];
        $alias  = $parsed['alias'];

        if ($alias === null) {
            $sql = $expression->getValue($this->builder->getGrammar());
            $alias = $this->builder->parseAlias($sql);
        }

        if ($alias !== null && $alias !== '') {
            $this->builder->registerSelectAlias($key, $alias);
        }
    }
}

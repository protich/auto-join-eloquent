<?php

namespace protich\AutoJoinEloquent\Compilers;

use Exception;
use Illuminate\Database\Query\Builder;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Support\CompiledExpression;

/**
 * WhereCompiler
 *
 * Compiles WHERE clause column expressions.
 * Disallows direct aggregates and supports scalar descriptor expressions.
 */
class WhereCompiler extends BaseCompiler
{
    /**
     * {@inheritDoc}
     */
    protected const BINDING_TYPE = 'where';

    /**
     * Compile a WHERE clause column expression.
     *
     * WHERE clauses do not support SQL aliases or direct aggregate functions.
     * Correlated count descriptors remain valid scalar subqueries.
     *
     * @param string $column The raw column expression.
     * @param bool $allowAlias Required by interface; always ignored (false).
     * @return CompiledExpression The compiled expression.
     * @throws Exception If an aggregate expression is detected in WHERE clause.
     */
    public function compileColumn(
        string $column,
        bool $allowAlias = false
    ): CompiledExpression
    {
        if ($modelPath = $this->parseDescribedPathExpression($column)) {
            if (in_array($modelPath['descriptor']->type(), [
                ExpressionDescriptor::TYPE_SUM,
                ExpressionDescriptor::TYPE_AVG,
                ExpressionDescriptor::TYPE_MIN,
                ExpressionDescriptor::TYPE_MAX,
            ], true)) {
                throw new Exception(
                    'Aggregate expressions are not allowed in WHERE clauses.'
                );
            }

            return $this->compileModelDefinedPath($modelPath, false);
        }

        if ($this->parseAggregateExpression($column)) {
            throw new Exception("Aggregate expressions are not allowed in WHERE clauses.");
        }

        // Aliasing not allowed — pass false explicitly
        return $this->compileStandardColumn($column, false);
    }

    /**
     * Compile all WHERE clause entries, including nested expressions.
     *
     * Recursively compiles nested where groups (type = "Nested") using a fresh WhereCompiler instance.
     *
     * @param array<int|string,mixed> $wheres
     * @return array<int|string,mixed>
     */
    public function compileClause(array $wheres): array
    {
        $compiled = [];

        foreach ($wheres as $key => $where) {
            $this->beginClause();

            if (is_array($where)) {
                if (isset($where['type'])
                    && $where['type'] === 'Nested'
                    && $where['query'] instanceof Builder) {
                    // Recursively compile the nested where builder
                    $nestedCompiler = new self($this->builder);
                    $nestedCompiler->bindingOffset = $this->bindingOffset;
                    $compiledNested = $nestedCompiler->compileClause($where['query']->wheres);
                    $where['query']->wheres = $compiledNested;
                    $this->bindingOffset = $nestedCompiler->bindingOffset;
                    $compiled[$key] = $where;
                    continue;
                }

                // Standard column or raw SQL expression
                if (isset($where['column']) && is_string($where['column'])) {
                    $where['column'] = $this->compileColumn( $where['column']);
                }

                if (
                    is_string($where['sql'] ?? null)
                    && is_string($where['type'] ?? null)
                    && strcasecmp($where['type'], 'Raw') === 0
                ) {
                    $where['sql'] = $this->compileRawSql( $where['sql']);
                }
            }

            $compiled[$key] = $where;
            $this->finishClause($where);
        }

        return $compiled;
    }
}

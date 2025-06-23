<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Builder;
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
     * WHERE clauses do not support SQL aliases or aggregate functions.
     * This method throws if an aggregate is detected, and otherwise delegates
     * to the base compiler with aliasing explicitly disabled.
     *
     * @param string $column The raw column expression.
     * @param bool $allowAlias Required by interface; always ignored (false).
     * @return Expression The compiled expression.
     * @throws Exception If an aggregate expression is detected in WHERE clause.
     */
    public function compileColumn(string $column, bool $allowAlias = false): Expression
    {
        if ($this->parseAggregateExpression($column)) {
            throw new Exception("Aggregate expressions are not allowed in WHERE clauses.");
        }

        // Aliasing not allowed — pass false explicitly
        return parent::compileColumn($column, false);
    }

    /**
     * Compile all WHERE clause entries, including nested expressions.
     *
     * Recursively compiles nested where groups (type = "Nested") using a fresh WhereCompiler instance.
     *
     * @param array<int, mixed> $wheres
     * @return array<int, mixed>
     */
    public function compileClause(array $wheres): array
    {
        return collect($wheres)->map(function (mixed $where) {
            if (is_array($where)) {
                if (isset($where['type'])
                    && $where['type'] === 'Nested'
                    && $where['query'] instanceof Builder) {
                    // Recursively compile the nested where builder
                    $nestedCompiler = new self($this->builder);
                    $compiledNested = $nestedCompiler->compileClause($where['query']->wheres ?? []);
                    $where['query']->wheres = $compiledNested;
                    return $where;
                }

                // Standard column or raw SQL expression
                if (isset($where['column'])) {
                    $compiled = $this->compileColumn((string) $where['column']);
                    $where['column'] = $compiled instanceof Expression ? $compiled : (string) $compiled;
                }

                if (isset($where['sql'])
                    && strcasecmp((string) ($where['type'] ?? ''), 'Raw') === 0) {
                    $where['sql'] = $this->compileRawSql((string) $where['sql']);
                }
            }

            return $where;
        })->all();
    }
}

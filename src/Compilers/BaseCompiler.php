<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use Illuminate\Database\Query\Expression;

/**
 * BaseCompiler
 *
 * Provides shared logic for compiling SQL expressions across SELECT, WHERE, HAVING, GROUP BY, and ORDER BY clauses.
 * Handles support for:
 * - Relationship-aware column resolution
 * - Suffix-based aggregates (e.g., __count)
 * - COALESCE(...) expressions
 * - Aggregate SQL parsing (e.g., COUNT(...), SUM(...))
 */
abstract class BaseCompiler
{
    protected AutoJoinQueryBuilder $builder;

    public function __construct(AutoJoinQueryBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Compile an array of clause entries (e.g. columns, orders, havings).
     *
     * Handles string inputs, structured arrays, and Expression objects.
     *
     * @param array<array<mixed>|string|Expression> $clauses
     * @return array<array<mixed>|string|Expression>
     */
    public function compileClause(array $clauses): array
    {
        // @phpstan-ignore-next-line
        return collect($clauses)->map(function (string|array|Expression $clause) {
            // Unwrap Expression to raw SQL string
            if ($clause instanceof Expression) {
                // @phpstan-ignore-next-line
                $clause = $clause->getValue($this->builder->getGrammar());
            }

            // Handle scalar column (e.g., 'user.id')
            if (is_string($clause)) {
                return $this->compileColumn($clause);
            }

            // Structured clause: column or raw SQL
            if (is_array($clause)) {
                // ['column' => 'user.id__count']
                if (isset($clause['column'])) {
                    $compiled = $this->compileColumn((string) $clause['column']); // @phpstan-ignore-line
                    $clause['column'] = $compiled instanceof Expression ? $compiled : (string) $compiled; // @phpstan-ignore-line
                    return $clause;
                }

                // ['type' => 'Raw', 'sql' => 'COUNT(...) > 5']
                if (isset($clause['sql']) && strcasecmp((string) ($clause['type'] ?? ''), 'Raw') === 0) { // @phpstan-ignore-line
                    $clause['sql'] = $this->compileRawSql((string) $clause['sql']); // @phpstan-ignore-line
                    return $clause;
                }
            }

            return $clause;
        })->all();
    }

    /**
     * Compile a single column expression.
     *
     * Supports:
     * - Aggregates (COUNT(...), SUM(...))
     * - Suffix-based aggregates (e.g., __count)
     * - COALESCE(...)
     * - Basic relationship-aware columns
     *
     * @param string $column
     * @param bool $allowAlias
     * @return Expression
     */
    public function compileColumn(string $column, bool $allowAlias = false): Expression
    {
        if ($aggregate = $this->parseAggregateExpression($column)) {
            return $this->compileAggregateExpression($aggregate, $allowAlias);
        }

        if ($coalesce = $this->parseCoalesceExpression($column)) {
            return new Expression($this->compileCoalesce($coalesce));
        }

        $parsed = $this->parseColumnParts($column);

        return $this->builder->resolveColumnExpression(
            $parsed['column'],
            $allowAlias ? $parsed['alias'] : null
        );
    }

    /**
     * Compile raw SQL that may contain COUNT(...), COALESCE(...), or bitwise operations.
     *
     * This method inspects the raw SQL string to detect and resolve relationship-based
     * expressions. It compiles aggregate or COALESCE expressions if they reference
     * relationships. For bitwise expressions (e.g., `ticket.flags & ? = 0`), it resolves
     * the left-hand side if it's a relationship path.
     *
     * @param string $sql
     * @return string
     */
    public function compileRawSql(string $sql): string
    {
        // COUNT(...), SUM(...), etc.
        if ($aggregate = $this->parseAggregateExpression($sql)) {
            if ($this->isRelationshipReference($aggregate['innerExpression'])) {
                return $this->compileAggregate($aggregate);
            }
        }

        // COALESCE(a, b) [as alias]
        if ($coalesce = $this->parseCoalesceExpression($sql)) {
            return $this->compileCoalesce($coalesce);
        }

        // Bitwise operator (e.g., ticket.flags & ? = 0)
        if ($bitwise = $this->parseBitwiseExpression($sql)) {
            if ($this->isRelationshipReference($bitwise['left'])) {
                $resolved = $this->builder->resolveColumnExpression($bitwise['left'], null, false);
                // @phpstan-ignore-next-line
                $left = $resolved instanceof Expression
                    ? $resolved->getValue($this->builder->getGrammar())
                    : (string) $resolved;

                return sprintf('%s %s %s',
                    $left, $bitwise['operator'], $bitwise['right']);
            }
        }

        // Fallback: return original raw SQL unchanged
        return $sql;
    }

    /**
     * Parse column expression into normalized parts.
     *
     * Supports aliasing (e.g., 'user.id as user_id') and relationship conversion.
     *
     * @param string $column
     * @return array{column: string, alias: string|null}
     */
    protected function parseColumnParts(string $column): array
    {
        $alias = null;
        $column = trim($column);

        if (preg_match('/^(.*?)\s+as\s+(.*?)$/i', $column, $matches)) {
            $column = trim($matches[1]);
            $alias = trim($matches[2]) ?: null;
        }

        // Normalize dot notation (user.agent.id → user__agent.id)
        $parts = explode('.', $column);
        if (count($parts) > 1) {
            $field = array_pop($parts);
            $column = implode('__', $parts) . '.' . $field;
        }

        return [
            'column' => $column,
            'alias'  => $alias,
        ];
    }

    /**
     * Parse a bitwise expression of the form: `column & something`
     *
     * Example:
     *   "ticket.flags & ? = 0" →
     *   [
     *     'left' => 'ticket.flags',
     *     'operator' => '&',
     *     'right' => '? = 0'
     *   ]
     *
     * This is useful for resolving the left-hand column as a relationship path.
     *
     * @param string $expression
     * @return array{left: string, operator: string, right: string}|false
     */
    protected function parseBitwiseExpression(string $expression): array|false
    {
        if (preg_match('/^\s*([a-zA-Z0-9_.]+)\s*(&)\s*(.+)$/', $expression, $matches)) {
            return [
                'left'     => trim($matches[1]),
                'operator' => $matches[2],
                'right'    => trim($matches[3]),
            ];
        }

        return false;
    }

    /**
     * Detect and parse aggregate functions, including suffix-based style (e.g., __count).
     *
     * @param string $expression
     * @return array{aggregateFunction: string, innerExpression: string, alias: string|null, outerExpression: string}|false
     */
    protected function parseAggregateExpression(string $expression): array|false
    {
        // Standard SQL function: COUNT(user.id)
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\((.+?)\)(?:\s+as\s+(\w+))?(.*)$/i', $expression, $m)) {
            $alias = $m[3] ? trim($m[3]) : null;

            return [
                'aggregateFunction' => strtoupper($m[1]),
                'innerExpression'   => trim($m[2]),
                'alias'             => $alias !== '' ? $alias : null,
                'outerExpression'   => trim($m[4] ?: ''),
            ];
        }

        // Suffix-based shorthand (e.g., user.id__count)
        if (!($this instanceof WhereCompiler || $this instanceof GroupByCompiler)
            && preg_match('/^(.*?)__(count|sum|avg|min|max)(?:\s+as\s+(\w+))?$/i', $expression, $m)) {

            $functionMap = [
                'count' => 'COUNT',
                'sum'    => 'SUM',
                'avg'    => 'AVG',
                'min'    => 'MIN',
                'max'    => 'MAX',
            ];

            $function = $functionMap[strtolower($m[2])] ?? null;
            if (!$function) {
                return false;
            }

            return [
                'aggregateFunction' => $function,
                'innerExpression'   => trim($m[1]),
                'alias'             => isset($m[3]) && $m[3] !== '' ? $m[3] : null, // @phpstan-ignore-line
                'outerExpression'   => '',
            ];
        }

        return false;
    }

    /**
     * Compile an aggregate clause (COUNT, SUM, etc.).
     *
     * @param array{aggregateFunction: string, innerExpression: string, alias: string|null, outerExpression: string} $info
     * @return string
     */
    protected function compileAggregate(array $info): string
    {
        $expr = $this->compileAggregateExpression($info, false);
        // @phpstan-ignore-next-line
        $sql = $expr->getValue($this->builder->getGrammar());

        if (!empty($info['outerExpression'])) {
            $sql .= ' ' . (string) $info['outerExpression']; // @phpstan-ignore-line
        }

        return $sql; // @phpstan-ignore-line
    }

    /**
     * Compile an aggregate expression into a wrapped Expression.
     *
     * @param array{aggregateFunction: string, innerExpression: string, alias: string|null} $info
     * @param bool $useDefaultAlias
     * @return Expression
     */
    protected function compileAggregateExpression(array $info, bool $useDefaultAlias = false): Expression
    {
        $grammar = $this->builder->getGrammar();
        $parsed = $this->parseColumnParts($info['innerExpression']);
        $resolved = $this->builder->resolveColumnExpression($parsed['column'], null, false);

        $innerSql = $resolved->getValue($grammar); // @phpstan-ignore-line

        $alias = $info['alias']
            ?? ($useDefaultAlias ? $info['aggregateFunction'] . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $innerSql) : null); // @phpstan-ignore-line

        $sql = sprintf('%s(%s)', $info['aggregateFunction'], $innerSql); // @phpstan-ignore-line

        if ($alias !== null) {
            $sql .= ' as ' . $grammar->wrap($alias);
        }

        return new Expression($sql);
    }

    /**
     * Parse a COALESCE(...) expression with optional alias and condition.
     *
     * @param string $expression
     * @return array{fields: string[], alias: string|null, outerExpression: string}|false
     */
    protected function parseCoalesceExpression(string $expression): array|false
    {
        if (!preg_match('/^COALESCE\s*\((.*?)\)(.*)$/i', $expression, $m)) {
            return false;
        }

        $fields = array_map('trim', explode(',', $m[1]));
        $remainder = trim($m[2] ?: '');

        $alias = null;
        $outer = '';

        if (preg_match('/^as\s+([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/i', $remainder, $am)) {
            $alias = trim($am[1]) ?: null;
            $outer = trim($am[2] ?: '');
        } else {
            $outer = $remainder;
        }

        return [
            'fields' => $fields,
            'alias' => $alias,
            'outerExpression' => $outer,
        ];
    }

    /**
     * @param array{fields: string[], alias: string|null, outerExpression: string} $info
     */
    protected function compileCoalesce(array $info): string
    {
        $expr = $this->compileCoalesceExpression($info);
        // @phpstan-ignore-next-line
        $sql = $expr->getValue($this->builder->getGrammar());

        if (!empty($info['outerExpression'])) {
            $sql .= ' ' . (string) $info['outerExpression']; // @phpstan-ignore-line
        }

        return $sql; // @phpstan-ignore-line
    }

    /**
     * Compile a COALESCE(...) expression into a wrapped Expression.
     *
     * @param array{fields: string[], alias: string|null} $info
     * @return Expression
     */
    protected function compileCoalesceExpression(array $info): Expression
    {
        $grammar = $this->builder->getGrammar();

        $resolved = array_map(function ($field) use ($grammar) {
            $column = $this->parseColumnParts($field)['column'];
            $expr = $this->builder->resolveColumnExpression($column, null, false);
            // @phpstan-ignore-next-line
            return $expr->getValue($grammar);// @phpstan-ignore-line
        }, $info['fields']);

        $sql = 'COALESCE(' . implode(', ', $resolved) . ')';

        if ($info['alias']) {
            $sql .= ' as ' . $grammar->wrap($info['alias']);
        }

        return new Expression($sql);
    }

    /**
     * Check if a given expression likely references a relationship.
     *
     * @param string $expression
     * @return bool
     */
    protected function isRelationshipReference(string $expression): bool
    {
        if (str_contains($expression, ' ')) {
            return false;
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $expression)) {
            return false;
        }

        return str_contains($expression, '.') || str_contains($expression, '__');
    }
}

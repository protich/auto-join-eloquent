<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use Illuminate\Database\Query\Expression;

/**
 * BaseCompiler
 *
 * Provides shared logic for compiling SQL expressions across SELECT, WHERE, HAVING, GROUP BY, and ORDER BY clauses.
 * Supports COALESCE, aggregate functions, and alias-aware resolution through AutoJoinQueryBuilder.
 */
abstract class BaseCompiler
{
    /**
     * The query builder instance responsible for relationship-aware resolution.
     *
     * @var AutoJoinQueryBuilder
     */
    protected AutoJoinQueryBuilder $builder;

    /**
     * Constructor.
     *
     * @param AutoJoinQueryBuilder $builder
     */
    public function __construct(AutoJoinQueryBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * Compile an array of clause entries using the appropriate logic for each type.
     *
     * Each item in the clause array may be one of:
     * - A scalar string (e.g., 'name') typically used in SELECT or GROUP BY
     * - An Expression object (e.g., DB::raw(...)), which will be compiled like a string
     * - A structured clause array (e.g., ['column' => '...', 'direction' => 'asc'])
     * - A raw SQL clause array (e.g., ['type' => 'Raw', 'sql' => '...'])
     *
     * This method normalizes each clause entry and applies compileColumn() or compileRawSql()
     * as needed, returning a transformed clause array that is safe for Laravel’s grammar layer.
     *
     * @param array $clauses An array of clauses to compile.
     * @return array The compiled clause array with resolved expressions.
     */
    public function compileClause(array $clauses): array
    {
        return collect($clauses)->map(function ($clause) {
            // Normalize Expression: convert to string so it can be parsed
            if ($clause instanceof Expression) {
                // @phpstan-ignore-next-line
                $clause = $clause->getValue($this->builder->getGrammar());
            }

            // Scalar input (e.g., 'name', 'COALESCE(...)') — compile directly
            if (is_string($clause)) {
                return $this->compileColumn($clause);
            }

            // Structured clause (e.g., ['column' => 'name'] or ['type' => 'Raw', 'sql' => '...'])
            if (is_array($clause)) {

                // Handle structured column clause
                if (isset($clause['column'])) {
                    $compiled = $this->compileColumn($clause['column']);
                    $clause['column'] = $compiled instanceof Expression ? $compiled : (string) $compiled;
                    return $clause;
                }

                // Handle structured raw SQL clause (e.g., HAVING, ORDER BY)
                if (isset($clause['sql']) && strcasecmp($clause['type'] ?? '', 'Raw') === 0) {
                    $clause['sql'] = $this->compileRawSql($clause['sql']);
                    return $clause;
                }
            }

            // Fallback: return clause unchanged if structure not recognized
            return $clause;
        })->all();
    }

    /**
     * Compile a column expression.
     *
     * Handles COALESCE(...) and aggregate functions.
     * Aliases (e.g. 'AS alias') are applied only if $allowAlias is true.
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
     * Compile a raw SQL string, resolving aggregates or COALESCE if needed.
     *
     * @param string $sql
     * @return string
     */
    public function compileRawSql(string $sql): string
    {
        if ($aggregate = $this->parseAggregateExpression($sql)) {
            if ($this->isRelationshipReference($aggregate['innerExpression'])) {
                return $this->compileAggregate($aggregate);
            }
        }

        if ($coalesce = $this->parseCoalesceExpression($sql)) {
            return $this->compileCoalesce($coalesce);
        }

        return $sql;
    }

    /**
     * Parse a column string into base expression and optional alias.
     *
     *     "users.agent.id as some_alias"
     *
     * becomes:
     *
     *     [
     *         'column' => "users__agent.id",
     *         'alias'      => "some_alias"
     *     ]
     *
     * If no dot exists, then no normalization needed
     *
     * @param string $column The raw column expression.
     * @return array{column: string, alias: string|null} An associative array with keys 'column' and 'alias'.
     */
    protected function parseColumnParts(string $column): array
    {
        $alias = null;
        $column = trim($column);

        if (preg_match('/^(.*?)\s+as\s+(.*?)$/i', $column, $matches)) {
            $column = trim($matches[1]);
            $alias = trim($matches[2]);
        }

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
     * Parse an aggregate expression.
     *
     * Supports:
     * - COUNT(...), SUM(...), etc.
     * - With optional alias or trailing condition (e.g. '> ?', 'IS NOT NULL')
     *
     * @param string $expression
     * @return array{aggregateFunction: string, innerExpression: string, alias: string|null, outerExpression: string}|false
     */
    protected function parseAggregateExpression(string $expression): array|false
    {
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\((.+?)\)(?:\s+as\s+(\w+))?(.*)$/i', $expression, $matches)) {
            $alias = isset($matches[3]) ? trim($matches[3]) : null;

            return [
                'aggregateFunction' => strtoupper($matches[1]),
                'innerExpression'   => trim($matches[2]),
                'alias'             => $alias !== '' ? $alias : null,
                'outerExpression'   => trim($matches[4] ?? ''),
            ];
        }

        return false;
    }

    /**
     * Compile an aggregate expression into a string with optional trailing condition.
     *
     * @param array $info
     * @return string
     */
    protected function compileAggregate(array $info): string
    {
        $expr = $this->compileAggregateExpression($info, false);
        // @phpstan-ignore-next-line
        $sql = $expr->getValue($this->builder->getGrammar());

        if (!empty($info['outerExpression'])) {
            $sql .= ' ' . $info['outerExpression'];
        }

        return $sql;
    }

    /**
     * Compile an aggregate expression into a Laravel Expression.
     *
     * @param array{aggregateFunction: string, innerExpression: string, alias: string|null} $info
     * @param bool $useDefaultAlias
     * @return Expression
     */
    protected function compileAggregateExpression(array $info, bool $useDefaultAlias = false): Expression
    {
        $grammar = $this->builder->getGrammar();

        $parsed = $this->parseColumnParts($info['innerExpression']);
        $resolved = $this->builder->resolveColumnExpression($parsed['column']);

        $innerSql = $resolved instanceof Expression
            // @phpstan-ignore-next-line
            ? $resolved->getValue($grammar)
            : (string) $resolved;
        $alias = $info['alias']
            ?? ($useDefaultAlias ? $info['aggregateFunction'] . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $innerSql) : null);

        $sql = sprintf('%s(%s)', $info['aggregateFunction'], $innerSql);

        if ($alias !== null) {
            $sql .= ' as ' . $grammar->wrap($alias);
        }

        return new Expression($sql);
    }

    /**
     * Parse a COALESCE(...) expression into fields + optional alias/condition.
     *
     * @param string $expression
     * @return array{fields: string[], alias: string|null, outerExpression: string}|false
     */
    protected function parseCoalesceExpression(string $expression): array|false
    {
        if (!preg_match('/^COALESCE\s*\((.*?)\)(.*)$/i', $expression, $matches)) {
            return false;
        }

        $fields = array_map('trim', explode(',', $matches[1]));
        $remainder = trim($matches[2] ?? '');

        $alias = null;
        $outer = '';

        if (preg_match('/^as\s+([a-zA-Z_][a-zA-Z0-9_]*)(.*)$/i', $remainder, $aliasMatches)) {
            $alias = trim($aliasMatches[1]);
            $outer = trim($aliasMatches[2] ?? '');
        } else {
            $outer = $remainder;
        }

        return [
            'fields'          => $fields,
            'alias'           => $alias,
            'outerExpression' => $outer,
        ];
    }

    /**
     * Compile a COALESCE(...) expression into raw SQL.
     *
     * @param array $info
     * @return string
     */
    protected function compileCoalesce(array $info): string
    {
        $expr = $this->compileCoalesceExpression($info);
        // @phpstan-ignore-next-line
        $sql = $expr->getValue($this->builder->getGrammar());

        if (!empty($info['outerExpression'])) {
            $sql .= ' ' . $info['outerExpression'];
        }

        return $sql;
    }

    /**
     * Compile a COALESCE(...) expression into a Laravel Expression.
     *
     * @param array $info
     * @return Expression
     */
    protected function compileCoalesceExpression(array $info): Expression
    {
        $grammar = $this->builder->getGrammar();

        $resolved = array_map(function ($field) use ($grammar) {
            $column = $this->parseColumnParts($field)['column'];
            $expr = $this->builder->resolveColumnExpression($column);
            return $expr instanceof Expression
                // @phpstan-ignore-next-line
                ? $expr->getValue($grammar)
                : (string) $expr;
        }, $info['fields']);

        $sql = 'COALESCE(' . implode(', ', $resolved) . ')';

        if ($info['alias']) {
            $sql .= ' as ' . $grammar->wrap($info['alias']);
        }

        return new Expression($sql);
    }

    /**
     * Check if a string expression looks like a relationship path.
     *
     * Used to determine whether to resolve joins in aggregate/coalesce logic.
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

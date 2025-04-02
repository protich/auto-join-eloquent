<?php

namespace protich\AutoJoinEloquent\Compilers;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use Illuminate\Database\Query\Expression;

abstract class AbstractCompiler
{
    /**
     * The AutoJoinQueryBuilder instance.
     *
     * @var \protich\AutoJoinEloquent\AutoJoinQueryBuilder
     */
    protected $builder;

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
     * Get the AutoJoinQueryBuilder instance.
     *
     * @return AutoJoinQueryBuilder
     */
    public function getBuilder(): AutoJoinQueryBuilder
    {
        return $this->builder;
    }



    /**
     * Parse a column and return its parts.
     *
     * This method extracts an alias (if provided via an "as" clause) and normalizes the expression by exploding
     * on dot ("."). If more than one part exists, it converts the relationship chain (all parts except the last)
     * to double-underscore notation and appends the final field (preceded by a dot). For example:
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
     * @return array An associative array with keys 'column' and 'alias'.
     */
    protected function parseColumnParts(string $column): array
    {
        $alias = null;
        $column = trim($column);

        // Extract alias if provided using a regex.
        if (preg_match('/^(.*?)\s+as\s+(.*?)$/i', $column, $matches)) {
            $column = trim($matches[1]);
            $alias = trim($matches[2]);
        }

        $parts = explode('.', $column);
        if (count($parts) > 1) {
            $field = array_pop($parts);
            // Reassemble: relationship chain (normalized) then a dot then the field.
            $column = sprintf('%s.%s', implode('__', $parts), $field);
        }

        return [
            'column'    => $column,
            'alias'     => $alias,
        ];
    }

    /**
     * Parse an aggregate SQL expression.
     *
     * This method supports expressions such as:
     *   - "COUNT(agent__departments.id) as dept_count"
     *   - "COUNT(agent.departments.id) > ?"
     *
     * It returns an array containing:
     *   - 'aggregateFunction': The aggregate function (e.g. COUNT, SUM) in uppercase.
     *   - 'innerExpression': The expression inside the parentheses.
     *   - 'alias': An optional alias if provided.
     *   - 'outerExpression': Any trailing part (e.g. operator and parameter) after the aggregate.
     *
     * @param string $expression The aggregate expression.
     * @return array{aggregateFunction: string, innerExpression: string, alias: string|null, outerExpression: string}|false
     */
    protected function parseAggregateExpression(string $expression): array|false
    {
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\((.+)\)(?:\s+as\s+(.+))?(.*)$/i', $expression, $matches)) {
            return [
                'aggregateFunction' => strtoupper($matches[1]),
                'innerExpression'   => trim($matches[2]),
                'alias'             => isset($matches[3]) ? trim($matches[3]) : null,
                'outerExpression'   => trim($matches[4]),
            ];
        }
        return false;
    }

    /**
     * Compile an aggregate column expression using the parsed aggregate info.
     *
     * This method parses the inner expression and delegates to the builder to resolve the
     * fully qualified inner expression. It then constructs the aggregate expression
     *
     * @param array $info Associative array with keys 'aggregateFunction', 'innerExpression', 'alias'.
     * @param bool $useDefaultAlias Optional flag; if true, generates a default alias if none is provided.
     * @return \Illuminate\Database\Query\Expression The compiled aggregate expression.
     */
    protected function compileAggregateExpression(array $info, bool $useDefaultAlias = false): Expression
    {
        $grammar = $this->builder->getGrammar();
        // Parse the inner expression.
        $innerParsed = $this->parseColumnParts($info['innerExpression']);
        // Delegate to the builder to resolve the fully qualified inner expression.
        $compiledInnerExpression = $this->builder->resolveColumnExpression($innerParsed['column']);
        // Stringify the inner expression.
        $innerValue = $compiledInnerExpression instanceof Expression
        ? $compiledInnerExpression->getValue($grammar)
        : (string) $compiledInnerExpression;

        // Determine alias if any
        $alias = !empty($info['alias'])
            ? $info['alias']
            : ($useDefaultAlias ? $aggregateFunction . '_' . preg_replace('/[^a-zA-Z0-9_]/', '', $innerValue) : null);
        // Build the aggregate expression.
        $aggregateExpression = sprintf('%s(%s)', $info['aggregateFunction'], $innerValue);
        // Add alias if any
        $grammar = $this->builder->getGrammar();
        if ($alias !== null)
            $aggregateExpression = sprintf('%s as %s', $aggregateExpression,  $grammar->wrap($alias));

        return new Expression($aggregateExpression);
    }

    /**
     * Check if the given expression appears to reference a relationship.
     *
     * A valid relationship reference should:
     * - Not contain any spaces.
     * - Consist solely of valid identifier characters (letters, digits, underscores, and dots).
     * - Contain at least one dot or double underscores to indicate a relationship chain.
     *
     * @param string $expression The expression to check.
     * @return bool True if the expression is likely a relationship reference; false otherwise.
     */
    protected function isRelationshipReference(string $expression): bool
    {
        // Reject if there are spaces.
        if (strpos($expression, ' ') !== false) {
            return false;
        }
        // Validate that the expression is a proper identifier (no invalid characters).
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $expression)) {
            return false;
        }
        // Must contain either a dot or double underscores.
        return (strpos($expression, '.') !== false || strpos($expression, '__') !== false);
    }
}

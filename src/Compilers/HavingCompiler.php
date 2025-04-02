<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;
use InvalidArgumentException;

class HavingCompiler extends AbstractCompiler
{
    /**
     * Compile a standard HAVING clause column expression.
     *
     * If the expression represents an aggregate function (e.g. COUNT, SUM, AVG, MIN, MAX),
     * it compiles the aggregate using compileAggregateExpression(). Otherwise, it parses
     * the column and resolves it via the builder.
     *
     * @param string $column The HAVING clause column expression.
     * @return Expression The compiled HAVING clause expression.
     */
    public function compileColumn(string $column): Expression
    {
        if ($aggregateInfo = $this->parseAggregateExpression($column)) {
            return $this->compileAggregateExpression($aggregateInfo, false);
        }
        $parts = $this->parseColumnParts($column);
        return $this->builder->resolveColumnExpression($parts['column']);
    }

    /**
     * Compile a raw SQL HAVING clause.
     *
     * This method examines the provided raw SQL string. If it matches an aggregate
     * function pattern and its inner expression appears to reference a relationship,
     * it compiles the aggregate using compileAggregateExpression() and returns the
     * resulting SQL string. Otherwise, it returns the original raw SQL unchanged.
     *
     * @param string $sql The raw SQL HAVING clause.
     * @return string The compiled SQL string.
     */
    public function compileRawSql(string $sql): string
    {
        if ($aggregateInfo = $this->parseAggregateExpression($sql)) {
            if ($this->isRelationshipReference($aggregateInfo['innerExpression'])) {
                if ($expression = $this->compileAggregateExpression($aggregateInfo, false)) {
                    return sprintf('%s %s',
                        $expression->getValue($this->builder->getGrammar()),
                        $aggregateInfo['outerExpression'] ?: '');
                }
            }
        }
        return $sql;
    }

    /**
     * Compile a HAVING clause array.
     *
     * This method expects a HAVING clause as an array. If the array contains a 'column' key,
     * it compiles that column using compileColumn(). Otherwise, if it contains a truthy 'Raw'
     * key and an 'sql' key, it compiles the raw SQL using compileRawSql(). Otherwise, it returns
     * the array unchanged.
     *
     * @param array $having The HAVING clause array to compile.
     * @return array The compiled HAVING clause array.
     * @throws InvalidArgumentException If the input format is unrecognized.
     */
    public function compileClause(array $having): array
    {
        if (isset($having['column'])) {
            $having['column'] = $this->compileColumn($having['column']);
        } elseif (!strcasecmp($having['type'], 'Raw')
            && isset($having['sql'])) {
            $having['sql'] = trim($this->compileRawSql($having['sql']));
        }
        return $having;
    }
}

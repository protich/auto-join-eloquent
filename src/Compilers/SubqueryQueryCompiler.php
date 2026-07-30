<?php

namespace protich\AutoJoinEloquent\Compilers;

use Illuminate\Database\Query\Expression;
use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Support\CompiledExpression;

/**
 * Class: SubqueryQueryCompiler
 *
 * Compile queries intended for subquery use.
 *
 * This compiler reuses the normal clause compilers and then normalizes
 * compiled clause output so compiler-generated expressions become final
 * and do not re-enter the auto-join compilation pipeline.
 */
class SubqueryQueryCompiler extends QueryCompiler
{
    /**
     * Create a new subquery query compiler.
     *
     * @param  AutoJoinQueryBuilder $builder
     * @return void
     */
    public function __construct(AutoJoinQueryBuilder $builder)
    {
        parent::__construct($builder);
    }

    /**
     * Normalize compiled clause entries for subquery use.
     *
     * Compiler-generated expressions inside subqueries should be treated
     * as final SQL and left untouched by any later compilation pass.
     *
     * @param  string                   $clauseKey
     * @param  array<int|string,mixed>  $clauses
     * @return array<int|string,mixed>
     */
    protected function normalizeCompiledClause(string $clauseKey, array $clauses): array
    {
        return collect($clauses)->map(function ($clause) {
            if ($clause instanceof CompiledExpression) {
                return $clause;
            }

            if ($clause instanceof Expression) {
                $value = $clause->getValue($this->builder->getGrammar());

                return is_string($value)
                    ? new CompiledExpression($value)
                    : $clause;
            }

            if (is_array($clause) && isset($clause['column'])) {
                $column = $clause['column'];

                if ($column instanceof CompiledExpression) {
                    return $clause;
                }

                if ($column instanceof Expression) {
                    $value = $column->getValue(
                        $this->builder->getGrammar()
                    );

                    if (is_string($value)) {
                        $clause['column'] = new CompiledExpression($value);
                    }
                }

                return $clause;
            }

            return $clause;
        })->all();
    }
}

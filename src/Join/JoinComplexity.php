<?php

namespace protich\AutoJoinEloquent\Join;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\JoinClause;

/**
 * Class: JoinComplexity
 *
 * Detect query state that cannot be inferred safely from standard Eloquent
 * relationship key metadata. Complex joins require the model to describe
 * their additional constraints through AutoJoinRelation.
 *
 * @internal
 */
final class JoinComplexity
{
    /**
     * Determine whether a relationship contains complex join behavior.
     *
     * Eloquent's standard BelongsToMany pivot join is ignored because the
     * auto-joiner already derives it from relationship metadata. Additional
     * joins and row-affecting query state make the relationship complex.
     *
     * @param  Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $relation
     * @return bool
     */
    public static function isComplex(Relation $relation): bool
    {
        $query = $relation->getQuery()->applyScopes()->getQuery();

        foreach ([
            'wheres',
            'groups',
            'havings',
            'limit',
            'groupLimit',
            'offset',
            'unions',
            'unionLimit',
            'unionOffset',
            'lock',
        ] as $property) {
            if ($query->{$property} !== null && $query->{$property} !== []) {
                return true;
            }
        }

        if ($query->distinct !== false) {
            return true;
        }

        $joins = $query->joins ?? [];

        if (! $relation instanceof BelongsToMany) {
            return $joins !== [];
        }

        $standardPivotJoinFound = false;

        foreach ($joins as $join) {
            if (
                ! $standardPivotJoinFound
                && self::isStandardPivotJoin($relation, $join)
            ) {
                $standardPivotJoinFound = true;
                continue;
            }

            return true;
        }

        return ! $standardPivotJoinFound;
    }

    /**
     * Determine whether a query join is the standard join that Eloquent adds
     * when constructing a BelongsToMany relationship.
     *
     * Matching both the pivot table and the related-to-pivot key condition
     * prevents an unrelated first join from being silently discarded.
     *
     * @param  BelongsToMany<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Relations\Pivot, string>  $relation
     * @param  mixed  $join
     * @return bool
     */
    private static function isStandardPivotJoin(
        BelongsToMany $relation,
        mixed $join
    ): bool {
        if (
            ! $join instanceof JoinClause
            || $join->type !== 'inner'
            || $join->table !== $relation->getTable()
            || count($join->wheres) !== 1
        ) {
            return false;
        }

        $condition = $join->wheres[0];

        if (! is_array($condition)) {
            return false;
        }

        return ($condition['type'] ?? null) === 'Column'
            && ($condition['first'] ?? null)
                === $relation->getQualifiedRelatedKeyName()
            && ($condition['operator'] ?? null) === '='
            && ($condition['second'] ?? null)
                === $relation->getQualifiedRelatedPivotKeyName()
            && ($condition['boolean'] ?? null) === 'and';
    }
}

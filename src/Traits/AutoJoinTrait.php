<?php

namespace protich\AutoJoinEloquent\Traits;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;

/**
 * Trait: AutoJoinTrait
 *
 * Override Laravel's default Eloquent builder creation so models return
 * an AutoJoinQueryBuilder by default.
 */
trait AutoJoinTrait
{
    use AutoJoinQueryBuilderTrait;

    /**
     * Create a new Eloquent builder with auto-join support.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @return AutoJoinQueryBuilder
     */
    public function newEloquentBuilder($query): AutoJoinQueryBuilder
    {
        return $this->newAutoJoinBuilder($query);
    }
}

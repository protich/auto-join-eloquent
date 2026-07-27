<?php

namespace protich\AutoJoinEloquent\Traits;

use Illuminate\Database\Query\Builder;

/**
 * Trait: QueryJoinerTrait
 *
 * Provide manual auto-join query integration and model-defined path
 * support for models that opt into package behavior without overriding
 * Laravel's default Eloquent builder.
 */
trait QueryJoinerTrait
{
    use AutoJoinQueryBuilderTrait;

    /**
     * Scope a query to manually trigger auto join logic.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string                                $joinType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAutoJoins($query, string $joinType = 'left')
    {
        $model = $query->getModel();

        $builder = $model->newAutoJoinBuilder($query->getQuery(), $joinType);
        $builder->setBaseModel($model);

        return $query;
    }

}

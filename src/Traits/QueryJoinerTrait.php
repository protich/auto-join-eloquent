<?php

namespace protich\AutoJoinEloquent\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
     * @template TModel of Model
     * @param  Builder<TModel> $query
     * @param  string          $joinType
     * @return Builder<TModel>
     */
    public function scopeWithAutoJoins(
        Builder $query,
        string $joinType = 'left'
    ): Builder {
        $model = $query->getModel();

        $builder = $this->newAutoJoinBuilder(
            $query->getQuery(),
            $joinType
        );
        $builder->setBaseModel($model);

        return $query;
    }
}

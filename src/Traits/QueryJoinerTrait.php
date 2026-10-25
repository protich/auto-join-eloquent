<?php

namespace protich\AutoJoinEloquent\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Query\JoinClause;
use Closure;

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
     * @param Closure(JoinClause, Model, string): void|null $joinConstraint Explicit related-table ON constraints.
     * @param Closure(JoinClause, BelongsToMany<Model, Model, Pivot, string>, string): void|null $pivotConstraint Explicit pivot-stage ON constraints.
     * @return Builder<TModel>
     */
    public function scopeWithAutoJoins(
        Builder $query,
        string $joinType = 'left',
        ?Closure $joinConstraint = null,
        ?Closure $pivotConstraint = null
    ): Builder {
        $model = $query->getModel();

        $builder = $this->newAutoJoinBuilder(
            $query->getQuery(),
            $joinType
        );
        $builder->setBaseModel($model);
        $builder->setJoinConstraint($joinConstraint);
        $builder->setPivotJoinConstraint($pivotConstraint);

        return $query;
    }
}

<?php

namespace protich\AutoJoinEloquent\Traits;

trait QueryJoinerTrait
{
    use AutoJoinQueryBuilderTrait;

    /**
     * Scope a query to manually trigger auto join logic.
     *
     * This scope method, withAutoJoins, attaches a beforeQuery callback on the underlying query builder.
     * The callback retrieves the current model from the query and passes it to the AutoJoinQueryBuilder via
     * setBaseModel() for proper context, then applies auto join logic to add the necessary join clauses.
     * This enables you to chain withAutoJoins anywhere in the query to manually control the auto joining behavior.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $joinType The join type to use (default 'left').
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithAutoJoins($query, string $joinType = 'left')
    {
        // Retrieve the model instance associated with this query.
        $model = $query->getModel();

        // Attach a callback on the underlying query builder that will be executed before the query runs.
        $query->getQuery()->beforeQuery(function ($q) use ($model, $joinType) {
            // Create a new, fully configured AutoJoinQueryBuilder for the current query.
            $builder = $this->newAutoJoinQueryBuilder($q, $joinType);
            // Pass the model to the builder so it has full context about the base table.
            $builder->setBaseModel($model);
            // Apply auto join logic to modify the query's join clauses.
            $builder->autoJoinQuery($q);
        });

        return $query;
    }
}

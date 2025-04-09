<?php

namespace protich\AutoJoinEloquent\Traits;

trait AutoJoinTrait
{
    use AutoJoinQueryBuilderTrait;

    /**
     * Override the newEloquentBuilder to return an AutoJoinQueryBuilder with auto join logic.
     *
     * This method leverages newAutoJoinQueryBuilder() from AutoJoinQueryBuilderTrait to create
     * a custom query builder. It then attaches a beforeQuery callback on the underlying query
     * builder to inject auto join logic right before query execution. This ensures that join
     * clauses are automatically applied based on the model's relationships.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \protich\AutoJoinEloquent\AutoJoinQueryBuilder
     */
    public function newEloquentBuilder($query)
    {
        // Create a custom AutoJoinQueryBuilder using shared configuration.
        $builder = $this->newAutoJoinQueryBuilder($query);

        // Attach a callback that applies auto join logic before the query is executed.
        $query->beforeQuery(function ($query) use ($builder) {
            $builder->autoJoinQuery($query);
        });

        return $builder;
    }
}

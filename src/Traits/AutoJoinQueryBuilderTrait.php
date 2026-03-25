<?php

namespace protich\AutoJoinEloquent\Traits;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;

/**
 * Trait: AutoJoinQueryBuilderTrait
 *
 * Provide shared factory methods for constructing and configuring
 * AutoJoinQueryBuilder instances.
 */
trait AutoJoinQueryBuilderTrait
{
    /**
     * Option to use simple sequential aliases.
     *
     * @var bool
     */
    protected $useSimpleAliases = true;

    /**
     * Debug output flag for auto join queries.
     *
     * @var bool
     */
    public $debugOutput = false;

    /**
     * Create and configure a new AutoJoinQueryBuilder instance.
     *
     * This method centralizes the configuration for AutoJoinQueryBuilder,
     * setting the default join type, simple aliases flag, and debug
     * output flag based on the model's properties or configuration
     * defaults.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  string                             $joinType
     * @return AutoJoinQueryBuilder
     */
    protected function newAutoJoinQueryBuilder(
        $query,
        string $joinType = 'left'
    ): AutoJoinQueryBuilder {
        $builder = new AutoJoinQueryBuilder($query);

        $builder->setDefaultJoinType($joinType);

        /** @var bool $useSimple */
        $useSimple = $this->useSimpleAliases
            ?: config('auto_join_eloquent.use_simple_aliases', true);

        $builder->setUseSimpleAliases($useSimple);
        $builder->debugOutput = $this->debugOutput || (bool) getenv('AUTO_JOIN_DEBUG_SQL');

        return $builder;
    }

    /**
     * Create a new auto-join Eloquent builder.
     *
     * The underlying query builder is wrapped in the package-specific
     * AutoJoinQueryBuilder and a beforeQuery callback is registered so
     * auto-join processing runs immediately before execution.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  string                             $joinType
     * @return AutoJoinQueryBuilder
     */
    public function newAutoJoinBuilder(
        $query,
        string $joinType = 'left'
    ): AutoJoinQueryBuilder {
        $builder = $this->newAutoJoinQueryBuilder($query, $joinType);

        $query->beforeQuery(function ($query) use ($builder) {
            $builder->autoJoinQuery($query);
        });

        return $builder;
    }
}

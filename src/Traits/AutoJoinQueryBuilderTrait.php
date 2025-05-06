<?php

namespace protich\AutoJoinEloquent\Traits;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;

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
     * setting the default join type, simple aliases flag, and debug output flag based
     * on the model's properties or configuration defaults.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $joinType The join type to use (default 'left').
     * @return AutoJoinQueryBuilder
     */
    protected function newAutoJoinQueryBuilder($query, string $joinType = 'left'): AutoJoinQueryBuilder
    {
        $builder = new AutoJoinQueryBuilder($query);

        // Set the default join type.
        $builder->setDefaultJoinType($joinType);

        // Determine whether to use simple aliases from the property or config.
        /**
         * @var bool $useSimple
         */
        $useSimple = $this->useSimpleAliases ?: config('auto_join_eloquent.use_simple_aliases', true);
        $builder->setUseSimpleAliases($useSimple);

        // Set the debug output flag.
        $builder->debugOutput = $this->debugOutput || (bool)getenv('AUTO_JOIN_DEBUG_SQL');

        return $builder;
    }
}

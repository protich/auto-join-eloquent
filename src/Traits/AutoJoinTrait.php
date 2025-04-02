<?php

namespace protich\AutoJoinEloquent\Traits;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\JoinClauseInfo;
use Illuminate\Database\Eloquent\Relations\Relation;

trait AutoJoinTrait
{

    /**
     * Option to use simple sequential aliases (A, B, C, …).
     *
     * @var bool
     */
    protected $useSimpleAliases = true;

    /**
     * Debug output flag for auto-join queries.
     *
     * @var bool
     */
    public $debugOutput = false;

    /**
     * Set the debug output flag for auto-join queries.
     *
     * @param bool $flag
     * @return void
     */
    public function setAutoJoinDebug(bool $flag)
    {
        $this->debugOutput = $flag;
    }

    /**
     * Boot the AutoJoinTrait.
     *
     * This method is automatically called when the model boots.
     * Any initialization logic for auto-join functionality can be placed here.
     *
     * @return void
     */
    public static function bootAutoJoinTrait()
    {
        // Initialization logic can be added here if needed.
    }

    /**
     * Get join clause information for a given relationship.
     *
     * Delegates to JoinClauseInfo::getJoinInformation().
     *
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param string $joinType The join type to use (e.g., 'left' or 'inner').
     * @return \protich\AutoJoinEloquent\JoinClauseInfo
     */
    public function getJoinInformation(Relation $relation, string $joinType = 'left'): JoinClauseInfo
    {
        return JoinClauseInfo::getJoinInformation($relation, $joinType);
    }

    /**
     * Override the newEloquentBuilder to return an AutoJoinQueryBuilder.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \protich\AutoJoinEloquent\AutoJoinQueryBuilder
     */
    public function newEloquentBuilder($query)
    {
        $builder = new AutoJoinQueryBuilder($query);

        // Use the use_simple_aliases configuration value (default now true)
        $useSimple = property_exists($this, 'useSimpleAliases')
            ? $this->useSimpleAliases
            : config('auto_join_eloquent.use_simple_aliases', true);
        $builder->setUseSimpleAliases($useSimple);

        // Set the default join type (e.g., left or inner) from config
        $builder->setDefaultJoinType(config('auto_join_eloquent.join_type', 'left'));

        // Set the debug output flag using AUTO_JOIN_DEBUG_SQL environment variable
        $builder->debugOutput = $this->debugOutput || (bool) getenv('AUTO_JOIN_DEBUG_SQL');

        // Hook a callback to compile the query before execution.
        $query->beforeQuery(function ($query) use ($builder) {
            $builder->autoJoinQuery($query);
        });

        return $builder;
    }
}

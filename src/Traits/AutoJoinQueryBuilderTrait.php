<?php

namespace protich\AutoJoinEloquent\Traits;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Model\AutoJoinRelation;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;
use RuntimeException;

/**
 * Trait: AutoJoinQueryBuilderTrait
 *
 * Provide shared factory methods for constructing and configuring
 * AutoJoinQueryBuilder instances.
 */
trait AutoJoinQueryBuilderTrait
{
    /**
     * Describe a complete path carrying the reserved `model__` marker.
     *
     * Models override this hook only when they expose model-defined paths. The
     * request contains the complete expression so the package does not impose
     * application-specific segmentation rules.
     *
     * @param  PathRequest  $request
     * @return ExpressionDescriptor
     *
     * @throws RuntimeException When the model does not support the path.
     */
    public static function describeAutoJoinPath(
        PathRequest $request
    ): ExpressionDescriptor {
        throw new RuntimeException(sprintf(
            'Model [%s] does not support auto-join path [%s].',
            static::class,
            $request->path
        ));
    }

    /**
     * Describe the join constraints for a complex Eloquent relationship.
     *
     * This hook is called only when JoinComplexity detects query state beyond
     * standard relationship keys. Implementations mutate the supplied
     * AutoJoinRelation with every related, parent, or pivot constraint that
     * affects which rows match. The model's description is authoritative; the
     * package requires a non-empty description but cannot compare it for
     * equivalence with arbitrary Eloquent query-builder state.
     *
     * @param  AutoJoinRelation  $autoJoinRelation Model-facing relationship
     *                                             description.
     * @param  string            $name             Relationship method name.
     * @param  string            $path             Complete normalized query
     *                                             path that triggered the join.
     * @return void
     *
     * @throws RuntimeException When the model does not describe the relation.
     */
    public function describeAutoJoinRelation(
        AutoJoinRelation $autoJoinRelation,
        string $name,
        string $path
    ): void {
        throw new RuntimeException(sprintf(
            'Complex auto-join relation [%s] on model [%s] requires a description for path [%s].',
            $name,
            static::class,
            $path
        ));
    }

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

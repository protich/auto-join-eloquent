<?php

namespace protich\AutoJoinEloquent\Traits;

use Illuminate\Database\Eloquent\Relations\Relation;
use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Model\AutoJoinRelation;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Model\PathRequest;
use RuntimeException;
use Throwable;

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
     * Resolve and describe an Eloquent relationship used by an auto-join path.
     *
     * The base implementation resolves the named relationship with Eloquent's
     * automatic parent-key constraints disabled and returns an empty
     * description around it. Models may override this method to add related,
     * parent, or pivot constraints and should begin by calling the parent
     * implementation.
     *
     * @param  string  $name Relationship method name.
     * @param  string  $path Complete normalized path that triggered the join.
     * @return AutoJoinRelation Authoritative relationship description.
     *
     * @throws RuntimeException When the relationship cannot be resolved or
     *                          does not return an Eloquent relation.
     */
    public function describeAutoJoinRelation(
        string $name,
        string $path
    ): AutoJoinRelation {
        try {
            $relation = Relation::noConstraints(
                fn () => $this->{$name}()
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf(
                'Unable to resolve auto-join relationship [%s] on model [%s] for path [%s].',
                $name,
                static::class,
                $path
            ), previous: $exception);
        }

        if (! $relation instanceof Relation) {
            throw new RuntimeException(sprintf(
                'Method [%s] on model [%s] did not return an Eloquent relationship for path [%s].',
                $name,
                static::class,
                $path
            ));
        }

        return new AutoJoinRelation($relation);
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

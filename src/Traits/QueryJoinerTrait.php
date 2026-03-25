<?php

namespace protich\AutoJoinEloquent\Traits;

use Illuminate\Database\Query\Builder;
use RuntimeException;

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

    /**
     * Describe a model-defined auto-join path.
     *
     * Paths prefixed with `model__` are delegated to the model so it can
     * describe how a logical domain path should be resolved by the
     * auto-join compiler.
     *
     * Models should override this method when they want to support custom
     * logical paths such as `model__accessibleDepartments` or
     * `model__status`.
     *
     * @param  string            $path
     * @param  array<int,string> $remainder
     * @return array<string,mixed>
     *
     * @throws RuntimeException
     */
    public static function describeAutoJoinPath(string $path, array $remainder): array
    {
        throw new RuntimeException(sprintf(
            'Model [%s] does not support auto-join path [%s].',
            static::class,
            $path
        ));
    }
}

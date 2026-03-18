<?php

namespace protich\AutoJoinEloquent\Traits;

use Illuminate\Database\Query\Builder;
use RuntimeException;

trait AutoJoinTrait
{
    use AutoJoinQueryBuilderTrait;

    /**
     * Create a new Eloquent builder with auto-join support.
     *
     * The underlying query builder is wrapped in the package-specific
     * AutoJoinQueryBuilder and a beforeQuery callback is registered so
     * auto-join processing runs immediately before execution.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @return \protich\AutoJoinEloquent\AutoJoinQueryBuilder
     */
    public function newEloquentBuilder($query)
    {
        $builder = $this->newAutoJoinQueryBuilder($query);

        $query->beforeQuery(function (Builder $query) use ($builder) {
            $builder->autoJoinQuery($query);
        });

        return $builder;
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
     * @param  string              $path
     * @param  array<int,string>   $remainder
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

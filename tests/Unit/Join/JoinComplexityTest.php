<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Join;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use protich\AutoJoinEloquent\Join\JoinComplexity;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\Models\Group;
use protich\AutoJoinEloquent\Tests\Models\User;

/**
 * Verify the boundary between standard relation metadata and query state that
 * requires an authoritative model description.
 */
class JoinComplexityTest extends AutoJoinTestCase
{
    /**
     * Ensure every supported standard relationship type remains simple.
     *
     * @return void
     */
    public function test_standard_relationship_types_are_not_complex(): void
    {
        $this->assertFalse(JoinComplexity::isComplex(
            $this->relation(new Agent, 'user')
        ));
        $this->assertFalse(JoinComplexity::isComplex(
            $this->relation(new User, 'agent')
        ));
        $this->assertFalse(JoinComplexity::isComplex(
            $this->relation(new Group, 'children')
        ));
        $this->assertFalse(JoinComplexity::isComplex(
            $this->relation(new Agent, 'departments')
        ));
    }

    /**
     * Ensure row-affecting relation query state is considered complex.
     *
     * @return void
     */
    public function test_row_affecting_query_state_is_complex(): void
    {
        /** @var list<\Closure(Builder<Model>): mixed> $mutators */
        $mutators = [
            fn (Builder $query) => $query->where('users.name', 'Alice'),
            fn (Builder $query) => $query->groupBy('users.name'),
            fn (Builder $query) => $query->having('users.id', '>', 0),
            fn (Builder $query) => $query->limit(1),
            fn (Builder $query) => $query->offset(1),
            fn (Builder $query) => $query->distinct(),
            fn (Builder $query) => $query->lockForUpdate(),
        ];

        foreach ($mutators as $mutate) {
            $relation = $this->relation(new Agent, 'user');
            $mutate($relation->getQuery());

            $this->assertTrue(JoinComplexity::isComplex($relation));
        }

        $relation = $this->relation(new Agent, 'user');
        $relation->getQuery()->union(
            User::query()->select('users.*')
        );

        $this->assertTrue(JoinComplexity::isComplex($relation));
    }

    /**
     * Ensure projection and ordering do not require join constraints.
     *
     * @return void
     */
    public function test_projection_and_ordering_are_not_row_matching_complexity(): void
    {
        $relation = $this->relation(new Agent, 'user');
        $relation->getQuery()
            ->select('users.id')
            ->orderBy('users.name');

        $this->assertFalse(JoinComplexity::isComplex($relation));
    }

    /**
     * Ensure applied global scopes are detected without mutating the relation.
     *
     * @return void
     */
    public function test_global_scope_is_detected_without_mutating_relation(): void
    {
        $relation = $this->relation(new Agent, 'user');
        $builder = $relation->getQuery();

        $builder->withGlobalScope(
            'available',
            fn (Builder $query) => $query->whereNull('users.deleted_at')
        );

        $this->assertSame([], $builder->getQuery()->wheres);
        $this->assertTrue(JoinComplexity::isComplex($relation));
        $this->assertSame([], $builder->getQuery()->wheres);
    }

    /**
     * Ensure only Eloquent's verified pivot join is ignored.
     *
     * @return void
     */
    public function test_extra_or_replaced_pivot_joins_are_complex(): void
    {
        $relation = $this->relation(new Agent, 'departments');
        $query = $relation->getQuery()->getQuery();

        $query->join('users', 'users.id', '=', 'departments.manager_id');

        $this->assertTrue(JoinComplexity::isComplex($relation));

        $joins = $query->joins;
        if ($joins === null || count($joins) < 2) {
            $this->fail('Expected the relation query to contain two joins.');
        }
        $query->joins = [$joins[1]];

        $this->assertTrue(JoinComplexity::isComplex($relation));
    }

    /**
     * Construct a relation without Eloquent's automatic parent-key predicate.
     *
     * @param  Model   $model
     * @param  string  $method
     * @return Relation<Model, Model, mixed>
     */
    private function relation(Model $model, string $method): Relation
    {
        $relation = Relation::noConstraints(
            fn () => $model->{$method}()
        );

        if (!$relation instanceof Relation) {
            throw new \LogicException(sprintf(
                'Model method [%s::%s] did not return a relationship.',
                $model::class,
                $method
            ));
        }

        return $relation;
    }
}

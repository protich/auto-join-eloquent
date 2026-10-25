<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\JoinClause;
use protich\AutoJoinEloquent\Support\CompiledExpression;
use protich\AutoJoinEloquent\Tests\Models\Department;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;
use protich\AutoJoinEloquent\Traits\QueryJoinerTrait;

/**
 * Verify manual auto-join processing targets Eloquent's scoped query clone.
 */
class ScopedManualAutoJoinTest extends AutoJoinTestCase
{
    /**
     * Ensure global scopes do not separate compiled joins from the active
     * query.
     *
     * @return void
     */
    public function test_manual_auto_join_compiles_on_scoped_query_clone(): void
    {
        $query = ManuallyJoinedAgent::query()->withGlobalScope(
            'enabled',
            fn(Builder $builder) => $builder->where('flags', '>=', 0)
        );

        (new ManuallyJoinedAgent())->scopeWithAutoJoins($query);

        $query
            ->select('user.id as user_id')
            ->limit(1);

        $sql = $query->toSql();

        $this->assertStringContainsStringIgnoringCase(
            'left join "ost_users" as "B"',
            $sql
        );
        $this->assertNotNull($query->first());
    }
    /** Explicit ON constraints preserve rows and pagination while hiding unavailable related values. */
    public function test_explicit_join_constraints_preserve_left_join_rows_counts_and_bindings(): void
    {
        $model = new ManuallyJoinedAgent;
        $query = $model->scopeWithAutoJoins($model->newQuery(), joinConstraint:
            function (JoinClause $join, Model $related, string $alias): void {
                if ($related instanceof User) {
                    $join->where(new CompiledExpression($join->getGrammar()->wrap($alias).'.'.$join->getGrammar()->wrap('name')), 'Peter Rotich');
                }
            }
        )->select(['id', 'user.name as user_name'])->orderBy('id');
        $expected = $model->newQuery()->count();
        $this->assertGreaterThan(1, $expected);
        foreach ([1, 2] as $attempt) {
            $page = (clone $query)->paginate(100);
            $this->assertSame($expected, $page->total());
            $this->assertCount($expected, $page->items());
            $this->assertSame(['Peter Rotich'], $page->getCollection()->pluck('user_name')->filter()->values()->all());
            $this->assertContains(null, $page->getCollection()->pluck('user_name')->all());
        }
        $compiled = clone $query;
        $compiled->toSql();
        $this->assertSame(['Peter Rotich'], $compiled->getBindings());
    }

    /** A nested pivot aggregate must receive the same explicit restrictions as direct joins. */
    public function test_join_constraints_reach_nested_pivot_count_subqueries(): void
    {
        $model = new ManuallyJoinedAgent;
        $query = $model->scopeWithAutoJoins($model->newQuery(), joinConstraint:
            function (JoinClause $join, Model $related, string $alias): void {
                if ($related instanceof Department) {
                    $join->where(new CompiledExpression($join->getGrammar()->wrap($alias).'.'.$join->getGrammar()->wrap('id')), -1);
                }
            }
        )->select(['id', 'user__agent__departments.id__count as department_count',
            'user__agent__accessibleDepartments__id__count as accessible_count'])->groupBy('id')->orderBy('id');
        $rows = $query->get();
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertInstanceOf(Model::class, $row);
            $this->assertSame(0, $row->getAttribute('department_count'));
            $this->assertSame(0, $row->getAttribute('accessible_count'));
        }
    }

    /** Unavailable pivot targets must not create duplicate NULL rows for a permitted parent. */
    public function test_explicit_pivot_constraints_preserve_parent_rows_and_pagination(): void
    {
        $model = new ManuallyJoinedAgent;
        $query = $model->scopeWithAutoJoins($model->newQuery(), pivotConstraint:
            function (JoinClause $join, BelongsToMany $relation, string $alias): void {
                $column = new CompiledExpression($join->getGrammar()->wrap($alias).'.'
                    .$join->getGrammar()->wrap($relation->getRelatedPivotKeyName()));
                $join->where($column, -1);
            }
        )->select(['id', 'user__agent__departments.id as department_id'])->orderBy('id');
        $page = $query->paginate(100);
        $expected = $model->newQuery()->count();
        $this->assertSame($expected, $page->total());
        $this->assertCount($expected, $page->items());
        $this->assertSame([], $page->getCollection()->pluck('department_id')->filter()->all());
    }

}

/**
 * Test model that opts into the manual query-joiner scope.
 */
class ManuallyJoinedAgent extends Model
{
    use QueryJoinerTrait;

    /**
     * @var string
     */
    protected $table = 'agents';

    /**
     * Related user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

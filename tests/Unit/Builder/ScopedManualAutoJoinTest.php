<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

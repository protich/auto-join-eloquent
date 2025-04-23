<?php

namespace protich\AutoJoinEloquent\Tests\Integration;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Traits\QueryJoinerTrait;


use Illuminate\Database\Eloquent\Model;


// Inline Model class that doesn't use AutoJoinTrait
class User extends Model
{
    use QueryJoinerTrait;

    protected $table = 'users';
    public $timestamps = true;

    /**
     * Summary of agent
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function agent()
    {
        return $this->hasOne(Agent::class);
    }
}

class Agent extends Model
{
    use QueryJoinerTrait;

    protected $table = 'agents';
    public $timestamps = true;

    /**
     * Summary of user
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

/**
 * Class QueryJoinerIntegrationTest
 *
 * This integration test verifies that manual auto join logic is correctly applied using an
 * inline User model that leverages QueryJoinerTrait. The test builds a query using auto-join
 * notation to specify join types and nested relationships, inspects the generated SQL for expected
 * JOIN clauses, and asserts that the query returns non-empty results.
 *
 * @package protich\AutoJoinEloquent\Tests\Integration
 */
class QueryJoinerIntegrationTest extends AutoJoinTestCase
{
    /**
     * Summary of testQueryJoiner
     * @return void
     */
    public function testQueryJoiner()
    {

        // Build a query using auto-join notation.
        /** @phpstan-ignore-next-line */
        $query = User::query()->select([
            'name as agent',
            'email',
            'agent|inner.id as agent_id',  // Use INNER JOIN for the agent relationship.
            'agent.position',
        ])->withAutoJoins();

        // Retrieve the final SQL using debugSql() for inspection.
        /**
         * @var \Illuminate\Database\Query\Builder $query
         */
        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('INNER JOIN', $sql, 'The query should include an INNER JOIN for the agent relationship.');
        $this->assertStringContainsStringIgnoringCase('JOIN', $sql, 'The query should include JOIN clauses for nested relationships.');

        // Assert that the query returns non-empty results.
        $this->assertNonEmptyResults($query->get()->toArray());
    }
}

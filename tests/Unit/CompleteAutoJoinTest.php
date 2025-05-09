<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\Models\Department;

/**
 * Inline UserStaff model extending User, providing a custom alias for 'agent'.
 */
class UserStaff extends User
{
    protected $table = 'users';
    public $timestamps = false;

    /**
     * Custom join alias for the agent relationship.
     * @var array<string, string>
     */
    public $joinAliases = [
        'agent' => 'staff'
    ];

    /**
     * Summary of agent
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function agent()
    {
        return $this->hasOne(Agent::class, 'user_id');
    }
}

class CompleteAutoJoinTest extends AutoJoinTestCase
{
    /**
     * Test full auto-join pipeline with nesting, aggregates, filters, grouping, and aliasing.
     *
     * @return void
     */
    public function testCompleteAutoJoinFunctionality(): void
    {
        // Query 1: Comprehensive join with aggregates, where, groupBy, having, and orderBy.
        $query = User::query()->select([
            'name as user_name',
            'agent.id as agent_id',
            'COUNT(agent.departments.id) as dept_count',
        ])
        ->where('agent.id', '=', 1)
        ->groupBy('agent.id')
        ->having('dept_count', '>', 1) // @phpstan-ignore-line
        ->orderBy('name', 'asc');

        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('join', $sql);
        $this->assertStringContainsStringIgnoringCase('count(', $sql);
        $this->assertStringContainsStringIgnoringCase('group by', $sql);
        $this->assertStringContainsStringIgnoringCase('having', $sql);
        $this->assertStringContainsStringIgnoringCase('order by', $sql);

        $results = $query->get();
        // Make sure we have results
        $this->assertNonEmptyResults($results->toArray());
        // Get a random row from the results.
        $row = $results->random();
        $this->assertNotNull($row->user_name, 'user_name should not be null.');
        $this->assertNotNull($row->agent_id, 'agent_id should not be null.');
        $this->assertNotNull($row->dept_count, 'dept_count should not be null.');
        $this->assertIsNumeric($row->dept_count, 'dept_count should be numeric.');

        // Query 2: Custom alias from UserStaff.
        $customQuery = UserStaff::query()->select([
            'name as user_name',
            'agent.id as staff_id',
        ]);
        $customSql = $this->debugSql($customQuery);
        $this->assertStringContainsStringIgnoringCase('as "staff"', $customSql);
    }
}

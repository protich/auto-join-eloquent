<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class AgentAggregateTest extends AutoJoinTestCase
{
    /**
     * Test that auto-join functionality correctly handles an aggregate
     * on the SELECT clause. This query selects the agent's name and counts
     * the number of departments the agent has access to. The query is grouped
     * by the agent's id to avoid grouping by duplicate names.
     *
     * It asserts that the generated SQL includes "COUNT(" and "GROUP BY", and that
     * the query returns a record with a numeric department count.
     *
     * @return void
     */
    public function testAgentNameAndDepartmentCount(): void
    {
        // Build a query on the User model selecting:
        // - agent's name (aliased as agent_name),
        // - count of departments (using aggregate COUNT).
        // The query is grouped by agent.id to ensure uniqueness.
        $query = User::query()->select([
            'name as agent_name',
            'COUNT(agent__departments.id) as dept_count'
        ])->groupBy('agent.id');

        // Retrieve the generated SQL via debugSql() for inspection.
        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase(
            'COUNT(',
            $sql,
            'The query should include a COUNT aggregate function.'
        );
        $this->assertStringContainsStringIgnoringCase(
            'GROUP BY',
            $sql,
            'The query should include a GROUP BY clause.'
        );

        // Make sure we have results
        $this->assertNonEmptyResults($query->get()->toArray());

        // Verify that the returned record contains the expected fields.
        $result = $query->first();
        $this->assertNotEmpty($result, 'A record should be returned from the query.');
        $this->assertNotNull($result->agent_name, 'The agent name should be returned.');
        $this->assertNotNull($result->dept_count, 'The department count should be returned.');
        $this->assertIsNumeric($result->dept_count, 'The department count should be numeric.');
    }
}

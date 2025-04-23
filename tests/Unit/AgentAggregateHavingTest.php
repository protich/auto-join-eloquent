<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class AgentAggregateHavingTest extends AutoJoinTestCase
{
    /**
     * Test that auto-join functionality correctly handles an aggregate on the SELECT clause
     * along with a HAVING clause. This query selects the agent's name and counts the number of
     * departments the agent has access to. It groups by the agent's id and filters to return only
     * those agents with a department count greater than 1.
     *
     * It asserts that the generated SQL includes a HAVING clause and that the query returns a
     * record with a numeric department count greater than 1.
     *
     * @return void
     */
    public function testAgentHavingDeptCountGreaterThanOne(): void
    {
        // Build a query on the User model selecting:
        // - agent's name (aliased as agent_name),
        // - count of departments (using aggregate COUNT) as dept_count.
        // The query is grouped by agent.id and has a HAVING clause filtering dept_count > 1.
        $query = User::query()->select([
            'name as agent_name',
            'COUNT(agent__departments.id) as dept_count'
        ])->groupBy('agent.id')
          ->having('dept_count', '>', 1); // @phpstan-ignore-line

        // Retrieve the generated SQL via debugSql() for inspection.
        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase(
            'HAVING',
            $sql,
            'The query should include a HAVING clause.'
        );
        $this->assertStringContainsStringIgnoringCase(
            '>',
            $sql,
            'The HAVING clause should include a ">" operator.'
        );

        // Make sure we have results
        $this->assertNonEmptyResults($query->get()->toArray());

        // Check first record
        $result = $query->first();
        // Verify that a record is returned.
        $this->assertNotEmpty($result, 'A record should be returned from the query.');
        // Verify that the agent name is present.
        $this->assertNotNull($result->agent_name, 'The agent name should be returned.');
        // Verify that the department count is present and numeric.
        $this->assertNotNull($result->dept_count, 'The department count should be returned.');
        $this->assertIsNumeric($result->dept_count, 'The department count should be numeric.');
        // Verify that the department count is greater than 1.
        $this->assertGreaterThan(1, $result->dept_count, 'The department count should be greater than 1.');
    }
}

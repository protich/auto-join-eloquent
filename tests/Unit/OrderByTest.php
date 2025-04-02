<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class OrderByTest extends AutoJoinTestCase
{
    /**
     * Test that auto-join functionality correctly processes an ORDER BY clause.
     *
     * This query selects the user's name and the agent's name via auto-join,
     * and orders the results by the agent's name in ascending order.
     *
     * The test asserts that the generated SQL includes an ORDER BY clause
     * with "asc" (ignoring case) and that the query returns records.
     *
     * @return void
     */
    public function testOrderByAgentName(): void
    {
        // Build a query on the User model selecting:
        // - user's name (aliased as user_name)
        // - agent's name (aliased as agent_name)
        $query = User::query()->select([
            'name as user_name',
            'agent.id as agent_id'
        ])->orderBy('agent.id', 'asc');

        // Retrieve the generated SQL via debugSql() for inspection.
        $sql = $query->debugSql();
        $this->assertStringContainsStringIgnoringCase(
            'ORDER BY',
            $sql,
            'The query should include an ORDER BY clause.'
        );
        $this->assertStringContainsStringIgnoringCase(
            'asc',
            $sql,
            'The ORDER BY clause should specify ascending order.'
        );

        // Execute the query.
        $results = $query->get();
        $this->debugResults($results->toArray());

        // Verify that results are returned.
        $this->assertNotEmpty($results, 'The query should return one or more records.');

        // Optionally, check that the agent names are sorted in ascending order.
        $agentNames = $results->pluck('agent_name')->toArray();
        $sorted = $agentNames;
        sort($sorted);
        $this->assertEquals($sorted, $agentNames, 'The agent names should be sorted in ascending order.');
    }
}

<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class BasicJoinsTest extends AutoJoinTestCase
{
    /**
     * Test that the auto-join functionality adds the necessary JOIN clause and returns correct data.
     *
     * This test queries the User model (under protich\AutoJoinEloquent\Tests\Models\User)
     * to select the user's name and the related agent's id. It asserts that the generated SQL
     * includes a JOIN clause and that the query returns expected results.
     *
     * @return void
     */
    public function testBasicJoinQuery(): void
    {
        // Build a query that selects the user's name and the joined agent id.
        // The auto-join functionality should automatically add the JOIN for the 'agent' relationship.
        $query = User::query()->select([
            'name',
            'agent.id as agent_id'
        ]);

        // Check that the generated SQL contains a JOIN clause.
        $sql = $query->debugSql();
        $this->assertStringContainsStringIgnoringCase('JOIN', $sql, 'The query should include a JOIN clause.');

        // Execute the query.
        $result = $query->first();

        // Assuming that your common seeders have created a user with name 'Alice' and an associated agent,
        // we can assert that the auto-join returned the expected data.
        $this->assertNotEmpty($result, 'A result should be returned from the query.');
        $this->assertEquals('Alice', $result->name, 'The user name should match the seeded value.');
        $this->assertNotNull($result->agent_id, 'The agent id should be present from the auto-join.');
    }
}

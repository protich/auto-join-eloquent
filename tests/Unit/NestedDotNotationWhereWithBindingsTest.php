<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class NestedDotNotationWhereWithBindingsTest extends AutoJoinTestCase
{
    /**
     * Test nested join using dot notation with a WHERE condition and verify
     * that the binding for the condition is present.
     *
     * This query selects:
     *   - the user's name (aliased as user_name),
     *   - the agent's id (aliased as agent_id),
     *   - the department name from the agent.departments relationship (using dot notation).
     *
     * It applies a WHERE condition filtering agent.id = 1. Since toSql() produces
     * parameter placeholders, the test asserts that the SQL contains a WHERE clause with
     * a placeholder, and then checks that the query's bindings contain the value 1.
     *
     * @return void
     */
    public function testNestedJoinUsingDotNotationWithWhereAndBindings(): void
    {
        // Build a query using dot notation for nested relationships with a WHERE condition.
        $query = User::query()->select([
            'name as user_name',
            'agent.id as agent_id',
            'agent.departments.name as dept_name'
        ])->where('agent.id', '=', 1);

        // Retrieve the generated SQL and the query bindings.
        $sql = $query->debugSql();
        $bindings = $query->getBindings();

        // Assert that the SQL contains a WHERE clause with a placeholder.
        $this->assertStringContainsStringIgnoringCase(
            'where',
            $sql,
            'The query should include a WHERE clause.'
        );
        $this->assertStringContainsStringIgnoringCase(
            '?',
            $sql,
            'The query should have placeholders for bindings.'
        );

        // Assert that the bindings include the value 1.
        $this->assertContains(
            1,
            $bindings,
            'The query bindings should contain the value 1.'
        );

        // Execute the query.
        $result = $query->first();

        // Verify that the returned record contains the expected fields.
        $this->assertNotEmpty($result, 'A record should be returned from the query.');
        $this->assertNotNull($result->agent_id, 'Agent id should be present from the nested join.');
        $this->assertNotNull($result->dept_name, 'Department name should be present from the nested join.');
    }
}

<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class NestedJoinsWithInnerAgentTest extends AutoJoinTestCase
{
    /**
     * Test nested join using an inner join for the agent relationship.
     *
     * This test builds a query on the User model selecting:
     *   - the user's name,
     *   - the agent's id using an inner join (indicated by "agent|inner"),
     *   - the department name from the agent's departments, and
     *   - the manager name from each department's manager.
     *
     * It asserts that the generated SQL (via debugSql()) contains "INNER JOIN" (ignoring case)
     * for the agent join, and that the query returns a record with non-null values for agent_id,
     * dept_name, and mgr_name.
     *
     * @return void
     */
    public function testNestedJoinWithInnerAgent(): void
    {
        // Build a query using auto-join notation with an inner join for the agent relationship.
        $query = User::query()->select([
            'name as agent',
            'agent|inner.id as agent_id',  // Use inner join for the agent relationship.
            'agent__departments.name as dept_name',
            'agent__departments__manager__user.name as mgr_name'
        ]);

        // Retrieve the final SQL using debugSql() for inspection.
        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('INNER JOIN', $sql, 'The query should include an INNER JOIN for the agent relationship.');
        $this->assertStringContainsStringIgnoringCase('JOIN', $sql, 'The query should include JOIN clauses for nested relationships.');

        // Make sure we have results
        $this->assertNonEmptyResults($query->get()->toArray());

        // Verify that the returned record contains the expected fields.
        $result = $query->first();
        $this->assertNotEmpty($result, 'A record should be returned from the query.');
        $this->assertNotNull($result->agent_id, 'Agent id should be present from the inner join.');
        $this->assertNotNull($result->dept_name, 'Department name should be present from the nested join.');
        $this->assertNotNull($result->mgr_name, 'Manager name should be present from the nested join.');
    }
}

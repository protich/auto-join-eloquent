<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class DirectHavingAggregateTest extends AutoJoinTestCase
{
    /**
     * Test that auto-join functionality correctly applies a HAVING clause
     * with an aggregate function specified directly via a raw expression.
     *
     * This query builds a query on the User model that selects:
     *   - the user's name (aliased as user_name).
     * It groups by the agent's id (i.e. the join for the agent relationship)
     * and applies a raw HAVING clause filtering for records where
     * COUNT(agent.departments.id) > 0.
     *
     * The test asserts that the generated SQL includes the HAVING clause with COUNT,
     * and that the query returns at least one record.
     *
     * @return void
     */
    public function testDirectHavingAggregate(): void
    {
        $query = User::query()->select([
            'name as user_name'
        ])
        ->groupBy('agent.id')
        ->havingRaw('COUNT(agent.departments.id) > ?', [0]);

        $sql = $query->debugSql();
        $this->assertStringContainsStringIgnoringCase('having', $sql, 'SQL should include a HAVING clause.');
        $this->assertStringContainsStringIgnoringCase('count(', $sql, 'SQL should include a COUNT aggregate.');
        // Make sure we have results
        $this->assertNonEmptyResults($query->get()->toArray());
    }
}

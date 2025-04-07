<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class BaseAliasPreservationTest extends AutoJoinTestCase
{
    /**
     * Test that the base alias specified via the Eloquent query is preserved
     * after auto-join processing.
     *
     * This test uses the from() method on the Eloquent query to set an alias (e.g., "users as u"),
     * then triggers auto-join processing and asserts that the generated SQL retains that alias.
     *
     * @return void
     */
    public function testBaseAliasIsPreservedUsingFromMethod(): void
    {
        // Build a query using the User model with a manually specified alias.
        $query = User::query()->select(['id', 'name'])->from('users as u');

        // Obtain the generated SQL via the debugSql() method (provided by AutoJoinTestCase).
        $sql = $this->debugSql($query);

        // Assert that the generated SQL contains the expected alias.
        $this->assertStringContainsStringIgnoringCase(
            'users" as "u"',
            $sql,
            'The FROM clause should include the alias "users as u".'
        );

        // Execute the query and assert that a result is returned.
        $result = $query->first();
        $this->assertNotEmpty($result, 'A result should be returned from the query.');
    }
}


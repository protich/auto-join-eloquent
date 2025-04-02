<?php

namespace protich\AutoJoinEloquent\Tests;

use protich\AutoJoinEloquent\Traits\AutoJoinTrait;
use protich\AutoJoinEloquent\Tests\Models\User;
use protich\AutoJoinEloquent\Tests\Models\Agent;

class UserStaff extends User
{
    protected $table = 'users';
    public $timestamps = false;

    // Define a custom alias for the "agent" relationship.
    public $joinAliases = [
        'agent' => 'staff'
    ];

    public function agent()
    {
        return $this->hasOne(Agent::class, 'user_id');
    }
}

class CustomAliasTest extends AutoJoinTestCase
{
    public function testCustomAliasIsUsed()
    {
        // Build a query selecting a column from the "agent" relationship.
        // Note: We do not provide an explicit alias, so the auto-join system should use
        // the custom alias defined in UserStaff (i.e. "staff").
        $query = UserStaff::query()->select([
            'name',
            'agent.id as staff_id'
        ]);

        $sql = $query->debugSql();

        // Assert that the compiled SQL contains the custom alias "staff".
        // For example, we expect to see a JOIN clause that uses "AS `staff`".
        $this->assertStringContainsStringIgnoringCase('as "staff"', $sql);
    }
}

<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

class SeedDataTest extends AutoJoinTestCase
{
    /**
     * Test that seed data is loaded into the database.
     *
     * @return void
     */
    public function testSeedDataLoaded(): void
    {
        $db = $this->getDb();
        $usersCount = $db->table('users')->count();
        $this->assertGreaterThan(0, $usersCount, 'Users table should have seeded data.');

        $alice = $db->table('users')->where('name', 'Alice')->first();
        $this->assertNotEmpty($alice, "User 'Alice' should be present in the users table.");
    }
}

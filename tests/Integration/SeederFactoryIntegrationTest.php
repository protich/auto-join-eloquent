<?php

namespace protich\AutoJoinEloquent\Tests\Integration;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

/**
 * Class SeederFactoryIntegrationTest
 *
 * Verifies that the seeding of schemas works correctly when using a seeder factory.
 *
 * @package protich\AutoJoinEloquent\Tests\Integration
 */
class SeederFactoryIntegrationTest extends AutoJoinTestCase
{
    /**
     * Test that all expected tables exist in the database using the seeder factory.
     *
     * Optionally outputs the contents of each table if debugging is enabled.
     *
     * @return void
     */
    public function testSeedingWithFactory(): void
    {
        $tables = $this->seeder->getTables();
        $this->assertIsArray($tables);

        if ($this->debug) {
            echo "\n*** Default Seeder Factory: Tables Content ***\n";
            $db = $this->getDb();
            foreach ($tables as $table) {
                $results = array_map('get_object_vars', $db->table($table)->get()->all());
                $this->debugResults($results, $table);
            }
        }
    }
}

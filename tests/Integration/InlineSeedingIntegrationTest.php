<?php

namespace protich\AutoJoinEloquent\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

/**
 * Class InlineSeedingIntegrationTest
 *
 * This integration test verifies inline seeding by creating the "users" table
 * and seeding it directly within the test. It confirms that the table is created
 * and the expected data is inserted. Additionally, if the debug flag is enabled,
 * the seeded data is output via debugResults.
 *
 * @package protich\AutoJoinEloquent\Tests\Integration
 */
class InlineSeedingIntegrationTest extends AutoJoinTestCase
{
    /**
     * Override the database setup to create the "users" table inline.
     *
     * Instead of loading migrations from external files, we create the table directly
     * using the schema builder, and then seed it with inline data.
     *
     * @return void
     */
    protected function setupDatabases(): void
    {
        $schemaBuilder = $this->getSchemaBuilder();

        // Drop the "users" table if it already exists.
        if ($schemaBuilder->hasTable('users')) {
            $schemaBuilder->drop('users');
        }

        // Create the "users" table inline.
        $schemaBuilder->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });

        // Seed the "users" table with inline data.
        $this->seedData();
    }

    /**
     * Seed test data for the "users" table inline.
     *
     * Inserts sample records directly into the "users" table using the database connection.
     *
     * @return void
     */
    protected function seedData(): void
    {
        $this->db->table('users')->insert([
            [
                'name'       => 'Peter',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name'       => 'Bob',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Test that the inline schema and seed data are loaded correctly.
     *
     * Verifies that the "users" table exists, that it contains seeded data, and that
     * specific records (e.g. "Peter") are present.
     *
     * @return void
     */
    public function testInlineSchemaAndSeedData(): void
    {
        // Verify that the "users" table exists.
        $this->assertTrue($this->getSchemaBuilder()->hasTable('users'), 'users table should exist.');

        // Verify that we have records
        $this->assertNonEmptyResults('users');

        // Verify that a specific record exists.
        $peter = $this->db->table('users')->where('name', 'Peter')->first();
        $this->assertNotEmpty($peter, "Record for 'Peter' should be present.");
    }
}

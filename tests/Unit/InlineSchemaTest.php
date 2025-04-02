<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

class InlineSchemaTest extends AutoJoinTestCase
{
    /**
     * Override the database setup to create the "users" table inline.
     *
     * Instead of loading migrations from external files, we create the table directly.
     *
     * @return void
     */
    protected function setupDatabases(): void
    {
        // Use the schema builder to create the "users" table inline.
        $schemaBuilder = $this->getSchemaBuilder();
        if ($schemaBuilder->hasTable('users')) {
            $schemaBuilder->drop('users');
        }
        $schemaBuilder->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
        // Seed Data
        $this->seedData();
    }

    /**
     * Seed test data for the "users" table inline.
     *
     * This method is called by setupDatabases() and inserts records into the "users" table.
     *
     * @return void
     */
    protected function seedData(): void
    {
        // Insert inline seed data directly using the database connection.
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
     * @return void
     */
    public function testInlineSchemaAndSeedData(): void
    {
        // Verify that the "users" table exists.
        $this->assertTrue($this->getSchemaBuilder()->hasTable('users'), 'users table should exist.');

        // Verify that seed data is loaded in the "users" table.
        $count = $this->db->table('users')->count();
        $this->assertGreaterThan(0, $count, 'users table should have seeded data.');

        // Verify that a specific record exists.
        $alice = $this->db->table('users')->where('name', 'Peter')->first();
        $this->assertNotEmpty($alice, "Record for 'Inline Alice' should be present.");
    }
}

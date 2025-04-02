<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

class SchemaExistenceTest extends AutoJoinTestCase
{
    /**
     * Test that all expected tables exist in the database.
     *
     * @return void
     */
    public function testTablesExist(): void
    {
        $schemaBuilder = $this->getSchemaBuilder();
        $this->assertTrue($schemaBuilder->hasTable('users'), 'Users table should exist.');
        $this->assertTrue($schemaBuilder->hasTable('agents'), 'Agents table should exist.');
        $this->assertTrue($schemaBuilder->hasTable('departments'), 'Departments table should exist.');
        $this->assertTrue($schemaBuilder->hasTable('agent_department'), 'Agent_Department table should exist.');
    }
}

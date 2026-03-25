<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Compiler;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Test: AccessibleDepartmentsCountCompilerTest
 *
 * Verify accessibleDepartments count compiles through the EXISTS-based
 * multi-path pipeline and returns a positive integer result.
 */
class AccessibleDepartmentsCountCompilerTest extends AutoJoinTestCase
{
    /**
     * Test accessibleDepartments count compiles using EXISTS and executes.
     *
     * @return void
     */
    public function test_accessible_departments_count_compiles_and_executes(): void
    {
        $query = Agent::query()
            ->select('id')
            ->selectRaw(
                'model__accessibleDepartments__id__count as accessible_departments_count'
            )
            ->where('id', 1);

        $sql = $this->debugSql($query);
        $sqlLower = strtolower($sql);

        $this->assertStringContainsString('select count(*)', $sqlLower);
        $this->assertStringContainsString('exists', $sqlLower);
        $this->assertStringNotContainsString('union', $sqlLower);

        $row = $query->first();

        $this->assertNotNull($row);
        $this->assertIsNumeric($row->accessible_departments_count);
        $this->assertGreaterThan(0, (int) $row->accessible_departments_count);
    }
}

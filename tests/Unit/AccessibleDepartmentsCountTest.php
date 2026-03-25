<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Test: AccessibleDepartmentsCountTest
 *
 * Verify model-defined accessibleDepartments count descriptors compile
 * and execute across select, order, and having clauses using an
 * EXISTS-based strategy.
 */
class AccessibleDepartmentsCountTest extends AutoJoinTestCase
{
    /**
     * Ensure accessibleDepartments count compiles in select clauses.
     *
     * @return void
     */
    public function test_select_with_accessible_departments_count(): void
    {
        $query = Agent::query()->select([
            'id',
            'model__accessibleDepartments__id__count as accessible_departments_count',
        ]);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('select count(*)', $sql);
        $this->assertStringContainsStringIgnoringCase('exists', $sql);
        $this->assertStringNotContainsStringIgnoringCase('union', $sql);
        $this->assertStringContainsStringIgnoringCase(
            'as "accessible_departments_count"',
            $sql
        );

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure accessibleDepartments count compiles in order by clauses.
     *
     * @return void
     */
    public function test_order_by_with_accessible_departments_count(): void
    {
        $query = Agent::query()
            ->select([
                'id',
                'model__accessibleDepartments__id__count as accessible_departments_count',
            ])
            ->orderBy('model__accessibleDepartments__id__count', 'desc');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('order by', $sql);
        $this->assertStringContainsStringIgnoringCase('accessible_departments_count', $sql);
        $this->assertStringNotContainsStringIgnoringCase('union', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure accessibleDepartments count compiles in having clauses.
     *
     * @return void
     */
    public function test_having_with_accessible_departments_count(): void
    {
        $query = Agent::query()
            ->select([
                'id',
                'model__accessibleDepartments__id__count as accessible_departments_count',
            ])
            ->groupBy('id')
            ->having('model__accessibleDepartments__id__count', '>', 0); // @phpstan-ignore-line

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('having', $sql);
        $this->assertStringContainsStringIgnoringCase('exists', $sql);
        $this->assertStringNotContainsStringIgnoringCase('union', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }
}

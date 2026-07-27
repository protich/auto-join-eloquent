<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Compiler;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Verify that complex relationship constraints survive count subquery
 * compilation as ordered bindings on the outer query.
 */
class ComplexRelationshipSubqueryTest extends AutoJoinTestCase
{
    /**
     * Ensure bindings from every EXISTS predicate reach the outer select.
     *
     * @return void
     */
    public function test_exists_count_preserves_complex_relation_bindings(): void
    {
        $query = Agent::query()
            ->select([
                'id',
                'model__qualifiedDepartmentCount as qualified_count',
            ])
            ->where('id', 1);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('exists', $sql);
        $this->assertStringNotContainsStringIgnoringCase('union', $sql);
        $this->assertSame(3, substr_count($sql, '?'));
        $this->assertSame(
            ['Support', 'Support', 1],
            $query->getBindings()
        );

        $row = $query->first();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->qualified_count);
    }

    /**
     * Ensure UNION fallback subqueries also preserve relationship bindings.
     *
     * @return void
     */
    public function test_union_count_preserves_complex_relation_bindings(): void
    {
        $query = Agent::query()
            ->select([
                'id',
                'model__mixedComplexCount as mixed_count',
            ])
            ->where('id', 1);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('union', $sql);
        $this->assertSame(2, substr_count($sql, '?'));
        $this->assertSame(
            ['2025-01-01', 1],
            $query->getBindings()
        );

        $row = $query->first();

        $this->assertNotNull($row);
        $this->assertGreaterThan(0, (int) $row->mixed_count);
    }

    /**
     * Ensure generated bindings precede an existing HAVING comparison value.
     *
     * @return void
     */
    public function test_having_count_preserves_placeholder_binding_order(): void
    {
        $query = Agent::query()
            ->select('id')
            ->groupBy('id')
            ->having('model__qualifiedDepartmentCount', '>', 0); // @phpstan-ignore-line

        $sql = $this->debugSql($query);

        $this->assertSame(3, substr_count($sql, '?'));
        $this->assertSame(
            ['Support', 'Support', 0],
            $query->getBindings()
        );
        $this->assertNotEmpty($query->get());
    }

    /**
     * Ensure multiple bound count expressions interleave with their HAVING
     * comparison values in SQL order.
     *
     * @return void
     */
    public function test_multiple_having_counts_preserve_binding_order(): void
    {
        $query = Agent::query()
            ->select('id')
            ->groupBy('id')
            ->having('model__qualifiedDepartmentCount', '>', 0) // @phpstan-ignore-line
            ->having('model__mixedComplexCount', '>', 0); // @phpstan-ignore-line

        $sql = $this->debugSql($query);

        $this->assertSame(5, substr_count($sql, '?'));
        $this->assertSame(
            ['Support', 'Support', 0, '2025-01-01', 0],
            $query->getBindings()
        );
        $this->assertNotEmpty($query->get());
    }

    /**
     * Ensure WHERE comparison values follow bindings inside their subquery.
     *
     * @return void
     */
    public function test_where_count_preserves_placeholder_binding_order(): void
    {
        $query = Agent::query()
            ->select('id')
            ->where('model__qualifiedDepartmentCount', '>', 0);

        $sql = $this->debugSql($query);

        $this->assertSame(3, substr_count($sql, '?'));
        $this->assertSame(
            ['Support', 'Support', 0],
            $query->getBindings()
        );
        $this->assertNotEmpty($query->get());
    }
}

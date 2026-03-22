<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

/**
 * Test: SuffixCountsTest
 *
 * Verify suffix-based count aggregates compile and execute correctly
 * across select, order, and having clauses.
 */
class SuffixCountsTest extends AutoJoinTestCase
{
    /**
     * Ensure suffix-based count aggregates compile in select clauses.
     *
     * @return void
     */
    public function test_select_with_counts_suffix(): void
    {
        $query = User::query()
            ->select([
                'name',
                'agent__departments.id__count as dept_count',
            ])
            ->groupBy('agent.id');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);
        $this->assertStringContainsStringIgnoringCase('as "dept_count"', $sql);
        $this->assertStringContainsStringIgnoringCase('JOIN', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure suffix-based count aggregates compile in order by clauses.
     *
     * @return void
     */
    public function test_order_by_with_counts_suffix(): void
    {
        $query = User::query()
            ->select([
                'name',
                'agent__departments.id__count as dept_count',
            ])
            ->groupBy('agent.id')
            ->orderBy('agent__departments.id__count', 'desc');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);
        $this->assertStringContainsStringIgnoringCase('ORDER BY', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure suffix-based count aggregates compile in having clauses.
     *
     * @return void
     */
    public function test_having_with_counts_suffix(): void
    {
        $query = User::query()
            ->select([
                'name',
                'agent__departments.id__count as dept_count',
            ])
            ->groupBy('agent.id')
            ->having('agent__departments.id__count', '>', 0); // @phpstan-ignore-line

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('HAVING', $sql);
        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }
}

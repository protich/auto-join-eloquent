<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class SuffixCountsTest extends AutoJoinTestCase
{
    public function test_select_with_counts_suffix(): void
    {
        $query = User::query()
            ->select([
                'name',
                'agent__departments.id__counts as dept_count',
            ])
            ->groupBy('agent.id');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);
        $this->assertStringContainsStringIgnoringCase('as "dept_count"', $sql);
        $this->assertStringContainsStringIgnoringCase('JOIN', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    public function test_order_by_with_counts_suffix(): void
    {
        $query = User::query()
            ->select([
                'name',
                'agent__departments.id__counts as dept_count',
            ])
            ->groupBy('agent.id')
            ->orderBy('agent__departments.id__counts', 'desc');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);
        $this->assertStringContainsStringIgnoringCase('ORDER BY', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    public function test_having_with_counts_suffix(): void
    {
        $query = User::query()
            ->select([
                'name',
                'agent__departments.id__counts as dept_count',
            ])
            ->groupBy('agent.id')
            ->having('agent__departments.id__counts', '>', 0);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('HAVING', $sql);
        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }
}

<?php

namespace Protich\AutoJoinEloquent\Tests\Unit;

use Protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use Protich\AutoJoinEloquent\Tests\Models\User;
use Illuminate\Support\Facades\DB;

class CoalesceColumnTest extends AutoJoinTestCase
{
    public function test_select_with_coalesce_expression(): void
    {
        $query = User::query()->select([
            'name',
            "COALESCE(phone, email) as contact_info"
        ]);

        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('COALESCE', $sql);
        $this->assertStringContainsStringIgnoringCase('as "contact_info"', $sql);

        $result = $query->first();
        $this->assertNotNull($result?->contact_info);
    }

    public function test_where_with_coalesce(): void
    {
        $query = User::query()
            ->whereRaw("COALESCE(phone, email) IS NOT NULL")
            ->select(['id', 'name']);

        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('COALESCE', $sql);

        $result = $query->first();
        $this->assertNotNull($result);
    }

    public function test_order_by_with_coalesce(): void
    {
        $query = User::query()
            ->orderByRaw("COALESCE(phone, email) ASC")
            ->select(['id', 'name']);

        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('ORDER BY', $sql);
        $this->assertStringContainsStringIgnoringCase('COALESCE', $sql);

        $result = $query->first();
        $this->assertNotNull($result);
    }

    public function test_having_with_coalesce(): void
    {
        $query = User::query()
            ->select([
                'id',
                'name',
                DB::raw("COALESCE(phone, email) as contact_info")
            ])
            ->groupBy('id')
            ->havingRaw("COALESCE(phone, email) IS NOT NULL");

        $sql = $this->debugSql($query);
        $this->assertStringContainsStringIgnoringCase('HAVING', $sql);
        $this->assertStringContainsStringIgnoringCase('COALESCE', $sql);

        $result = $query->first();
        $this->assertNotNull($result);
    }
}

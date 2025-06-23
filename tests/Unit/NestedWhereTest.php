<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class NestedWhereTest extends AutoJoinTestCase
{
    public function test_nested_where_with_structured_and_raw_conditions(): void
    {
        $query = User::query()
            ->select(['name'])
            ->where(function ($q) {
                $q->where('agent__departments__manager__user.id', '=', 1)
                  ->orWhere('agent__departments.name', 'like', 'S%');
            })
            ->where('agent__departments__manager__user.id', '>', 0);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('JOIN', $sql);
        $this->assertStringContainsStringIgnoringCase('WHERE', $sql);
        $this->assertStringContainsStringIgnoringCase('agent_department', $sql);
        $this->assertStringContainsStringIgnoringCase('manager', $sql);
        $this->assertStringContainsStringIgnoringCase('user', $sql);
        $this->assertStringContainsStringIgnoringCase('id', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }
}

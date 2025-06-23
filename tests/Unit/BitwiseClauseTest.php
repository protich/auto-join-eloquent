<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class BitwiseClauseTest extends AutoJoinTestCase
{
    public function test_bitwise_expression_with_relationship_column(): void
    {
        $query = User::query()
            ->select(['name'])
            ->whereRaw('agent.flags & ? = 0', [1]);

        $sql = $this->debugSql($query);

        $this->assertStringContainsString('agent', $sql);
        $this->assertStringContainsString('&', $sql);
        $this->assertStringContainsString('= 0', $sql);
    }
}

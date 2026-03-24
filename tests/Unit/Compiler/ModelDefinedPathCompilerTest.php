<?php

namespace protich\AutoJoinEloquent\Tests\Compiler;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Test: ModelDefinedPathCompilerTest
 *
 * Verify model-defined paths compile through the normal query pipeline.
 */
class ModelDefinedPathCompilerTest extends AutoJoinTestCase
{
    /**
     * Test model-defined status path compiles like flags in select.
     *
     * @return void
     */
    public function test_model_status_compiles_like_flags_in_select(): void
    {
        $expected = Agent::query()
            ->select('flags')
            ->toSql();

        $query = Agent::query()
            ->select('model__status');

        $actual = $this->debugSql($query);

        $this->assertSame($expected, $actual);
    }

    /**
     * Test model-defined status path compiles like flags in where.
     *
     * @return void
     */
    public function test_model_status_compiles_like_flags_in_where(): void
    {
        $expected = Agent::query()
            ->where('flags', 1)
            ->toSql();

        $query = Agent::query()
            ->where('model__status', 1);

        $actual = $this->debugSql($query);

        $this->assertSame($expected, $actual);
    }

    /**
     * Test model-defined status path compiles like flags in order by.
     *
     * @return void
     */
    public function test_model_status_compiles_like_flags_in_order_by(): void
    {
        $expected = Agent::query()
            ->orderBy('flags')
            ->toSql();

        $query = Agent::query()
            ->orderBy('model__status');

        $actual = $this->debugSql($query);

        $this->assertSame($expected, $actual);
    }

    /**
     * Test model-defined status path compiles like flags in bitwise raw SQL.
     *
     * @return void
     */
    public function test_model_status_compiles_like_flags_in_bitwise_raw_sql(): void
    {
        $expected = Agent::query()
            ->whereRaw('flags & ? = 0', [1])
            ->toSql();

        $query = Agent::query()
            ->whereRaw('model__status & ? = 0', [1]);

        $actual = $this->debugSql($query);

        $this->assertSame($expected, $actual);
    }
}

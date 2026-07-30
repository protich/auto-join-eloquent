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

    /**
     * Test an unmarked path falls back to the model in SELECT.
     */
    public function test_unmarked_status_compiles_in_select(): void
    {
        $expected = Agent::query()->select('flags')->toSql();
        $actual = Agent::query()->select('status')->toSql();

        $this->assertSame($expected, $actual);
    }

    /**
     * Test an unmarked path falls back to the model in WHERE.
     */
    public function test_unmarked_status_compiles_in_where(): void
    {
        $expected = Agent::query()->where('flags', 1)->toSql();
        $actual = Agent::query()->where('status', 1)->toSql();

        $this->assertSame($expected, $actual);
    }

    /**
     * Test an unmarked path falls back to the model in ORDER BY.
     */
    public function test_unmarked_status_compiles_in_order_by(): void
    {
        $expected = Agent::query()->orderBy('flags')->toSql();
        $actual = Agent::query()->orderBy('status')->toSql();

        $this->assertSame($expected, $actual);
    }

    /**
     * Test bitwise raw SQL resolves an unmarked model path.
     */
    public function test_unmarked_status_compiles_in_bitwise_raw_sql(): void
    {
        $expected = Agent::query()
            ->whereRaw('flags & ? = 0', [1])
            ->toSql();
        $actual = Agent::query()
            ->whereRaw('status & ? = 0', [1])
            ->toSql();

        $this->assertSame($expected, $actual);
    }

    /**
     * Test COALESCE resolves its model-described arguments.
     */
    public function test_unmarked_status_compiles_in_coalesce(): void
    {
        $sql = Agent::query()
            ->select('COALESCE(status, flags) as current_status')
            ->toSql();

        $this->assertStringContainsString(
            'COALESCE("A"."flags", "A"."flags") as "current_status"',
            $sql
        );
    }

    /**
     * Test descriptors can resolve through another model expression.
     */
    public function test_descriptors_can_reference_other_model_expressions(): void
    {
        $expected = Agent::query()->select('flags')->toSql();
        $actual = Agent::query()->select('statusAlias')->toSql();

        $this->assertSame($expected, $actual);
    }

    /**
     * Test recursive model descriptions fail with an actionable error.
     */
    public function test_circular_model_expression_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Circular model-defined auto-join path [circularExpression]'
        );

        Agent::query()->select('circularExpression')->toSql();
    }
}

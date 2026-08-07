<?php

namespace protich\AutoJoinEloquent\Tests\Compiler;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\Models\Group;

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
     * Test every scalar aggregate descriptor compiles through relationship
     * resolution using the function represented by its factory.
     *
     * @return void
     */
    public function test_scalar_aggregate_descriptors_compile(): void
    {
        $paths = [
            'departmentIdSum' => 'SUM',
            'departmentIdAverage' => 'AVG',
            'departmentIdMinimum' => 'MIN',
            'departmentIdMaximum' => 'MAX',
        ];

        foreach ($paths as $path => $function) {
            $sql = Agent::query()
                ->select("{$path} as aggregate_value")
                ->toSql();

            $this->assertStringContainsString("{$function}(", $sql);
            $this->assertStringContainsString(
                'as "aggregate_value"',
                $sql
            );
        }
    }

    /**
     * Test COALESCE descriptors resolve each relationship path in order.
     *
     * @return void
     */
    public function test_coalesce_descriptor_compiles_and_executes(): void
    {
        $agent = Agent::query()
            ->where('user_id', 1)
            ->select('preferredContact as contact')
            ->firstOrFail();

        $this->assertSame('peter@osticket.com', $agent->contact);
        $this->assertStringContainsString(
            'COALESCE(',
            Agent::query()
                ->select('preferredContact as contact')
                ->toSql()
        );
    }

    /**
     * Test concatenation descriptors skip null paths and retain separators.
     *
     * @return void
     */
    public function test_concat_descriptor_compiles_and_executes(): void
    {
        $agent = Agent::query()
            ->where('user_id', 1)
            ->select('displayLabel as label')
            ->firstOrFail();

        $this->assertSame(
            'Peter Rotich / Auto Join Package Developer',
            $agent->label
        );
        $this->assertStringContainsString(
            'CONCAT_WS(\' / \',',
            Agent::query()
                ->select('displayLabel as label')
                ->toSql()
        );

        $contact = Agent::query()
            ->where('user_id', 1)
            ->select('contactLabel as label')
            ->firstOrFail();

        $this->assertSame('peter@osticket.com', $contact->label);
    }

    /**
     * Test scalar composite descriptors compile in WHERE and ORDER BY.
     *
     * @return void
     */
    public function test_composite_descriptors_compile_across_clauses(): void
    {
        $query = Agent::query()
            ->select('displayLabel as label')
            ->where(
                'preferredContact',
                'peter@osticket.com'
            )
            ->orderBy('displayLabel');

        $sql = $query->toSql();

        $this->assertStringContainsString('COALESCE(', $sql);
        $this->assertStringContainsString('CONCAT_WS(', $sql);
        $this->assertStringContainsString(
            'order by "label" asc',
            $sql
        );
        $this->assertSame(
            'Peter Rotich / Auto Join Package Developer',
            $query->firstOrFail()->label
        );
    }

    /**
     * Test aggregate descriptor types obey the normal WHERE restriction.
     *
     * @return void
     */
    public function test_aggregate_descriptors_are_rejected_in_where(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Aggregate expressions are not allowed in WHERE clauses.'
        );

        Agent::query()->where('departmentIdSum', '>', 0)->toSql();
    }

    /**
     * Test composite descriptors rebase through a downstream model hop.
     *
     * @return void
     */
    public function test_composite_descriptor_paths_are_rebased(): void
    {
        $sql = \protich\AutoJoinEloquent\Tests\Models\User::query()
            ->select('agent__displayLabel as agent_label')
            ->toSql();

        $this->assertStringContainsString('CONCAT_WS(', $sql);
        $this->assertStringContainsString('as "agent_label"', $sql);
        $this->assertSame(2, substr_count($sql, 'join'));
    }

    /**
     * Test concatenation qualifies children through a nullable self-parent.
     *
     * @return void
     */
    public function test_concat_descriptor_supports_nullable_self_parent(): void
    {
        $labels = Group::query()
            ->select(['name', 'label as qualified_label'])
            ->orderBy('label')
            ->pluck('qualified_label', 'name');

        $this->assertSame('Support', $labels['Support']);
        $this->assertSame(
            'Support / Escalations',
            $labels['Escalations']
        );

        $sql = Group::query()
            ->select('label as qualified_label')
            ->toSql();

        $this->assertStringContainsString('CONCAT_WS(', $sql);
        $this->assertSame(1, substr_count($sql, 'join "ost_groups"'));
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

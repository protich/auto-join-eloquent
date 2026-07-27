<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\Models\Group;

/**
 * Verify normal and model-described complex relationship join behavior.
 */
class ComplexRelationshipTest extends AutoJoinTestCase
{
    /**
     * Ensure normal relationships are resolved through the model hook.
     *
     * @return void
     */
    public function test_normal_relation_is_described_by_the_model(): void
    {
        Agent::$autoJoinRelationDescriptions = [];
        $query = Agent::query()->select('user.name');

        $sql = $this->debugSql($query);

        $this->assertStringContainsString(
            'left join "ost_users" as "B" on "A"."user_id" = "B"."id"',
            $sql
        );
        $this->assertSame([], $query->getBindings());
        $this->assertSame([[
            'name' => 'user',
            'path' => 'user.name',
        ]], Agent::$autoJoinRelationDescriptions);
    }

    /**
     * Ensure unresolved relationship calls fail with model and path context.
     *
     * @return void
     */
    public function test_missing_relation_fails_with_context(): void
    {
        $query = Agent::query()->select('missingRelation.name');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Unable to resolve auto-join relationship [missingRelation]'
        );

        $query->toSql();
    }

    /**
     * Ensure a related-table constraint is described and safely bound.
     *
     * @return void
     */
    public function test_complex_related_constraint_is_model_described_and_bound(): void
    {
        $query = Agent::query()->select('namedUser.name');

        $sql = $this->debugSql($query);

        $this->assertStringContainsString(
            'left join "ost_users" as "B" on "A"."user_id" = "B"."id" and "B"."name" = ?',
            $sql
        );
        $this->assertSame(['Alice'], $query->getBindings());
    }

    /**
     * Ensure the resolver uses the description returned by the model hook.
     *
     * @return void
     */
    public function test_model_returned_description_is_authoritative(): void
    {
        $query = Agent::query()->select('returnedNamedUser.name');

        $sql = $this->debugSql($query);

        $this->assertStringContainsString(
            'left join "ost_users" as "B" on "A"."user_id" = "B"."id" and "B"."name" = ?',
            $sql
        );
        $this->assertSame(['Alice'], $query->getBindings());
    }

    /**
     * Ensure a pivot-table constraint is described on the pivot join.
     *
     * @return void
     */
    public function test_complex_pivot_constraint_is_model_described_and_bound(): void
    {
        $query = Agent::query()->select('assignedDepartments.name');

        $sql = $this->debugSql($query);

        $this->assertStringContainsString(
            'left join "ost_agent_department" as "B" on "A"."id" = "B"."agent_id" and "B"."assigned_at" >= ?',
            $sql
        );
        $this->assertSame(['2025-01-01'], $query->getBindings());
    }

    /**
     * Ensure constraints may explicitly target the owning table.
     *
     * @return void
     */
    public function test_complex_parent_constraint_is_compiled_into_join(): void
    {
        $query = Agent::query()->select('flaggedUser.name');

        $sql = $this->debugSql($query);

        $this->assertStringContainsString(
            'left join "ost_users" as "B" on "A"."user_id" = "B"."id" and "A"."flags" = ?',
            $sql
        );
        $this->assertSame([1], $query->getBindings());
    }

    /**
     * Ensure related-table null predicates preserve their exact semantics.
     *
     * @return void
     */
    public function test_related_null_constraints_compile_both_variants(): void
    {
        $withoutPhone = Agent::query()->select('userWithoutPhone.name');
        $withPhone = Agent::query()->select('userWithPhone.name');

        $this->assertStringContainsString(
            'and "B"."phone" is null',
            $this->debugSql($withoutPhone)
        );
        $this->assertStringContainsString(
            'and "B"."phone" is not null',
            $this->debugSql($withPhone)
        );
        $this->assertSame([], $withoutPhone->getBindings());
        $this->assertSame([], $withPhone->getBindings());
    }

    /**
     * Ensure pivot-table null predicates are attached to the pivot join.
     *
     * @return void
     */
    public function test_pivot_null_constraint_is_compiled_into_pivot_join(): void
    {
        $query = Agent::query()->select('pendingDepartments.name');

        $this->assertStringContainsString(
            'left join "ost_agent_department" as "B" on "A"."id" = "B"."agent_id" and "B"."assigned_at" is null',
            $this->debugSql($query)
        );
        $this->assertSame([], $query->getBindings());
    }

    /**
     * Ensure pivot constraints are rejected for non-pivot relationships.
     *
     * @return void
     */
    public function test_non_pivot_relation_rejects_pivot_description(): void
    {
        $query = Agent::query()->select('invalidPivotUser.name');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'cannot use a pivot constraint because it is not a belongsToMany relationship'
        );

        $query->toSql();
    }

    /**
     * Ensure nested hooks receive the complete path that caused the join.
     *
     * @return void
     */
    public function test_nested_complex_relation_receives_complete_path(): void
    {
        Agent::$autoJoinRelationDescriptions = [];

        $query = \protich\AutoJoinEloquent\Tests\Models\User::query()
            ->select('agent.namedUser.name');

        $this->debugSql($query);

        $this->assertContains([
            'name' => 'namedUser',
            'path' => 'agent__namedUser.name',
        ], Agent::$autoJoinRelationDescriptions);
    }

    /**
     * Ensure reusing a path does not duplicate its join or model description.
     *
     * @return void
     */
    public function test_reused_complex_relation_is_described_once(): void
    {
        Agent::$autoJoinRelationDescriptions = [];

        $query = Agent::query()
            ->select('namedUser.name')
            ->where('namedUser.name', 'Alice')
            ->orderBy('namedUser.name');

        $sql = $this->debugSql($query);

        $this->assertSame(1, substr_count(
            $sql,
            'join "ost_users" as "B"'
        ));
        $this->assertSame([[
            'name' => 'namedUser',
            'path' => 'namedUser.name',
        ]], Agent::$autoJoinRelationDescriptions);
    }

    /**
     * Ensure constrained left and inner joins retain their normal row semantics.
     *
     * @return void
     */
    public function test_join_type_preserves_expected_query_results(): void
    {
        $this->db->table('users')->where('id', 1)->update(['name' => 'Alice']);
        $this->db->table('users')->where('id', '!=', 1)->update(['name' => 'Bob']);

        $agentCount = $this->db->table('agents')->count();

        $leftRows = Agent::query()
            ->select(['id', 'namedUser.name as matched_name'])
            ->get();

        $innerRows = Agent::query()
            ->select(['id', 'namedUser|inner.name as matched_name'])
            ->get();

        $this->assertCount($agentCount, $leftRows);
        $this->assertCount(1, $innerRows);
        $this->assertSame('Alice', $innerRows->first()->matched_name);
        $this->assertSame(
            $agentCount - 1,
            $leftRows->whereNull('matched_name')->count()
        );
    }

    /**
     * Ensure complex relationships cannot silently lose their constraints.
     *
     * @return void
     */
    public function test_undescribed_complex_relation_fails_clearly(): void
    {
        $query = Group::query()->select('namedChildren.name');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'did not describe constraints for complex auto-join relation [namedChildren]'
        );

        $query->toSql();
    }
}

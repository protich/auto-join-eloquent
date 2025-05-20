<?php

namespace protich\AutoJoinEloquent\Tests\Unit;

use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\User;

class ColumnNormalizationTest extends AutoJoinTestCase
{
    public function test_dot_based_column_is_preserved(): void
    {
        $query = User::query()
            ->select(['name as Name', 'agent__departments|inner.name as
            Department', 'agent__departments__manager__user.name as
            mgr_name']);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('JOIN', $sql);
        $this->assertStringContainsStringIgnoringCase('."name"', $sql);
        $this->assertStringContainsStringIgnoringCase('as "mgr_name"', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    public function test_column_without_dot_infers_primary_key_field(): void
    {
        $query = User::query()
            ->select(['name as Name', 'agent__departments|inner.name as
            Department', 'agent__departments__manager__user']);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('JOIN', $sql);
        $this->assertStringContainsStringIgnoringCase('."id"', $sql);
        $this->assertStringContainsStringIgnoringCase('agent__departments__manager__user', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    public function test_plain_field_expression_is_not_joined(): void
    {
        $query = User::query()
            ->select(['id', 'status']);

        $sql = $this->debugSql($query);

        $this->assertStringNotContainsStringIgnoringCase('JOIN', $sql);
        $this->assertStringContainsStringIgnoringCase('status', $sql);
    }

    public function test_invalid_final_relation_becomes_field(): void
    {
        $query = User::query()
            ->select(['agent__nonexistent as maybe_field']);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('agent', $sql);
        $this->assertStringNotContainsStringIgnoringCase('nonexistent.', $sql);
        $this->assertStringContainsStringIgnoringCase('as "maybe_field"', $sql);
    }

    public function test_suffix_based_aggregate_counts_related_departments(): void
    {
        $query = User::query()
            ->select(['name', 'agent__departments__count as dept_count'])
            ->groupBy('agent.id');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);
        $this->assertStringContainsStringIgnoringCase('as "dept_count"', $sql);
        $this->assertStringContainsStringIgnoringCase('departments', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure suffix-based aggregate can be used in a HAVING clause.
     */
    public function test_suffix_aggregate_in_having_clause(): void
    {
        $query = User::query()
            ->select(['name', 'agent__departments__count as dept_count'])
            ->groupBy('agent.id')
            ->having('agent__departments__count', '>', 0)// @phpstan-ignore-line
            ->orderBy('name', 'asc');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('HAVING', $sql);
        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure suffix-based aggregate can be used in ORDER BY.
     */
    public function test_suffix_aggregate_in_order_by_clause(): void
    {
        $query = User::query()
            ->select(['name', 'agent__departments__count as dept_count'])
            ->groupBy('agent.id')
            ->orderBy('agent__departments__count', 'desc');

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('ORDER BY', $sql);
        $this->assertStringContainsStringIgnoringCase('COUNT', $sql);

        $this->assertNonEmptyResults($query->get()->toArray());
    }

    /**
     * Ensure explicit alias suppresses auto-generated alias.
     */
    public function test_explicit_alias_overrides_auto_aliasing(): void
    {
        $query = User::query()
            ->select(['agent__departments__manager__user as manager_id']);

        $sql = $this->debugSql($query);

        $this->assertStringContainsStringIgnoringCase('JOIN', $sql);
        $this->assertStringContainsStringIgnoringCase('as "manager_id"', $sql);
        $this->assertStringNotContainsStringIgnoringCase('agent__departments__manager__user', $sql);
    }
}

<?php

declare(strict_types=1);

namespace protich\AutoJoinEloquent\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/** Verify aggregate fallbacks compile and execute through AutoJoin. */
final class CoalesceAggregateTest extends AutoJoinTestCase
{
    /**
     * Nested aggregate arguments retain root identities, zero counts, and explicit aliases.
     *
     * @param string $expression AutoJoin projection or model-described path.
     * @return void
     */
    #[DataProvider('countExpressions')]
    public function test_count_arguments_execute_per_root(string $expression): void
    {
        $this->prepareCounts();
        $query = Agent::query()->select(['id', $expression.' as amount'])
            ->whereIn('id', [1, 2, 3])->groupBy('id')->orderBy('id');

        $sql = $query->toSql();
        $this->assertSame($sql, $query->toSql());
        $this->assertSame([1 => 2, 2 => 1, 3 => 0], $query->get()->pluck('amount', 'id')->all());
    }

    /**
     * Cover SQL functions, suffix shorthand, fallback position, and nested COALESCE.
     *
     * @return list<array{string}>
     */
    public static function countExpressions(): array
    {
        return [
            ['departmentCountFallback'],
            ['COALESCE(departments__id__count, flags)'],
            ['COALESCE(COUNT(departments__id), flags)'],
            ['COALESCE(user__phone, departments__id__count)'],
            ['COALESCE(user__phone, COUNT(departments__id))'],
            ['COALESCE(COALESCE(user__phone, COUNT(departments__id)), flags)'],
            ['COALESCE(user__phone, COALESCE(departments__id__count, flags))'],
        ];
    }

    /**
     * MAX with no joined row must use the fallback rather than a quoted field fragment.
     *
     * @return void
     */
    public function test_null_aggregate_uses_fallback(): void
    {
        $this->prepareCounts();
        $result = Agent::query()->select(['id', 'COALESCE(MAX(departments__id), flags) as amount'])
            ->where('id', 3)->groupBy('id')->sole();

        $this->assertSame(1, $result->amount);
    }

    /**
     * Preserve HAVING bindings and ORDER BY suffixes after balanced expression parsing.
     *
     * @return void
     */
    public function test_raw_having_and_order_keep_trailing_sql_and_bindings(): void
    {
        $this->prepareCounts();
        $expression = 'COALESCE(COUNT(departments__id), flags)';
        $query = Agent::query()->select(['id', $expression.' as amount'])
            ->whereIn('id', [1, 2, 3])->groupBy('id')
            ->havingRaw($expression.' > ?', [0])->orderByRaw($expression.' DESC');

        $this->assertSame([1 => 2, 2 => 1], $query->get()->pluck('amount', 'id')->all());
        $this->assertSame([1, 2, 3, 0], $query->getBindings());
    }

    /**
     * Retain model-described paths and generated relationship bindings inside fallbacks.
     *
     * @param string $expression Model-described aggregate or composite expression.
     * @return void
     */
    #[DataProvider('boundExpressions')]
    public function test_described_aggregate_keeps_its_bindings(string $expression): void
    {
        $this->getDb()->table('users')->where('id', 1)->update(['name' => 'Alice', 'phone' => null]);
        $query = Agent::query()->select(['id', $expression.' as amount'])
            ->where('id', 1)->groupBy('id');
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertContains('Alice', $bindings);
        $this->assertSame($sql, $query->toSql());
        $this->assertSame($bindings, $query->getBindings());
        $this->assertSame(1, $query->sole()->amount);
    }

    /**
     * Cover both a described argument and the source context of a composite descriptor.
     *
     * @return list<array{string}>
     */
    public static function boundExpressions(): array
    {
        return [
            ['COALESCE(userNamed__Alice__count, flags)'],
            ['userNamed__Alice__countFallback'],
        ];
    }

    /**
     * Give three roots distinct related counts and a nullable fallback field.
     *
     * @return void
     */
    private function prepareCounts(): void
    {
        $this->getDb()->table('agent_department')->delete();
        $this->getDb()->table('agent_department')->insert([
            ['agent_id' => 1, 'department_id' => 1],
            ['agent_id' => 1, 'department_id' => 2],
            ['agent_id' => 2, 'department_id' => 1],
        ]);
        $this->getDb()->table('users')->update(['phone' => null]);
        $this->getDb()->table('agents')->update(['flags' => 1]);
    }
}

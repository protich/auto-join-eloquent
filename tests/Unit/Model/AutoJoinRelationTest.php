<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Model;

use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use protich\AutoJoinEloquent\Model\AutoJoinRelation;

/**
 * Verify the public fluent API and normalized state of AutoJoinRelation.
 */
class AutoJoinRelationTest extends TestCase
{
    /**
     * Ensure the original relation is available and constraints start empty.
     *
     * @return void
     */
    public function test_exposes_the_original_relation_and_starts_empty(): void
    {
        $eloquentRelation = $this->createMock(Relation::class);
        $relation = new AutoJoinRelation($eloquentRelation);

        $this->assertSame($eloquentRelation, $relation->relation());
        $this->assertFalse($relation->hasConstraints());
        $this->assertSame([], $relation->constraints());
    }

    /**
     * Ensure value constraints normalize their target and argument forms.
     *
     * @return void
     */
    public function test_normalizes_value_constraints(): void
    {
        $relation = $this->makeRelation();

        $relatedResult = $relation->whereRelated('status', 'active');
        $parentResult = $relation->whereParent('flags', '&', 1);
        $pivotResult = $relation->wherePivot(
            'assigned_at',
            '>=',
            '2025-01-01'
        );

        $this->assertSame($relation, $relatedResult);
        $this->assertSame($relation, $parentResult);
        $this->assertSame($relation, $pivotResult);
        $this->assertTrue($relation->hasConstraints());
        $this->assertSame([
            [
                'target' => 'related',
                'type' => 'basic',
                'column' => 'status',
                'operator' => '=',
                'value' => 'active',
            ],
            [
                'target' => 'parent',
                'type' => 'basic',
                'column' => 'flags',
                'operator' => '&',
                'value' => 1,
            ],
            [
                'target' => 'pivot',
                'type' => 'basic',
                'column' => 'assigned_at',
                'operator' => '>=',
                'value' => '2025-01-01',
            ],
        ], $relation->constraints());
    }

    /**
     * Ensure null constraints support every target and NOT NULL semantics.
     *
     * @return void
     */
    public function test_normalizes_null_constraints(): void
    {
        $relation = $this->makeRelation();

        $relation
            ->whereRelatedNull('deleted_at')
            ->whereParentNull('disabled_at', not: true)
            ->wherePivotNull('expired_at');

        $this->assertSame([
            [
                'target' => 'related',
                'type' => 'null',
                'column' => 'deleted_at',
                'not' => false,
            ],
            [
                'target' => 'parent',
                'type' => 'null',
                'column' => 'disabled_at',
                'not' => true,
            ],
            [
                'target' => 'pivot',
                'type' => 'null',
                'column' => 'expired_at',
                'not' => false,
            ],
        ], $relation->constraints());
    }

    /**
     * Ensure columns are normalized before being stored.
     *
     * @return void
     */
    public function test_trims_column_and_operator_values(): void
    {
        $relation = $this->makeRelation();

        $relation->whereRelated(' status ', ' >= ', 10);

        $this->assertSame([
            [
                'target' => 'related',
                'type' => 'basic',
                'column' => 'status',
                'operator' => '>=',
                'value' => 10,
            ],
        ], $relation->constraints());
    }

    /**
     * Ensure an empty column name is rejected.
     *
     * @return void
     */
    public function test_rejects_an_empty_column(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Auto-join relation columns must be non-empty, unqualified names.'
        );

        $this->makeRelation()->whereRelated(' ', 'active');
    }

    /**
     * Ensure callers identify the table through the fluent target method.
     *
     * @return void
     */
    public function test_rejects_a_qualified_column(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Auto-join relation columns must be non-empty, unqualified names.'
        );

        $this->makeRelation()->whereRelated('users.status', 'active');
    }

    /**
     * Ensure the three-argument form requires a valid operator.
     *
     * @return void
     */
    public function test_rejects_an_empty_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Auto-join relation operators must be non-empty strings.'
        );

        $this->makeRelation()->whereRelated('status', ' ', 'active');
    }

    /**
     * Create an AutoJoinRelation around an Eloquent relation test double.
     *
     * @return AutoJoinRelation
     */
    private function makeRelation(): AutoJoinRelation
    {
        return new AutoJoinRelation(
            $this->createMock(Relation::class)
        );
    }
}

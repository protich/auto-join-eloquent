<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Test: AccessibleDepartmentsDescriptorTest
 *
 * Verify accessibleDepartments model-defined paths are detected,
 * parsed, and described through the builder.
 */
class AccessibleDepartmentsDescriptorTest extends AutoJoinTestCase
{
    /**
     * Test accessibleDepartments count path is parsed and described.
     *
     * @return void
     */
    public function test_accessible_departments_count_path_is_parsed_and_described(): void
    {
        $builder = Agent::query();

        $this->assertInstanceOf(AutoJoinQueryBuilder::class, $builder);
        $this->assertTrue(
            $builder->isModelDefinedPath('model__accessibleDepartments__id__count')
        );

        $parsed = $builder->parseModelDefinedPath(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame([
            'path'     => 'accessibleDepartments',
            'segments' => ['id', 'count'],
        ], $parsed);

        $described = $builder->describeModelDefinedPath(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame('accessibleDepartments', $described['path']);
        $this->assertSame(['id', 'count'], $described['segments']);
        $this->assertSame([
            'type'     => 'count',
            'paths'    => [
                'departments.id',
                'groups.departments.id',
            ],
            'distinct' => true,
        ], $described['descriptor']);
    }
}

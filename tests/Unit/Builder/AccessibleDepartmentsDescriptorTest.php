<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;
use protich\AutoJoinEloquent\Tests\Models\Agent;

/**
 * Test: AccessibleDepartmentsDescriptorTest
 *
 * Verify accessibleDepartments model-defined paths are described.
 */
class AccessibleDepartmentsDescriptorTest extends AutoJoinTestCase
{
    /**
     * Test accessibleDepartments count path is described.
     */
    public function test_accessible_departments_count_path_is_described(): void
    {
        $builder = Agent::query();

        $this->assertInstanceOf(AutoJoinQueryBuilder::class, $builder);
        $this->assertTrue(
            $builder->isModelDefinedPath('model__accessibleDepartments__id__count')
        );

        $described = $builder->describeModelDefinedPath(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame(
            'model__accessibleDepartments__id__count',
            $described['path']
        );
        $this->assertInstanceOf(
            ExpressionDescriptor::class,
            $described['descriptor']
        );
        $this->assertSame('count', $described['descriptor']->type());
        $this->assertSame([
            'departments.id',
            'groups.departments.id',
        ], $described['descriptor']->paths());
        $this->assertTrue($described['descriptor']->distinct());
    }
}

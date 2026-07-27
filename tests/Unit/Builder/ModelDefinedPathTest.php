<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

/**
 * Test: ModelDefinedPathTest
 *
 * Verify complete model-defined paths are delegated to the base model.
 */
class ModelDefinedPathTest extends AutoJoinTestCase
{
    /**
     * Test model-defined path descriptor resolution.
     */
    public function test_model_status_path_is_described(): void
    {
        $builder = Agent::query();

        $this->assertInstanceOf(AutoJoinQueryBuilder::class, $builder);
        $this->assertTrue($builder->isModelDefinedPath('model__status'));

        $described = $builder->describeModelDefinedPath('model__status');

        $this->assertSame('model__status', $described['path']);
        $this->assertInstanceOf(
            ExpressionDescriptor::class,
            $described['descriptor']
        );
        $this->assertSame('path', $described['descriptor']->type());
        $this->assertSame('flags', $described['descriptor']->getPath());
    }

    /**
     * Test the package does not segment a model-defined path.
     */
    public function test_model_receives_the_complete_path(): void
    {
        $builder = Agent::query();

        $described = $builder->describeModelDefinedPath(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame(
            'model__accessibleDepartments__id__count',
            $described['path']
        );
        $this->assertSame(
            ['departments.id', 'groups.departments.id'],
            $described['descriptor']->paths()
        );
    }

    /**
     * Test invalid model-defined path throws.
     *
     * @return void
     */
    public function test_invalid_model_defined_path_throws(): void
    {
        $builder = Agent::query();

        $this->expectException(\InvalidArgumentException::class);

        $builder->describeModelDefinedPath('model__');
    }
}

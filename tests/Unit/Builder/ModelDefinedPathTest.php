<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Builder;

use protich\AutoJoinEloquent\AutoJoinQueryBuilder;
use protich\AutoJoinEloquent\Tests\Models\Agent;
use protich\AutoJoinEloquent\Tests\AutoJoinTestCase;

/**
 * Test: ModelDefinedPathTest
 *
 * Verify model-defined auto-join paths are detected, parsed,
 * and described through the base model.
 */
class ModelDefinedPathTest extends AutoJoinTestCase
{
    /**
     * Test model-defined path parsing and descriptor resolution.
     *
     * @return void
     */
    public function test_model_status_path_is_parsed_and_described(): void
    {
        $builder = Agent::query();

        $this->assertInstanceOf(AutoJoinQueryBuilder::class, $builder);
        $this->assertTrue($builder->isModelDefinedPath('model__status'));

        $parsed = $builder->parseModelDefinedPath('model__status');

        $this->assertSame([
            'path'     => 'status',
            'segments' => [],
        ], $parsed);

        $described = $builder->describeModelDefinedPath('model__status');

        $this->assertSame('status', $described['path']);
        $this->assertSame([], $described['segments']);
        $this->assertSame([
            'type' => 'path',
            'path' => 'flags',
        ], $described['descriptor']);
    }

    /**
     * Test parsing a model-defined path with additional segments.
     *
     * @return void
     */
    public function test_model_defined_path_parses_segments(): void
    {
        $builder = Agent::query();

        $parsed = $builder->parseModelDefinedPath(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame([
            'path'     => 'accessibleDepartments',
            'segments' => ['id', 'count'],
        ], $parsed);
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

        $builder->parseModelDefinedPath('model__');
    }
}

<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Model;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use protich\AutoJoinEloquent\Model\PathRequest;

/**
 * Verify the public PathRequest API and its path validation rules.
 */
class PathRequestTest extends TestCase
{
    /**
     * Ensure the complete model-defined expression remains intact.
     *
     * @return void
     */
    public function test_preserves_the_complete_path(): void
    {
        $request = new PathRequest(
            'model__accessibleDepartments__id__count'
        );

        $this->assertSame(
            'model__accessibleDepartments__id__count',
            $request->path
        );
    }

    /**
     * Ensure surrounding whitespace is removed without segmenting the path.
     *
     * @return void
     */
    public function test_trims_surrounding_whitespace(): void
    {
        $request = new PathRequest('  model__status__label  ');

        $this->assertSame('model__status__label', $request->path);
    }

    /**
     * Ensure a path without the reserved marker is rejected.
     *
     * @return void
     */
    public function test_rejects_a_path_without_the_model_marker(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Auto-join model path [status] must begin with [model__].'
        );

        new PathRequest('status');
    }

    /**
     * Ensure the marker alone is not considered a model-defined path.
     *
     * @return void
     */
    public function test_rejects_the_model_marker_without_a_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Auto-join model path [model__] must begin with [model__].'
        );

        new PathRequest('model__');
    }

    /**
     * Ensure an empty value is rejected after normalization.
     *
     * @return void
     */
    public function test_rejects_an_empty_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PathRequest('   ');
    }
}

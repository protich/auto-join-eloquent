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
            'accessibleDepartments__id__count'
        );

        $this->assertSame(
            'accessibleDepartments__id__count',
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
        $request = new PathRequest('  status__label  ');

        $this->assertSame('status__label', $request->path);
    }

    /**
     * Ensure an empty value is rejected after normalization.
     *
     * @return void
     */
    public function test_rejects_an_empty_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Auto-join model path must not be empty.'
        );

        new PathRequest('   ');
    }
}

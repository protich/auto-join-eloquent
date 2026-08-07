<?php

namespace protich\AutoJoinEloquent\Tests\Unit\Model;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use protich\AutoJoinEloquent\Model\ExpressionDescriptor;

/**
 * Verify the complete public API of ExpressionDescriptor.
 */
class ExpressionDescriptorTest extends TestCase
{
    /**
     * Ensure a path descriptor exposes its normalized path and defaults.
     *
     * @return void
     */
    public function test_path_descriptor_exposes_normalized_path(): void
    {
        $descriptor = ExpressionDescriptor::path(' flags ');

        $this->assertSame(
            ExpressionDescriptor::TYPE_PATH,
            $descriptor->type()
        );
        $this->assertSame('flags', $descriptor->getPath());
        $this->assertSame(['flags'], $descriptor->paths());
        $this->assertFalse($descriptor->distinct());
    }

    /**
     * Ensure a string creates a single-path count with default semantics.
     *
     * @return void
     */
    public function test_count_accepts_a_single_path_string(): void
    {
        $descriptor = ExpressionDescriptor::count(' departments.id ');

        $this->assertSame(
            ExpressionDescriptor::TYPE_COUNT,
            $descriptor->type()
        );
        $this->assertNull($descriptor->getPath());
        $this->assertSame(['departments.id'], $descriptor->paths());
        $this->assertFalse($descriptor->distinct());
    }

    /**
     * Ensure multiple paths are normalized into a list and preserve order.
     *
     * @return void
     */
    public function test_count_accepts_multiple_distinct_paths(): void
    {
        $descriptor = ExpressionDescriptor::count([
            5 => ' departments.id ',
            9 => 'groups.departments.id',
        ], distinct: true);

        $this->assertSame([
            'departments.id',
            'groups.departments.id',
        ], $descriptor->paths());
        $this->assertTrue($descriptor->distinct());
    }

    /**
     * Ensure every scalar aggregate has a typed factory and SQL function.
     *
     * @return void
     */
    public function test_scalar_aggregate_factories_are_complete(): void
    {
        $descriptors = [
            ExpressionDescriptor::sum(' departments.id ', true),
            ExpressionDescriptor::avg('departments.id'),
            ExpressionDescriptor::min('departments.id'),
            ExpressionDescriptor::max('departments.id'),
        ];

        $this->assertSame(
            [
                ExpressionDescriptor::TYPE_SUM,
                ExpressionDescriptor::TYPE_AVG,
                ExpressionDescriptor::TYPE_MIN,
                ExpressionDescriptor::TYPE_MAX,
            ],
            array_map(
                static fn (ExpressionDescriptor $descriptor): string =>
                    $descriptor->type(),
                $descriptors
            )
        );
        $this->assertSame(
            ['SUM', 'AVG', 'MIN', 'MAX'],
            array_map(
                static fn (ExpressionDescriptor $descriptor): ?string =>
                    $descriptor->aggregateFunction(),
                $descriptors
            )
        );
        $this->assertSame(['departments.id'], $descriptors[0]->paths());
        $this->assertTrue($descriptors[0]->distinct());
    }

    /**
     * Ensure COALESCE preserves ordered path fallback semantics.
     *
     * @return void
     */
    public function test_coalesce_preserves_ordered_paths(): void
    {
        $descriptor = ExpressionDescriptor::coalesce([
            ' user.phone ',
            'user.email',
        ]);

        $this->assertSame(
            ExpressionDescriptor::TYPE_COALESCE,
            $descriptor->type()
        );
        $this->assertSame(
            ['user.phone', 'user.email'],
            $descriptor->paths()
        );
    }

    /**
     * Ensure CONCAT preserves its path order and separator verbatim.
     *
     * @return void
     */
    public function test_concat_preserves_paths_and_separator(): void
    {
        $descriptor = ExpressionDescriptor::concat(
            [' parent.name ', 'name'],
            ' / '
        );

        $this->assertSame(
            ExpressionDescriptor::TYPE_CONCAT,
            $descriptor->type()
        );
        $this->assertSame(['parent.name', 'name'], $descriptor->paths());
        $this->assertSame(' / ', $descriptor->separator());
    }

    /**
     * Ensure an empty path descriptor is rejected.
     *
     * @return void
     */
    public function test_path_rejects_an_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expression descriptor paths must be non-empty strings.'
        );

        ExpressionDescriptor::path('   ');
    }

    /**
     * Ensure a count requires at least one path.
     *
     * @return void
     */
    public function test_count_rejects_an_empty_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Count expression descriptor requires at least one path.'
        );

        ExpressionDescriptor::count([]);
    }

    /**
     * Ensure a blank single count path is rejected.
     *
     * @return void
     */
    public function test_count_rejects_a_blank_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expression descriptor paths must be non-empty strings.'
        );

        ExpressionDescriptor::count(' ');
    }

    /**
     * Ensure every entry in a multi-path count is validated.
     *
     * @return void
     */
    public function test_count_rejects_a_blank_path_in_a_list(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expression descriptor paths must be non-empty strings.'
        );

        ExpressionDescriptor::count([
            'departments.id',
            ' ',
        ]);
    }

    /**
     * Ensure composite descriptors require at least two paths.
     *
     * @return void
     */
    public function test_coalesce_requires_two_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires at least 2 paths');

        ExpressionDescriptor::coalesce(['name']);
    }

    /**
     * Ensure concatenation requires at least two paths.
     *
     * @return void
     */
    public function test_concat_requires_two_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires at least 2 paths');

        ExpressionDescriptor::concat(['name']);
    }
}

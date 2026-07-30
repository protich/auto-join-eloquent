<?php

namespace protich\AutoJoinEloquent\Model;

use InvalidArgumentException;

/**
 * Class: ExpressionDescriptor
 *
 * A model-provided description of an expression the package can compile.
 *
 * Models create descriptors through the named factories instead of returning
 * compiler-specific arrays. The descriptor intentionally supports a small
 * expression vocabulary that can grow without introducing additional model
 * imports.
 */
final readonly class ExpressionDescriptor
{
    /**
     * Descriptor type for a path resolved by the normal column compiler.
     *
     * @var string
     */
    public const TYPE_PATH = 'path';

    /**
     * Descriptor type for a count over one or more relationship paths.
     *
     * @var string
     */
    public const TYPE_COUNT = 'count';

    /**
     * Create a normalized immutable descriptor.
     *
     * @param  string        $type
     * @param  list<string>  $paths
     * @param  bool          $distinct
     */
    private function __construct(
        private string $type,
        private array $paths,
        private bool $distinct = false
    ) {
    }

    /**
     * Describe an expression that resolves to another auto-join path.
     *
     * @param  string  $path
     * @return self
     *
     * @throws InvalidArgumentException If the path is empty.
     */
    public static function path(string $path): self
    {
        return new self(
            self::TYPE_PATH,
            [self::normalizePath($path)]
        );
    }

    /**
     * Describe a count over one or more auto-join paths.
     *
     * A list of paths allows the compiler to count the union of multiple
     * relationship routes, such as direct and group-derived access.
     *
     * @param  string|array<int, string>  $paths
     * @param  bool                       $distinct
     * @return self
     *
     * @throws InvalidArgumentException If no non-empty path is supplied.
     */
    public static function count(string|array $paths, bool $distinct = false): self
    {
        $paths = is_string($paths) ? [$paths] : array_values($paths);

        if ($paths === []) {
            throw new InvalidArgumentException(
                'Count expression descriptor requires at least one path.'
            );
        }

        return new self(
            self::TYPE_COUNT,
            array_map(self::normalizePath(...), $paths),
            $distinct
        );
    }

    /**
     * Get the descriptor type.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Get the resolved path for a path descriptor.
     *
     * @return string|null Null for descriptors that are not path descriptors.
     */
    public function getPath(): ?string
    {
        return $this->type === self::TYPE_PATH
            ? $this->paths[0]
            : null;
    }

    /**
     * Get all paths carried by the descriptor.
     *
     * Path descriptors contain one entry; count descriptors may contain one
     * or more entries.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Determine whether count compilation should use distinct semantics.
     *
     * @return bool
     */
    public function distinct(): bool
    {
        return $this->distinct;
    }

    /**
     * Trim and validate a descriptor path.
     *
     * @param  string  $path
     * @return non-empty-string
     *
     * @throws InvalidArgumentException If the path is empty.
     */
    private static function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new InvalidArgumentException(
                'Expression descriptor paths must be non-empty strings.'
            );
        }

        return $path;
    }
}

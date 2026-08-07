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
     * Descriptor types for scalar aggregate expressions.
     */
    public const TYPE_SUM = 'sum';
    public const TYPE_AVG = 'avg';
    public const TYPE_MIN = 'min';
    public const TYPE_MAX = 'max';

    /**
     * Descriptor type for the first non-null value across multiple paths.
     */
    public const TYPE_COALESCE = 'coalesce';

    /**
     * Descriptor type for null-skipping path concatenation.
     */
    public const TYPE_CONCAT = 'concat';

    /**
     * Create a normalized immutable descriptor.
     *
     * @param  string        $type
     * @param  list<string>  $paths
     * @param  bool          $distinct
     * @param  string|null   $separator
     */
    private function __construct(
        private string $type,
        private array $paths,
        private bool $distinct = false,
        private ?string $separator = null
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

        return new self(
            self::TYPE_COUNT,
            self::normalizePaths($paths, 'Count'),
            $distinct
        );
    }

    /**
     * Describe a SUM aggregate over one auto-join path.
     */
    public static function sum(string $path, bool $distinct = false): self
    {
        return self::aggregate(self::TYPE_SUM, $path, $distinct);
    }

    /**
     * Describe an AVG aggregate over one auto-join path.
     */
    public static function avg(string $path, bool $distinct = false): self
    {
        return self::aggregate(self::TYPE_AVG, $path, $distinct);
    }

    /**
     * Describe a MIN aggregate over one auto-join path.
     */
    public static function min(string $path, bool $distinct = false): self
    {
        return self::aggregate(self::TYPE_MIN, $path, $distinct);
    }

    /**
     * Describe a MAX aggregate over one auto-join path.
     */
    public static function max(string $path, bool $distinct = false): self
    {
        return self::aggregate(self::TYPE_MAX, $path, $distinct);
    }

    /**
     * Describe a COALESCE expression over ordered auto-join paths.
     *
     * @param  array<int,string>  $paths
     */
    public static function coalesce(array $paths): self
    {
        return new self(
            self::TYPE_COALESCE,
            self::normalizePaths($paths, 'Coalesce', 2)
        );
    }

    /**
     * Describe null-skipping concatenation over ordered auto-join paths.
     *
     * @param  array<int,string>  $paths
     */
    public static function concat(
        array $paths,
        string $separator = ''
    ): self {
        return new self(
            self::TYPE_CONCAT,
            self::normalizePaths($paths, 'Concat', 2),
            separator: $separator
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
     * Path and scalar aggregate descriptors contain one entry. Count and
     * composite descriptors may contain multiple ordered paths.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Determine whether aggregate compilation should use distinct semantics.
     *
     * @return bool
     */
    public function distinct(): bool
    {
        return $this->distinct;
    }

    /**
     * Get the SQL aggregate function represented by this descriptor.
     */
    public function aggregateFunction(): ?string
    {
        return match ($this->type) {
            self::TYPE_COUNT => 'COUNT',
            self::TYPE_SUM => 'SUM',
            self::TYPE_AVG => 'AVG',
            self::TYPE_MIN => 'MIN',
            self::TYPE_MAX => 'MAX',
            default => null,
        };
    }

    /**
     * Get the separator for a concatenation descriptor.
     */
    public function separator(): ?string
    {
        return $this->separator;
    }

    /**
     * Create a normalized single-path aggregate descriptor.
     */
    private static function aggregate(
        string $type,
        string $path,
        bool $distinct
    ): self {
        return new self(
            $type,
            [self::normalizePath($path)],
            $distinct
        );
    }

    /**
     * Normalize and validate an ordered path list.
     *
     * @param  array<int,string>  $paths
     * @return list<string>
     */
    private static function normalizePaths(
        array $paths,
        string $descriptor,
        int $minimum = 1
    ): array {
        $paths = array_values($paths);

        if (count($paths) < $minimum) {
            throw new InvalidArgumentException(sprintf(
                '%s expression descriptor requires at least %s path%s.',
                $descriptor,
                $minimum === 1 ? 'one' : (string) $minimum,
                $minimum === 1 ? '' : 's'
            ));
        }

        return array_map(self::normalizePath(...), $paths);
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

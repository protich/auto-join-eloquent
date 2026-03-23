<?php

namespace protich\AutoJoinEloquent\Support;

use RuntimeException;

/**
 * Class: Descriptor
 *
 * Represent a validated model-defined path descriptor.
 *
 * Descriptors are created through Descriptor::make(), which validates
 * and normalizes the raw definition before the object is instantiated.
 *
 * Supported types:
 * - path
 * - count
 */
final class Descriptor
{
    /**
     * Descriptor type.
     *
     * @var string
     */
    protected string $type;

    /**
     * Optional explicit alias.
     *
     * @var string|null
     */
    protected ?string $alias;

    /**
     * Whether aliasing is allowed for this compile pass.
     *
     * @var bool
     */
    protected bool $allowAlias;

    /**
     * Path value for path descriptors.
     *
     * @var string|null
     */
    protected ?string $path;

    /**
     * Paths value for count descriptors.
     *
     * @var array<int,string>
     */
    protected array $paths;

    /**
     * Whether DISTINCT semantics are enabled.
     *
     * @var bool
     */
    protected bool $distinct;

    /**
     * Create a new descriptor instance.
     *
     * @param  array<string,mixed> $data
     * @return void
     */
    protected function __construct(array $data)
    {
        $this->type       = $data['type'];
        $this->alias      = $data['alias'] ?? null;
        $this->allowAlias = (bool) ($data['allowAlias'] ?? true);
        $this->path       = $data['path'] ?? null;
        $this->paths      = $data['paths'] ?? [];
        $this->distinct   = (bool) ($data['distinct'] ?? false);
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
     * Get the explicit alias when aliasing is allowed.
     *
     * @return string|null
     */
    public function alias(): ?string
    {
        return $this->allowAlias ? $this->alias : null;
    }

    /**
     * Determine whether aliasing is allowed.
     *
     * @return bool
     */
    public function allowsAlias(): bool
    {
        return $this->allowAlias;
    }

    /**
     * Determine whether a default alias may be generated.
     *
     * @return bool
     */
    public function shouldAutoAlias(): bool
    {
        return $this->allowAlias && $this->alias === null;
    }

    /**
     * Get the path for path descriptors.
     *
     * @return string|null
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * Get the paths for count descriptors.
     *
     * @return array<int,string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Determine whether DISTINCT semantics are enabled.
     *
     * @return bool
     */
    public function distinct(): bool
    {
        return $this->distinct;
    }

    /**
     * Validate and normalize a path descriptor definition.
     *
     * @param  array<string,mixed> $definition
     * @param  string|null         $alias
     * @param  bool                $allowAlias
     * @return array<string,mixed>
     */
    protected static function validatePathDescriptor(
        array $definition,
        ?string $alias,
        bool $allowAlias
    ): array {
        $path = $definition['path'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException(
                'Path descriptor must define a non-empty [path].'
            );
        }

        return [
            'type'       => 'path',
            'alias'      => $alias,
            'allowAlias' => $allowAlias,
            'path'       => trim($path),
            'paths'      => [],
            'distinct'   => false,
        ];
    }

    /**
     * Validate and normalize a count descriptor definition.
     *
     * @param  array<string,mixed> $definition
     * @param  string|null         $alias
     * @param  bool                $allowAlias
     * @return array<string,mixed>
     */
    protected static function validateCountDescriptor(
        array $definition,
        ?string $alias,
        bool $allowAlias
    ): array {
        $paths = $definition['paths'] ?? null;

        if (! is_array($paths) || $paths === []) {
            throw new RuntimeException(
                'Count descriptor must define a non-empty [paths] array.'
            );
        }

        $normalized = [];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                throw new RuntimeException(
                    'Count descriptor [paths] must contain only non-empty strings.'
                );
            }

            $normalized[] = trim($path);
        }

        return [
            'type'       => 'count',
            'alias'      => $alias,
            'allowAlias' => $allowAlias,
            'path'       => null,
            'paths'      => $normalized,
            'distinct'   => (bool) ($definition['distinct'] ?? false),
        ];
    }

    /**
     * Create a validated descriptor instance.
     *
     * @param  array<string,mixed> $definition
     * @param  string|null         $alias
     * @param  bool                $allowAlias
     * @return static
     */
    public static function make(
        array $definition,
        ?string $alias = null,
        bool $allowAlias = true
    ): static {
        $type = $definition['type'] ?? null;

        if (! is_string($type) || $type === '') {
            throw new RuntimeException(
                'Descriptor must define a non-empty [type].'
            );
        }

        $data = match ($type) {
            'path'  => static::validatePathDescriptor($definition, $alias, $allowAlias),
            'count' => static::validateCountDescriptor($definition, $alias, $allowAlias),
            default => throw new RuntimeException(sprintf(
                'Unsupported descriptor type [%s].',
                $type
            )),
        };

        return new static($data);
    }
}

<?php

namespace protich\AutoJoinEloquent\Model;

use InvalidArgumentException;

/**
 * Class: PathRequest
 *
 * The complete model-defined path submitted to a model hook.
 *
 * The path is deliberately kept intact so the auto-joiner does not interpret
 * application-defined segments. Models receive the reserved `model__` marker
 * and decide how the complete expression should be described.
 */
final readonly class PathRequest
{
    /**
     * Complete model-defined path, including the `model__` marker.
     *
     * @var non-empty-string
     */
    public string $path;

    /**
     * Create a request for a complete model-defined path.
     *
     * @param  string  $path
     *
     * @throws InvalidArgumentException If the path does not contain a name
     *                                  after the `model__` marker.
     */
    public function __construct(string $path)
    {
        $path = trim($path);

        if (! str_starts_with($path, 'model__') || $path === 'model__') {
            throw new InvalidArgumentException(sprintf(
                'Auto-join model path [%s] must begin with [model__].',
                $path
            ));
        }

        $this->path = $path;
    }
}

<?php

namespace protich\AutoJoinEloquent\Model;

use InvalidArgumentException;

/**
 * Class: PathRequest
 *
 * The complete model-defined path submitted to a model hook.
 *
 * The path is deliberately kept intact so the auto-joiner does not interpret
 * application-defined segments. The auto-joiner removes its reserved marker
 * before constructing the request.
 */
final readonly class PathRequest
{
    /**
     * Complete application-defined path without the auto-join marker.
     *
     * @var non-empty-string
     */
    public string $path;

    /**
     * Create a request for a complete model-defined path.
     *
     * @param  string  $path
     *
     * @throws InvalidArgumentException If the path is empty.
     */
    public function __construct(string $path)
    {
        $path = trim($path);

        if ($path === '') {
            throw new InvalidArgumentException(
                'Auto-join model path must not be empty.'
            );
        }

        $this->path = $path;
    }
}

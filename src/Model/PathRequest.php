<?php

namespace protich\AutoJoinEloquent\Model;

use InvalidArgumentException;

/**
 * Class: PathRequest
 *
 * The complete unresolved path remainder submitted to a model hook.
 *
 * Normal relationship hops are resolved before the request is constructed.
 * The remaining model-owned expression is kept intact so the package does not
 * impose application-specific segmentation rules.
 */
final readonly class PathRequest
{
    /**
     * Complete model-local remainder without the auto-join marker.
     *
     * @var non-empty-string
     */
    public string $path;

    /**
     * Create a request for a complete unresolved path remainder.
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

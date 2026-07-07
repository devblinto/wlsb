<?php

declare(strict_types=1);

namespace Wlsb\Access\Support;

use RuntimeException;

/**
 * Thrown when the container is asked to resolve an id that was never bound.
 */
final class NotFoundException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('No binding registered for "%s".', $id));
    }
}

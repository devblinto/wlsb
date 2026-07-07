<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Infrastructure\Database\VersionStore;

/**
 * In-memory schema VersionStore double.
 */
final class InMemoryVersionStore implements VersionStore
{
    public function __construct(private int $version = 0) {}

    public function get(): int
    {
        return $this->version;
    }

    public function set(int $version): void
    {
        $this->version = $version;
    }
}

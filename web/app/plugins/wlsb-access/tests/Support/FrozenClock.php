<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use DateTimeImmutable;
use Wlsb\Access\Domain\Clock\Clock;

/**
 * Deterministic clock for tests: always returns the instant it was given.
 */
final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

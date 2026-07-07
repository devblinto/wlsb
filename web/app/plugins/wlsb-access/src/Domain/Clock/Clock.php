<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Clock;

use DateTimeImmutable;

/**
 * Abstracts "now" so time-dependent logic (event timestamps, token expiry) is
 * deterministic under test via a frozen clock.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}

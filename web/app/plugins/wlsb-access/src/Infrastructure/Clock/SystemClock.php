<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Wlsb\Access\Domain\Clock\Clock;

/**
 * Real clock: always UTC so stored timestamps are timezone-stable regardless of
 * the site's configured timezone.
 */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Events;

/**
 * Append-only audit logger. Implementations must never update or delete existing
 * entries; retention/anonymisation is a separate, explicit concern.
 */
interface EventLogger
{
    public function log(Event $event): void;
}

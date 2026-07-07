<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Events;

/**
 * An immutable audit event to append to the log.
 *
 * The caller supplies the subject (object/user) and, when applicable, the actor
 * and their IP (already packed to binary). A null actor marks a system event.
 * The timestamp is stamped by the logger via the Clock, not here, so events are
 * comparable and testable.
 */
final class Event
{
    /**
     * @param array<string, mixed> $context arbitrary structured detail (JSON-encoded on write)
     */
    public function __construct(
        public readonly EventType $type,
        public readonly string $objectType,
        public readonly int $objectId = 0,
        public readonly ?int $userId = null,
        public readonly ?int $actorId = null,
        public readonly ?string $actorIp = null,
        public readonly array $context = [],
    ) {}
}

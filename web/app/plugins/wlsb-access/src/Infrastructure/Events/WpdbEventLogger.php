<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Events;

use Wlsb\Access\Domain\Clock\Clock;
use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;

/**
 * Appends audit events to the `wlsb_event_log` table via `$wpdb->insert()`.
 *
 * The event → row mapping is pure and unit-tested with a fake `$wpdb`; the only
 * WordPress dependency is the insert call itself. Timestamps come from the
 * injected Clock so ordering is stable and testable.
 *
 * `$wpdb` is typed loosely (`object`) rather than `\wpdb` so the mapping is
 * exercisable without loading WordPress; the real dependency is the global
 * `$wpdb` instance supplied at wiring.
 */
final class WpdbEventLogger implements EventLogger
{
    public function __construct(
        private readonly object $wpdb,
        private readonly string $table,
        private readonly Clock $clock,
    ) {}

    public function log(Event $event): void
    {
        $context = $event->context === []
            ? null
            : (json_encode($event->context) ?: null);

        $this->wpdb->insert($this->table, [
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
            'event_type' => $event->type->value,
            'object_type' => $event->objectType,
            'object_id' => $event->objectId,
            'user_id' => $event->userId,
            'actor_id' => $event->actorId,
            'actor_ip' => $event->actorIp,
            'created_by_system' => $event->actorId === null ? 1 : 0,
            'context' => $context,
        ]);
    }
}

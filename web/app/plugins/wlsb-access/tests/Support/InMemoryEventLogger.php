<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;

/**
 * Records events in memory so tests can assert what was logged.
 */
final class InMemoryEventLogger implements EventLogger
{
    /** @var list<Event> */
    private array $events = [];

    public function log(Event $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<Event>
     */
    public function all(): array
    {
        return $this->events;
    }
}

<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Infrastructure\Events\WpdbEventLogger;
use Wlsb\Access\Tests\Support\FrozenClock;

function fakeWpdb(): object
{
    return new class {
        /** @var list<array{0:string,1:array<string,mixed>}> */
        public array $inserted = [];

        public function insert(string $table, array $data): int
        {
            $this->inserted[] = [$table, $data];

            return 1;
        }
    };
}

function frozenAt(string $instant): FrozenClock
{
    return new FrozenClock(new DateTimeImmutable($instant, new DateTimeZone('UTC')));
}

test('maps a system event to a row and inserts it', function (): void {
    $wpdb = fakeWpdb();
    $logger = new WpdbEventLogger($wpdb, 'wp_wlsb_event_log', frozenAt('2026-07-07 12:00:00.500'));

    $logger->log(new Event(EventType::RolesReconciled, 'system', context: ['added' => ['staff']]));

    [$table, $row] = $wpdb->inserted[0];

    expect($table)->toBe('wp_wlsb_event_log')
        ->and($row['event_type'])->toBe('roles.reconciled')
        ->and($row['object_type'])->toBe('system')
        ->and($row['object_id'])->toBe(0)
        ->and($row['created_at'])->toBe('2026-07-07 12:00:00.500')
        ->and($row['actor_id'])->toBeNull()
        ->and($row['created_by_system'])->toBe(1)
        ->and($row['context'])->toBe(json_encode(['added' => ['staff']]));
});

test('records the actor and marks the event non-system, with null context when empty', function (): void {
    $wpdb = fakeWpdb();
    $logger = new WpdbEventLogger($wpdb, 'wp_wlsb_event_log', frozenAt('2026-07-07 09:30:00.000'));

    $logger->log(new Event(EventType::RoleUpdated, 'role', objectId: 0, actorId: 5, actorIp: 'RAWIP'));

    [, $row] = $wpdb->inserted[0];

    expect($row['actor_id'])->toBe(5)
        ->and($row['actor_ip'])->toBe('RAWIP')
        ->and($row['created_by_system'])->toBe(0)
        ->and($row['context'])->toBeNull();
});

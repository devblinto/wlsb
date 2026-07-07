<?php

declare(strict_types=1);

use Wlsb\Access\Application\Reconciliation;
use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Catalog;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Roles\Role;
use Wlsb\Access\Domain\Roles\RoleReconciler;
use Wlsb\Access\Infrastructure\Database\MigrationRunner;
use Wlsb\Access\Tests\Support\InMemoryEventLogger;
use Wlsb\Access\Tests\Support\InMemoryRoleOverrideRepository;
use Wlsb\Access\Tests\Support\InMemoryRolesGateway;
use Wlsb\Access\Tests\Support\InMemoryVersionStore;
use Wlsb\Access\Tests\Support\RecordingMigration;

function makeReconciliation(
    InMemoryRolesGateway $gateway,
    InMemoryEventLogger $logger,
    RecordingMigration $migration,
    InMemoryVersionStore $versions,
): Reconciliation {
    return new Reconciliation(
        new MigrationRunner($versions, [$migration]),
        new RoleReconciler(Catalog::roles(), Catalog::capabilities()),
        new InMemoryRoleOverrideRepository(),
        $gateway,
        $logger,
    );
}

test('runs pending migrations, materialises roles, and logs the change', function (): void {
    $gateway = new InMemoryRolesGateway([
        'administrator' => ['name' => 'Administrator', 'caps' => ['manage_options' => true]],
    ]);
    $logger = new InMemoryEventLogger();
    $migration = new RecordingMigration(1);

    $result = makeReconciliation($gateway, $logger, $migration, new InMemoryVersionStore())->run();

    expect($migration->ran)->toBeTrue()
        ->and($result['migrations'])->toBe([1])
        ->and($gateway->snapshot())->toHaveKey(Role::PENDING)
        ->and($gateway->snapshot()['administrator']['caps'])->toHaveKey(Cap::MANAGE_ACCESS)
        ->and($logger->all())->toHaveCount(1)
        ->and($logger->all()[0]->type)->toBe(EventType::RolesReconciled);
});

test('does not log when nothing changed on a repeat run', function (): void {
    $gateway = new InMemoryRolesGateway([
        'administrator' => ['name' => 'Administrator', 'caps' => ['manage_options' => true]],
    ]);
    $logger = new InMemoryEventLogger();
    $versions = new InMemoryVersionStore();

    $reconciliation = makeReconciliation($gateway, $logger, new RecordingMigration(1), $versions);
    $reconciliation->run();
    $second = $reconciliation->run();

    expect($second['migrations'])->toBe([])       // migration already applied
        ->and($second['roles']->isEmpty())->toBeTrue()
        ->and($logger->all())->toHaveCount(1);    // still only the first event
});

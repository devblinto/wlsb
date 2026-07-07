<?php

declare(strict_types=1);

use Wlsb\Access\Delivery\Cli\ReconcileCommand;
use Wlsb\Access\Domain\Roles\ReconcileResult;

beforeEach(function (): void {
    WP_CLI::reset();
});

test('reports that nothing changed when there are no migrations or role changes', function (): void {
    $command = new ReconcileCommand(fn(): array => [
        'migrations' => [],
        'roles' => new ReconcileResult(),
    ]);

    $command();

    expect(WP_CLI::$messages)->toHaveCount(1)
        ->and(WP_CLI::$messages[0][0])->toBe('success')
        ->and(WP_CLI::$messages[0][1])->toContain('up to date');
});

test('reports the migration and role-change counts when something changed', function (): void {
    $command = new ReconcileCommand(fn(): array => [
        'migrations' => [1],
        'roles' => new ReconcileResult(
            added: ['wlsb_pending'],
            grantsApplied: ['administrator:wlsb_manage_access'],
        ),
    ]);

    $command();

    expect(WP_CLI::$messages[0][0])->toBe('success')
        ->and(WP_CLI::$messages[0][1])->toContain('1 migration(s)')
        ->and(WP_CLI::$messages[0][1])->toContain('2 role change(s)');
});

<?php

declare(strict_types=1);

use Wlsb\Access\Infrastructure\Database\Migration;
use Wlsb\Access\Infrastructure\Database\MigrationRunner;
use Wlsb\Access\Infrastructure\Database\VersionStore;

/** In-memory version store double. */
function versionStore(int $initial = 0): VersionStore
{
    return new class ($initial) implements VersionStore {
        public function __construct(private int $version) {}

        public function get(): int
        {
            return $this->version;
        }

        public function set(int $version): void
        {
            $this->version = $version;
        }
    };
}

/** Build a recording migration; optionally one that fails. */
function migration(int $version, ArrayObject $log, bool $throws = false): Migration
{
    return new class ($version, $log, $throws) implements Migration {
        public function __construct(
            private int $version,
            private ArrayObject $log,
            private bool $throws,
        ) {}

        public function version(): int
        {
            return $this->version;
        }

        public function up(): void
        {
            if ($this->throws) {
                throw new RuntimeException("migration {$this->version} failed");
            }

            $this->log->append($this->version);
        }
    };
}

test('runs all pending migrations in version order from a fresh install', function (): void {
    $store = versionStore(0);
    $log = new ArrayObject();

    $applied = (new MigrationRunner($store, [
        migration(2, $log),
        migration(1, $log),
        migration(3, $log),
    ]))->run();

    expect($log->getArrayCopy())->toBe([1, 2, 3])
        ->and($applied)->toBe([1, 2, 3])
        ->and($store->get())->toBe(3);
});

test('only runs migrations newer than the stored version', function (): void {
    $store = versionStore(1);
    $log = new ArrayObject();

    $applied = (new MigrationRunner($store, [
        migration(1, $log),
        migration(2, $log),
        migration(3, $log),
    ]))->run();

    expect($log->getArrayCopy())->toBe([2, 3])
        ->and($applied)->toBe([2, 3])
        ->and($store->get())->toBe(3);
});

test('is idempotent — a second run applies nothing', function (): void {
    $store = versionStore(0);
    $log = new ArrayObject();
    $runner = new MigrationRunner($store, [migration(1, $log), migration(2, $log)]);

    $runner->run();
    $second = $runner->run();

    expect($second)->toBe([])
        ->and($log->getArrayCopy())->toBe([1, 2])
        ->and($store->get())->toBe(2);
});

test('a failing migration halts the run and records only completed versions', function (): void {
    $store = versionStore(0);
    $log = new ArrayObject();
    $runner = new MigrationRunner($store, [
        migration(1, $log),
        migration(2, $log, throws: true),
        migration(3, $log),
    ]);

    expect(fn() => $runner->run())->toThrow(RuntimeException::class, 'migration 2 failed');

    // v1 committed, v2 failed before recording, v3 never ran.
    expect($store->get())->toBe(1)
        ->and($log->getArrayCopy())->toBe([1]);
});

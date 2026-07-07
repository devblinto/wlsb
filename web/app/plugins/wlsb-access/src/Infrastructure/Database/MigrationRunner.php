<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Database;

/**
 * Applies pending schema migrations exactly once, in ascending version order.
 *
 * The runner is the idempotent core of the plugin's lifecycle: it is safe to
 * call on every activation, on the boot-time version guard, and from
 * `wp wlsb reconcile`. Each migration's version is recorded only after its
 * `up()` succeeds, so a mid-run failure leaves the schema at the last fully
 * applied version rather than a half-migrated state.
 */
final class MigrationRunner
{
    /** @var list<Migration> */
    private array $migrations;

    /**
     * @param list<Migration> $migrations
     */
    public function __construct(
        private readonly VersionStore $store,
        array $migrations,
    ) {
        $this->migrations = $migrations;
    }

    /**
     * Run all migrations newer than the stored version.
     *
     * @return list<int> the versions applied, in the order they ran
     */
    public function run(): array
    {
        $current = $this->store->get();

        $pending = array_filter(
            $this->migrations,
            static fn(Migration $migration): bool => $migration->version() > $current,
        );

        usort(
            $pending,
            static fn(Migration $a, Migration $b): int => $a->version() <=> $b->version(),
        );

        $applied = [];

        foreach ($pending as $migration) {
            $migration->up();
            $this->store->set($migration->version());
            $applied[] = $migration->version();
        }

        return $applied;
    }
}

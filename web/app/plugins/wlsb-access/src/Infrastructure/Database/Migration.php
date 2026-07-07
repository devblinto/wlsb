<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Database;

/**
 * A single, ordered, forward-only schema migration.
 *
 * `up()` performs the schema change (in production via `dbDelta()` / `$wpdb`).
 * Migrations must be idempotent at the SQL level where practical, but the
 * runner also guarantees each version runs at most once via the VersionStore.
 */
interface Migration
{
    /** Monotonic version number; migrations run in ascending order. */
    public function version(): int;

    public function up(): void;
}

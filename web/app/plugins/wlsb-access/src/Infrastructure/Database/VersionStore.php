<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Database;

/**
 * Persists the currently-applied schema version.
 *
 * Production implementation stores an integer in a WordPress option
 * (`wlsb_access_db_version`); tests use an in-memory double. Keeping this
 * behind an interface is what lets the MigrationRunner be unit-tested with
 * zero WordPress.
 */
interface VersionStore
{
    public function get(): int;

    public function set(int $version): void;
}

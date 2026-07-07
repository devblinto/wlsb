<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Infrastructure\Database\Migration;

/**
 * Migration double that records whether it ran.
 */
final class RecordingMigration implements Migration
{
    public bool $ran = false;

    public function __construct(private int $version) {}

    public function version(): int
    {
        return $this->version;
    }

    public function up(): void
    {
        $this->ran = true;
    }
}

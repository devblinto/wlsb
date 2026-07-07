<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Database;

/**
 * Stores the applied schema version in an autoloaded WordPress option.
 *
 * Autoloading keeps the boot-time version guard cheap: the value is already in
 * memory after WordPress loads `alloptions`, so checking "are migrations
 * pending?" on every request costs no extra query.
 */
final class OptionVersionStore implements VersionStore
{
    public function __construct(private readonly string $optionName) {}

    public function get(): int
    {
        return (int) get_option($this->optionName, 0);
    }

    public function set(int $version): void
    {
        update_option($this->optionName, $version, true);
    }
}

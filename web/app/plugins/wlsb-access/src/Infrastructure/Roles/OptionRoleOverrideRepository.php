<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Roles;

use Wlsb\Access\Domain\Roles\RoleOverrideRepository;
use Wlsb\Access\Domain\Roles\RoleOverrides;

/**
 * Stores role/capability overrides in a single autoloaded WordPress option.
 *
 * Autoloaded because the reconciler reads it on the boot-time version guard;
 * written as one blob so a save is atomic (no partial-write races).
 */
final class OptionRoleOverrideRepository implements RoleOverrideRepository
{
    public function __construct(private readonly string $optionName) {}

    public function load(): RoleOverrides
    {
        $data = get_option($this->optionName, []);

        return RoleOverrides::fromArray(is_array($data) ? $data : []);
    }

    public function save(RoleOverrides $overrides): void
    {
        update_option($this->optionName, $overrides->toArray(), true);
    }
}

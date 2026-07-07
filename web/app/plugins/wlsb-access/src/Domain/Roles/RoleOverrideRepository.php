<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * Port for loading and persisting the admin's role/capability customisations.
 *
 * Keeping this an interface lets the reconciler and admin controllers depend on
 * an abstraction while the concrete store (a WordPress option) stays in the
 * infrastructure layer and out of unit tests.
 */
interface RoleOverrideRepository
{
    public function load(): RoleOverrides;

    public function save(RoleOverrides $overrides): void;
}

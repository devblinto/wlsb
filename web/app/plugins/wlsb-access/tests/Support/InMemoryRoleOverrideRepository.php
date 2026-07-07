<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Roles\RoleOverrideRepository;
use Wlsb\Access\Domain\Roles\RoleOverrides;

/**
 * In-memory RoleOverrideRepository double.
 */
final class InMemoryRoleOverrideRepository implements RoleOverrideRepository
{
    public function __construct(private RoleOverrides $overrides = new RoleOverrides()) {}

    public function load(): RoleOverrides
    {
        return $this->overrides;
    }

    public function save(RoleOverrides $overrides): void
    {
        $this->overrides = $overrides;
    }
}

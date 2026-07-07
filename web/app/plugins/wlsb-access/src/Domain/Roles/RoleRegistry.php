<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * The plugin's default role definitions: owned-role blueprints plus capability
 * grants onto existing roles. Extended via the `wlsb/roles` filter when built.
 *
 * This holds the *code defaults* only; admin customisations live separately in
 * RoleOverrides and are merged on top by the RoleReconciler.
 */
final class RoleRegistry
{
    /** @var array<string, RoleBlueprint> */
    private array $blueprints = [];

    /** @var list<CapabilityGrant> */
    private array $grants = [];

    public function addBlueprint(RoleBlueprint $blueprint): void
    {
        unset($this->blueprints[$blueprint->slug]);
        $this->blueprints[$blueprint->slug] = $blueprint;
    }

    public function addGrant(CapabilityGrant $grant): void
    {
        $this->grants[] = $grant;
    }

    public function hasBlueprint(string $slug): bool
    {
        return isset($this->blueprints[$slug]);
    }

    public function blueprint(string $slug): ?RoleBlueprint
    {
        return $this->blueprints[$slug] ?? null;
    }

    /**
     * @return list<RoleBlueprint> in registration order
     */
    public function blueprints(): array
    {
        return array_values($this->blueprints);
    }

    /**
     * @return list<string>
     */
    public function blueprintSlugs(): array
    {
        return array_keys($this->blueprints);
    }

    /**
     * @return list<CapabilityGrant> in registration order
     */
    public function grants(): array
    {
        return $this->grants;
    }
}

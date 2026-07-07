<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Capabilities;

/**
 * The bounded allow-list of capabilities the plugin manages, plus their UI
 * groups. Third parties extend it (via the `wlsb/capabilities` filter applied
 * when the registry is built) rather than editing core, and the registry is the
 * single source of truth for "which capabilities exist and how they present."
 */
final class CapabilityRegistry
{
    /** @var array<string, Capability> */
    private array $capabilities = [];

    /** @var array<string, CapabilityGroup> */
    private array $groups = [];

    public function addGroup(CapabilityGroup $group): void
    {
        $this->groups[$group->key] = $group;
    }

    public function add(Capability $capability): void
    {
        // Re-registering a key replaces it (last-wins), so extensions can refine
        // a default definition without duplicating it.
        unset($this->capabilities[$capability->key]);
        $this->capabilities[$capability->key] = $capability;
    }

    public function has(string $key): bool
    {
        return isset($this->capabilities[$key]);
    }

    public function get(string $key): ?Capability
    {
        return $this->capabilities[$key] ?? null;
    }

    /**
     * @return list<Capability> in registration order
     */
    public function all(): array
    {
        return array_values($this->capabilities);
    }

    /**
     * @return list<CapabilityGroup> ordered by their `order`, then registration
     */
    public function groups(): array
    {
        $groups = array_values($this->groups);

        usort(
            $groups,
            static fn(CapabilityGroup $a, CapabilityGroup $b): int => $a->order <=> $b->order,
        );

        return $groups;
    }

    /**
     * @return list<Capability> the capabilities in the given group, in registration order
     */
    public function capabilitiesInGroup(string $groupKey): array
    {
        return array_values(array_filter(
            $this->capabilities,
            static fn(Capability $capability): bool => $capability->group === $groupKey,
        ));
    }

    /**
     * @return list<string> keys of capabilities flagged protected
     */
    public function protectedKeys(): array
    {
        return array_values(array_map(
            static fn(Capability $capability): string => $capability->key,
            array_filter(
                $this->capabilities,
                static fn(Capability $capability): bool => $capability->protected,
            ),
        ));
    }
}

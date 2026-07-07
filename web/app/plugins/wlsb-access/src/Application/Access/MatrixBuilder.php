<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

use Wlsb\Access\Domain\Roles\RoleOverrides;
use Wlsb\Access\Domain\Roles\RoleReconciler;

/**
 * Computes the capability-matrix view state.
 *
 * `defaultState` is the code-default grant for each role/capability (owned roles
 * from their blueprint, others from default grants) — the baseline the form
 * mapper diffs against. `checkedState` overlays the admin overrides to produce
 * the currently-effective state rendered in the checkboxes.
 */
final class MatrixBuilder
{
    public function __construct(private readonly RoleReconciler $reconciler) {}

    /**
     * @param list<string> $roleSlugs
     * @param list<string> $capabilityKeys
     * @return array<string, array<string, bool>>
     */
    public function defaultState(array $roleSlugs, array $capabilityKeys): array
    {
        $empty = new RoleOverrides();

        $ownedCaps = [];
        foreach ($this->reconciler->effectiveOwnedRoles($empty) as $role) {
            $ownedCaps[$role->slug] = array_fill_keys($role->capabilities, true);
        }

        $grantedCaps = [];
        foreach ($this->reconciler->effectiveGrants($empty) as $grant) {
            if ($grant->granted) {
                $grantedCaps[$grant->roleSlug][$grant->capability] = true;
            }
        }

        $state = [];
        foreach ($roleSlugs as $slug) {
            foreach ($capabilityKeys as $capability) {
                $state[$slug][$capability] = isset($ownedCaps[$slug])
                    ? isset($ownedCaps[$slug][$capability])
                    : ($grantedCaps[$slug][$capability] ?? false);
            }
        }

        return $state;
    }

    /**
     * Grants that must not be revocable through the UI (e.g. the administrator's
     * protected manage-access grant), keyed "slug|capability".
     *
     * @return array<string, true>
     */
    public function protectedPairs(): array
    {
        $pairs = [];

        foreach ($this->reconciler->effectiveGrants(new RoleOverrides()) as $grant) {
            if ($grant->protected) {
                $pairs[$grant->roleSlug . '|' . $grant->capability] = true;
            }
        }

        return $pairs;
    }

    /**
     * @param list<string> $roleSlugs
     * @param list<string> $capabilityKeys
     * @return array<string, array<string, bool>>
     */
    public function checkedState(array $roleSlugs, array $capabilityKeys, RoleOverrides $overrides): array
    {
        $default = $this->defaultState($roleSlugs, $capabilityKeys);

        $state = [];
        foreach ($roleSlugs as $slug) {
            foreach ($capabilityKeys as $capability) {
                $toggle = $overrides->togglesFor($slug)[$capability] ?? null;
                $state[$slug][$capability] = $toggle ?? $default[$slug][$capability];
            }
        }

        return $state;
    }
}

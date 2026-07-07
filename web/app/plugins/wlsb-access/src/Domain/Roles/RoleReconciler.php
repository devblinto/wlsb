<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;

/**
 * The single writer of WordPress roles.
 *
 * It computes the desired role state as `merge(code defaults, admin overrides)`
 * and materialises it through the RolesGateway, touching only what differs. This
 * is the one model that lets plugin updates change defaults without clobbering
 * admin edits: overrides are re-applied on top of the current defaults every
 * time, and protected capabilities/grants are always re-asserted to prevent
 * self-lockout.
 */
final class RoleReconciler
{
    public function __construct(
        private readonly RoleRegistry $registry,
        private readonly CapabilityRegistry $capabilities,
    ) {}

    /**
     * @return list<EffectiveRole> the owned roles after applying overrides
     */
    public function effectiveOwnedRoles(RoleOverrides $overrides): array
    {
        $protectedCaps = $this->capabilities->protectedKeys();
        $result = [];

        foreach ($this->mergedBlueprints($overrides) as $slug => $blueprint) {
            if ($overrides->isTombstoned($slug) && ! $blueprint->protected) {
                continue;
            }

            $capabilities = [];
            foreach ($blueprint->capabilities as $capability) {
                $capabilities[$capability] = true;
            }

            foreach ($overrides->togglesFor($slug) as $capability => $granted) {
                if ($granted) {
                    $capabilities[$capability] = true;
                } else {
                    unset($capabilities[$capability]);
                }
            }

            // A protected capability that is part of the role's definition cannot
            // be toggled away.
            foreach ($blueprint->capabilities as $capability) {
                if (in_array($capability, $protectedCaps, true)) {
                    $capabilities[$capability] = true;
                }
            }

            $result[] = new EffectiveRole(
                $slug,
                $overrides->renameFor($slug) ?? $blueprint->displayName,
                array_keys($capabilities),
                $blueprint->protected,
            );
        }

        return $result;
    }

    /**
     * @return list<CapabilityGrant> grants onto non-owned roles after overrides
     */
    public function effectiveGrants(RoleOverrides $overrides): array
    {
        $ownedSlugs = array_keys($this->mergedBlueprints($overrides));

        /** @var array<string, CapabilityGrant> $grants keyed "slug|cap" */
        $grants = [];
        foreach ($this->registry->grants() as $grant) {
            $grants[$grant->roleSlug . '|' . $grant->capability] = $grant;
        }

        foreach ($overrides->allToggles() as $slug => $toggles) {
            if (in_array($slug, $ownedSlugs, true)) {
                continue; // owned-role capabilities are handled elsewhere
            }

            foreach ($toggles as $capability => $granted) {
                $key = $slug . '|' . $capability;

                // A protected default grant always wins over an admin toggle.
                if (isset($grants[$key]) && $grants[$key]->protected) {
                    continue;
                }

                $grants[$key] = new CapabilityGrant($slug, (string) $capability, (bool) $granted);
            }
        }

        return array_values($grants);
    }

    /**
     * Apply the desired state to WordPress via the gateway, writing only diffs.
     */
    public function materialize(RoleOverrides $overrides, RolesGateway $gateway): ReconcileResult
    {
        $added = [];
        $removed = [];
        $updated = [];
        $grantsApplied = [];

        $ownedSlugs = [];

        foreach ($this->effectiveOwnedRoles($overrides) as $role) {
            $ownedSlugs[$role->slug] = true;
            $desired = $role->capabilityMap();

            if (! $gateway->roleExists($role->slug)) {
                $gateway->addRole($role->slug, $role->displayName, $desired);
                $added[] = $role->slug;

                continue;
            }

            $changed = false;

            if ($gateway->roleName($role->slug) !== $role->displayName) {
                $gateway->renameRole($role->slug, $role->displayName);
                $changed = true;
            }

            $current = $gateway->roleCapabilities($role->slug);

            foreach (array_keys($desired) as $capability) {
                if (($current[$capability] ?? false) !== true) {
                    $gateway->grantCap($role->slug, $capability);
                    $changed = true;
                }
            }

            foreach (array_keys($current) as $capability) {
                if (! isset($desired[$capability])) {
                    $gateway->revokeCap($role->slug, $capability);
                    $changed = true;
                }
            }

            if ($changed) {
                $updated[] = $role->slug;
            }
        }

        foreach ($this->mergedBlueprints($overrides) as $slug => $blueprint) {
            if ($overrides->isTombstoned($slug) && ! $blueprint->protected && $gateway->roleExists($slug)) {
                $gateway->removeRole($slug);
                $removed[] = $slug;
            }
        }

        // The plugin's managed capabilities are authoritative on every non-owned
        // role: granted where configured, and revoked where present but no longer
        // configured (so removing a grant override cleans up the orphaned cap).
        // Capabilities the plugin does not manage (WordPress-native caps like
        // edit_posts) are never touched.
        $managedCapabilities = $this->capabilities->keys();

        $desiredGrants = [];
        foreach ($this->effectiveGrants($overrides) as $grant) {
            $desiredGrants[$grant->roleSlug][$grant->capability] = $grant->granted;
        }

        foreach ($gateway->allRoleSlugs() as $slug) {
            if (isset($ownedSlugs[$slug])) {
                continue; // owned roles fully controlled above
            }

            $current = $gateway->roleCapabilities($slug);

            foreach ($managedCapabilities as $capability) {
                $want = $desiredGrants[$slug][$capability] ?? false;
                $has = isset($current[$capability]);

                if ($want && ! $has) {
                    $gateway->grantCap($slug, $capability);
                    $grantsApplied[] = $slug . ':' . $capability;
                } elseif (! $want && $has) {
                    $gateway->revokeCap($slug, $capability);
                    $grantsApplied[] = $slug . ':' . $capability;
                }
            }
        }

        return new ReconcileResult($added, $removed, $updated, $grantsApplied);
    }

    /**
     * Default blueprints overlaid by admin-created custom roles.
     *
     * @return array<string, RoleBlueprint> slug => blueprint
     */
    private function mergedBlueprints(RoleOverrides $overrides): array
    {
        $map = [];

        foreach ($this->registry->blueprints() as $blueprint) {
            $map[$blueprint->slug] = $blueprint;
        }

        foreach ($overrides->customRoles() as $blueprint) {
            $map[$blueprint->slug] = $blueprint;
        }

        return $map;
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleOverrides;

/**
 * Pure operations for admin-created custom roles, returning updated RoleOverrides.
 *
 * Custom roles are created with no capabilities — capabilities are granted
 * afterwards through the capability matrix — so creating a role can never
 * escalate privilege. Deletion keeps the blueprint and adds a tombstone so the
 * reconciler removes the role from WordPress; rename/delete are refused for any
 * role that is not an admin-created custom role, which prevents tampering with
 * core or plugin-owned roles.
 */
final class CustomRoleManager
{
    /**
     * @param list<string> $existingRoleSlugs all role slugs that currently exist in WordPress
     *
     * @throws InvalidRoleOperation
     */
    public function create(RoleOverrides $overrides, string $slug, string $displayName, array $existingRoleSlugs): RoleOverrides
    {
        $slug = trim($slug);
        $displayName = trim($displayName);

        if ($slug === '') {
            throw new InvalidRoleOperation(__('A role slug is required.', 'wlsb-access'));
        }

        if ($displayName === '') {
            throw new InvalidRoleOperation(__('A role name is required.', 'wlsb-access'));
        }

        // Recreating a tombstoned custom role is allowed even though it may still
        // exist in WordPress until the next reconcile.
        if (! $overrides->isTombstoned($slug)) {
            if (in_array($slug, $existingRoleSlugs, true) || $overrides->hasCustomRole($slug)) {
                throw new InvalidRoleOperation(sprintf(
                    /* translators: %s: role slug */
                    __('A role "%s" already exists.', 'wlsb-access'),
                    $slug,
                ));
            }
        }

        $customRoles = array_values(array_filter(
            $overrides->customRoles(),
            static fn(RoleBlueprint $role): bool => $role->slug !== $slug,
        ));
        $customRoles[] = new RoleBlueprint($slug, $displayName, [], false);

        $tombstones = array_values(array_filter(
            $overrides->tombstones(),
            static fn(string $tombstoned): bool => $tombstoned !== $slug,
        ));

        return $overrides->withCustomRoles($customRoles)->withTombstones($tombstones);
    }

    /**
     * @throws InvalidRoleOperation
     */
    public function rename(RoleOverrides $overrides, string $slug, string $displayName): RoleOverrides
    {
        $displayName = trim($displayName);

        if ($displayName === '') {
            throw new InvalidRoleOperation(__('A role name is required.', 'wlsb-access'));
        }

        if (! $overrides->hasCustomRole($slug) || $overrides->isTombstoned($slug)) {
            throw new InvalidRoleOperation(__('Only custom roles can be renamed.', 'wlsb-access'));
        }

        $customRoles = array_map(
            static fn(RoleBlueprint $role): RoleBlueprint => $role->slug === $slug
                ? $role->withDisplayName($displayName)
                : $role,
            $overrides->customRoles(),
        );

        return $overrides->withCustomRoles($customRoles);
    }

    /**
     * @throws InvalidRoleOperation
     */
    public function delete(RoleOverrides $overrides, string $slug): RoleOverrides
    {
        if (! $overrides->hasCustomRole($slug)) {
            throw new InvalidRoleOperation(__('Only custom roles can be deleted.', 'wlsb-access'));
        }

        $tombstones = $overrides->tombstones();

        if (! in_array($slug, $tombstones, true)) {
            $tombstones[] = $slug;
        }

        return $overrides->withTombstones(array_values($tombstones));
    }
}

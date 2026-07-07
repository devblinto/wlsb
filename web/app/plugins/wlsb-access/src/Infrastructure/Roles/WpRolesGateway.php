<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Roles;

use Wlsb\Access\Domain\Roles\RolesGateway;

/**
 * WordPress-backed RolesGateway: the concrete bridge between the reconciler and
 * WordPress's `wp_user_roles` option (via the `wp_roles()` singleton and the
 * role APIs). This adapter is the only place role writes happen.
 */
final class WpRolesGateway implements RolesGateway
{
    public function roleExists(string $slug): bool
    {
        return wp_roles()->is_role($slug);
    }

    public function allRoleSlugs(): array
    {
        return array_keys(wp_roles()->roles);
    }

    public function roleName(string $slug): ?string
    {
        return wp_roles()->role_names[$slug] ?? null;
    }

    public function roleCapabilities(string $slug): array
    {
        $role = get_role($slug);

        if ($role === null) {
            return [];
        }

        return array_filter(
            $role->capabilities,
            static fn($granted): bool => (bool) $granted,
        );
    }

    public function addRole(string $slug, string $name, array $capabilities): void
    {
        add_role($slug, $name, $capabilities);
    }

    public function removeRole(string $slug): void
    {
        remove_role($slug);
    }

    public function renameRole(string $slug, string $name): void
    {
        $roles = wp_roles();

        if (! $roles->is_role($slug)) {
            return;
        }

        $roles->roles[$slug]['name'] = $name;
        $roles->role_names[$slug] = $name;

        update_option($roles->role_key, $roles->roles);
    }

    public function grantCap(string $slug, string $cap): void
    {
        get_role($slug)?->add_cap($cap);
    }

    public function revokeCap(string $slug, string $cap): void
    {
        get_role($slug)?->remove_cap($cap);
    }
}

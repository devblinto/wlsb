<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain;

use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Capabilities\Capability;
use Wlsb\Access\Domain\Capabilities\CapabilityGroup;
use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;
use Wlsb\Access\Domain\Roles\CapabilityGrant;
use Wlsb\Access\Domain\Roles\Role;
use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleRegistry;

/**
 * The plugin's code-defined defaults: the capabilities/groups it manages and the
 * roles/grants it ships. These are the "defaults" layer the RoleReconciler
 * merges admin overrides on top of.
 *
 * `wlsb_manage_access` and the administrator grant for it are protected, which
 * together guarantee the administrator can always reach the management screens.
 */
final class Catalog
{
    public const GROUP_ACCESS = 'wlsb_access';

    private function __construct() {}

    public static function capabilities(): CapabilityRegistry
    {
        $registry = new CapabilityRegistry();

        $registry->addGroup(new CapabilityGroup(
            self::GROUP_ACCESS,
            __('Access management', 'wlsb-access'),
            10,
        ));

        $registry->add(new Capability(
            Cap::MANAGE_ACCESS,
            __('Manage roles & access', 'wlsb-access'),
            self::GROUP_ACCESS,
            protected: true,
            description: __('Create and edit roles, toggle capabilities, and configure access rules.', 'wlsb-access'),
        ));

        $registry->add(new Capability(
            Cap::MANAGE_APPROVALS,
            __('Manage approval workflows', 'wlsb-access'),
            self::GROUP_ACCESS,
            description: __('Configure which roles require approval and by whom.', 'wlsb-access'),
        ));

        $registry->add(new Capability(
            Cap::APPROVE_REQUESTS,
            __('Approve registrations', 'wlsb-access'),
            self::GROUP_ACCESS,
            description: __('Approve or reject pending registration requests.', 'wlsb-access'),
        ));

        return $registry;
    }

    public static function roles(): RoleRegistry
    {
        $registry = new RoleRegistry();

        $registry->addBlueprint(new RoleBlueprint(
            Role::PENDING,
            __('Pending approval', 'wlsb-access'),
            capabilities: [],
            protected: true,
        ));

        // Administrators keep every management capability; manage-access is
        // protected so it can never be toggled off (self-lockout guard).
        $registry->addGrant(new CapabilityGrant('administrator', Cap::MANAGE_ACCESS, protected: true));
        $registry->addGrant(new CapabilityGrant('administrator', Cap::MANAGE_APPROVALS));
        $registry->addGrant(new CapabilityGrant('administrator', Cap::APPROVE_REQUESTS));

        return $registry;
    }
}

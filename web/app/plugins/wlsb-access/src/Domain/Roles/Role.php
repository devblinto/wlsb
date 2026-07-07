<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * Canonical slugs for plugin-owned roles.
 */
final class Role
{
    /**
     * Zero-capability holding role. Newly-registered users are parked here until
     * their email is verified and any approval completes, so an auth-guard gap
     * or plugin deactivation can never leave a user with real capabilities.
     */
    public const PENDING = 'wlsb_pending';

    private function __construct() {}
}

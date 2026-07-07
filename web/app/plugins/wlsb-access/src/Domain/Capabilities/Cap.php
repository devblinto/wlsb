<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Capabilities;

/**
 * Canonical keys for the capabilities the plugin defines, so the rest of the
 * codebase references constants instead of magic strings.
 */
final class Cap
{
    /** Gate for the role/capability/access management screens. Self-lockout-protected. */
    public const MANAGE_ACCESS = 'wlsb_manage_access';

    /** Configure approval workflows. */
    public const MANAGE_APPROVALS = 'wlsb_manage_approvals';

    /** Approve or reject pending registrations. */
    public const APPROVE_REQUESTS = 'wlsb_approve_requests';

    private function __construct() {}
}

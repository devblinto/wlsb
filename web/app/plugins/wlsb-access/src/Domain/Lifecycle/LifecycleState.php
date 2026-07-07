<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Lifecycle;

/**
 * The account lifecycle. Stored as a string in user meta (`wlsb_access_status`).
 *
 * Token expiry is deliberately NOT a state — an expired verification token
 * leaves the user in PendingEmailVerification; only the token is stale.
 */
enum LifecycleState: string
{
    case PendingEmailVerification = 'pending_email_verification';
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * Only fully-cleared accounts may authenticate. Everything else is blocked
     * at login (see UserLifecycleManager::assertCanAuthenticate).
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Auth;

use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use WP_Error;
use WP_User;

/**
 * Blocks non-active accounts from authenticating.
 *
 * Primary gate is the `wp_authenticate_user` filter (runs after the password is
 * verified, covering username- and email-based login); the application-password
 * gate closes the REST/programmatic path. Unmanaged users (no plugin status,
 * e.g. pre-existing admins) are never affected.
 */
final class AuthenticationGuard
{
    public function __construct(private readonly UserLifecycleManager $lifecycle) {}

    /**
     * @param WP_User|WP_Error|mixed $user
     * @return WP_User|WP_Error|mixed
     */
    public function filter($user, string $password = '')
    {
        if (! $user instanceof WP_User) {
            return $user;
        }

        $blocked = $this->lifecycle->assertCanAuthenticate((int) $user->ID);

        if ($blocked === null) {
            return $user;
        }

        return new WP_Error($this->code($blocked), $this->message($blocked));
    }

    /**
     * Filter for `wp_authenticate_application_password`.
     *
     * @param WP_User|WP_Error|null|mixed $input
     * @param WP_User|mixed               $user
     * @return WP_User|WP_Error|null|mixed
     */
    public function filterApplicationPassword($input, $user)
    {
        if ($user instanceof WP_User) {
            $blocked = $this->lifecycle->assertCanAuthenticate((int) $user->ID);

            if ($blocked !== null) {
                return new WP_Error($this->code($blocked), $this->message($blocked));
            }
        }

        return $input;
    }

    private function code(LifecycleState $state): string
    {
        return match ($state) {
            LifecycleState::PendingEmailVerification => 'wlsb_email_unverified',
            LifecycleState::PendingApproval => 'wlsb_pending_approval',
            LifecycleState::Rejected => 'wlsb_registration_rejected',
            LifecycleState::Suspended => 'wlsb_account_suspended',
            default => 'wlsb_login_blocked',
        };
    }

    private function message(LifecycleState $state): string
    {
        $message = match ($state) {
            LifecycleState::PendingEmailVerification => __('Your email address is not verified yet. Please check your inbox for the verification link.', 'wlsb-access'),
            LifecycleState::PendingApproval => __('Your account is awaiting approval. You will be notified once it is reviewed.', 'wlsb-access'),
            LifecycleState::Rejected => __('Your registration was not approved.', 'wlsb-access'),
            LifecycleState::Suspended => __('Your account has been suspended.', 'wlsb-access'),
            default => __('You cannot sign in at this time.', 'wlsb-access'),
        };

        /** @var string $filtered */
        $filtered = apply_filters('wlsb/login/blocked_message', $message, $state->value);

        return $filtered;
    }
}

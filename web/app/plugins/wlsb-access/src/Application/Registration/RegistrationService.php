<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;

/**
 * Handles self-service registration: validate input, create the account in the
 * zero-capability holding role, and kick off email verification.
 *
 * Two security properties are enforced here:
 *  - the requested role must be self-selectable in the workflow config (and the
 *    administrator role is never self-selectable), preventing privilege
 *    escalation via signup;
 *  - an already-registered email is handled generically (no exception, no new
 *    user, an optional throttled resend) so registration cannot be used to
 *    enumerate accounts.
 */
final class RegistrationService
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly WorkflowConfigStore $workflow,
        private readonly EmailVerificationService $verification,
        private readonly EventLogger $log,
        private readonly string $holdingRole,
    ) {}

    /**
     * @throws RegistrationException on invalid input
     */
    public function register(RegistrationInput $input): void
    {
        $email = trim($input->email);
        $login = trim($input->login);

        if (! $this->isValidEmail($email)) {
            throw new RegistrationException(__('Please enter a valid email address.', 'wlsb-access'));
        }

        if ($login === '') {
            throw new RegistrationException(__('Please choose a username.', 'wlsb-access'));
        }

        if ($input->password === '') {
            throw new RegistrationException(__('Please choose a password.', 'wlsb-access'));
        }

        if (! $this->workflow->load()->isSelfSelectable($input->role)) {
            throw new RegistrationException(__('That role is not available for registration.', 'wlsb-access'));
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null) {
            // Anti-enumeration: never reveal that the email is taken. Nudge a
            // still-unverified account along, otherwise do nothing.
            if ($this->users->getStatus($existing) === LifecycleState::PendingEmailVerification) {
                $this->verification->resend($existing);
            }

            return;
        }

        if ($this->users->findByLogin($login) !== null) {
            throw new RegistrationException(__('That username is already taken.', 'wlsb-access'));
        }

        $userId = $this->users->create($login, $email, $input->password, $this->holdingRole);
        $this->users->setStatus($userId, LifecycleState::PendingEmailVerification);
        $this->users->setRequestedRole($userId, $input->role);

        $this->log->log(new Event(
            EventType::RegistrationCreated,
            'user',
            $userId,
            userId: $userId,
            context: ['role' => $input->role],
        ));

        $this->verification->issueToken($userId, $email);
    }

    private function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

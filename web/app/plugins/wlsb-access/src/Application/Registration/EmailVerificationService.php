<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

use Wlsb\Access\Application\Approval\ApprovalOpener;
use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Domain\Clock\Clock;
use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Tokens\TokenGenerator;
use Wlsb\Access\Domain\Tokens\TokenHasher;
use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Users\UserMeta;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;

/**
 * Issues, resends, and consumes email-verification tokens.
 *
 * Only the token HASH is stored; tokens are single-use (consumed on success),
 * expiring, throttled on resend, and compared in constant time. On successful
 * verification the user is advanced to Active (or PendingApproval when their
 * requested role requires approval), and the matching email is sent.
 */
final class EmailVerificationService
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly UserLifecycleManager $lifecycle,
        private readonly WorkflowConfigStore $workflow,
        private readonly TokenGenerator $generator,
        private readonly TokenHasher $hasher,
        private readonly NotificationService $notifications,
        private readonly RegistrationUrls $urls,
        private readonly Clock $clock,
        private readonly EventLogger $log,
        private readonly int $ttlSeconds = 86400,
        private readonly int $resendThrottleSeconds = 300,
        private readonly ?ApprovalOpener $approvals = null,
    ) {}

    public function issueToken(int $userId, string $email): void
    {
        $raw = $this->generator->generate();
        $now = $this->clock->now()->getTimestamp();

        $this->users->setMeta($userId, UserMeta::TOKEN_HASH, $this->hasher->hash($raw));
        $this->users->setMeta($userId, UserMeta::TOKEN_EXPIRES, (string) ($now + $this->ttlSeconds));
        $this->users->setMeta($userId, UserMeta::TOKEN_CREATED, (string) $now);

        $this->notifications->sendVerification($userId, $email, $this->urls->verify($userId, $raw));

        $this->log->log(new Event(EventType::EmailTokenIssued, 'user', $userId, userId: $userId));
    }

    public function resend(int $userId): ResendResult
    {
        if ($this->users->getStatus($userId) !== LifecycleState::PendingEmailVerification) {
            return ResendResult::NotPending;
        }

        $created = (int) ($this->users->getMeta($userId, UserMeta::TOKEN_CREATED) ?? '0');
        $now = $this->clock->now()->getTimestamp();

        if ($created > 0 && ($now - $created) < $this->resendThrottleSeconds) {
            return ResendResult::Throttled;
        }

        $email = $this->users->getEmail($userId);
        if ($email === null) {
            return ResendResult::NotPending;
        }

        $this->issueToken($userId, $email);

        return ResendResult::Sent;
    }

    public function verify(int $userId, string $rawToken): VerificationResult
    {
        if ($this->users->getStatus($userId) !== LifecycleState::PendingEmailVerification) {
            return VerificationResult::AlreadyVerified;
        }

        $storedHash = $this->users->getMeta($userId, UserMeta::TOKEN_HASH);
        if ($storedHash === null) {
            return VerificationResult::Invalid;
        }

        $expires = (int) ($this->users->getMeta($userId, UserMeta::TOKEN_EXPIRES) ?? '0');
        if ($this->clock->now()->getTimestamp() > $expires) {
            return VerificationResult::Expired;
        }

        if (! $this->hasher->verify($rawToken, $storedHash)) {
            return VerificationResult::Invalid;
        }

        // Consume the token (single use).
        $this->users->deleteMeta($userId, UserMeta::TOKEN_HASH);
        $this->users->deleteMeta($userId, UserMeta::TOKEN_EXPIRES);
        $this->users->deleteMeta($userId, UserMeta::TOKEN_CREATED);

        $this->log->log(new Event(EventType::EmailVerified, 'user', $userId, userId: $userId));

        $this->advance($userId);

        return VerificationResult::Verified;
    }

    private function advance(int $userId): void
    {
        $email = (string) $this->users->getEmail($userId);
        $role = $this->users->getRequestedRole($userId);

        if ($role !== null && $this->workflow->load()->requiresApproval($role)) {
            $this->lifecycle->transition($userId, LifecycleState::PendingApproval);
            $this->notifications->sendAwaitingApproval($userId, $email);
            $this->approvals?->open($userId, $role);

            return;
        }

        $this->lifecycle->transition($userId, LifecycleState::Active);
        $this->notifications->sendWelcome($userId, $email, $this->urls->login());
    }
}

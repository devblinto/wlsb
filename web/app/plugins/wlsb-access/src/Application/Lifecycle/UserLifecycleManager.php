<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Lifecycle;

use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Lifecycle\LifecycleTransitions;
use Wlsb\Access\Domain\Users\UserDirectory;

/**
 * The single authority for account status changes. Every transition validates
 * the edge against the transition table and applies role/session side-effects
 * atomically, so the stored status and the user's actual WordPress role can
 * never drift.
 *
 * The requested real role is granted only on the move to Active; suspending or
 * rejecting a user strips them back to the zero-capability holding role and
 * destroys their sessions, so a live cookie can never outlive activation.
 */
final class UserLifecycleManager
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly string $holdingRole,
        private readonly EventLogger $log,
    ) {}

    /**
     * @param array<string, mixed> $context may include 'actor_id' and 'reason'
     *
     * @throws IllegalTransition
     */
    public function transition(int $userId, LifecycleState $to, array $context = []): void
    {
        $from = $this->users->getStatus($userId);

        if ($from === null) {
            throw new IllegalTransition(sprintf('User %d has no lifecycle status.', $userId));
        }

        if (! LifecycleTransitions::isAllowed($from, $to)) {
            throw new IllegalTransition(sprintf(
                'Transition %s -> %s is not allowed.',
                $from->value,
                $to->value,
            ));
        }

        $this->users->setStatus($userId, $to);
        $this->applySideEffects($userId, $to);

        $actorId = isset($context['actor_id']) ? (int) $context['actor_id'] : null;

        $this->log->log(new Event(
            EventType::StatusChanged,
            'user',
            $userId,
            userId: $userId,
            actorId: $actorId,
            context: [
                'from' => $from->value,
                'to' => $to->value,
                'reason' => $context['reason'] ?? null,
            ],
        ));
    }

    /**
     * Returns the blocking state when the user may NOT authenticate, or null when
     * they may (active, or an unmanaged user with no plugin status).
     */
    public function assertCanAuthenticate(int $userId): ?LifecycleState
    {
        $status = $this->users->getStatus($userId);

        if ($status === null || $status->canAuthenticate()) {
            return null;
        }

        return $status;
    }

    private function applySideEffects(int $userId, LifecycleState $to): void
    {
        if ($to === LifecycleState::Active) {
            $requested = $this->users->getRequestedRole($userId);

            if ($requested !== null && $requested !== '') {
                $this->users->setRole($userId, $requested);
            }

            return;
        }

        if ($to === LifecycleState::Suspended || $to === LifecycleState::Rejected) {
            $this->users->setRole($userId, $this->holdingRole);
            $this->users->destroySessions($userId);
        }
    }
}

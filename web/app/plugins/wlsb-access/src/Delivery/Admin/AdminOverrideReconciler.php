<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Application\Approval\ApprovalService;
use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Users\UserDirectory;

/**
 * Keeps the plugin status in sync when an admin changes a user's role directly
 * (e.g. from the Users screen), bypassing the approval flow.
 *
 * If a still-pending user is given a real role, their status is reconciled to
 * active and any open approval request is cancelled. Our own activation sets the
 * status to active *before* assigning the role, so this hook is a no-op during
 * the normal flow (no recursion).
 */
final class AdminOverrideReconciler
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly ApprovalService $approvals,
        private readonly EventLogger $log,
        private readonly string $holdingRole,
    ) {}

    /**
     * @param array<int, string> $oldRoles
     */
    public function onRoleChanged(int $userId, string $newRole, array $oldRoles = []): void
    {
        $status = $this->users->getStatus($userId);

        $isPending = $status === LifecycleState::PendingEmailVerification
            || $status === LifecycleState::PendingApproval;

        if (! $isPending || $newRole === '' || $newRole === $this->holdingRole) {
            return;
        }

        $actorId = get_current_user_id() ?: null;

        $this->users->setStatus($userId, LifecycleState::Active);
        $this->approvals->cancelOpenFor($userId, $actorId);

        $this->log->log(new Event(
            EventType::StatusChanged,
            'user',
            $userId,
            userId: $userId,
            actorId: $actorId,
            context: ['reason' => 'admin_override', 'to' => LifecycleState::Active->value, 'role' => $newRole],
        ));
    }
}

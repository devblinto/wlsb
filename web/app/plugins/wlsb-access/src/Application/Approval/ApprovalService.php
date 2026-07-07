<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Approval;

use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Application\Registration\RegistrationUrls;
use Wlsb\Access\Domain\Approval\ApprovalPolicy;
use Wlsb\Access\Domain\Approval\ApprovalRepository;
use Wlsb\Access\Domain\Approval\ApprovalRequest;
use Wlsb\Access\Domain\Approval\ApprovalStep;
use Wlsb\Access\Domain\Approval\ApproverType;
use Wlsb\Access\Domain\Approval\RequestStatus;
use Wlsb\Access\Domain\Approval\StepStatus;
use Wlsb\Access\Domain\Approval\WorkflowResolver;
use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;

/**
 * The approval engine: opens requests, and applies approve/reject decisions.
 *
 * Every mutating action runs inside a repository transaction with a row lock on
 * the request, so two approvers acting at once are serialised — the loser sees
 * an already-decided step and produces no double activation or double email. On
 * the final approval the user is activated; a rejection rejects them. The chain
 * is walked step-by-step, so multi-step workflows work with no schema change.
 */
final class ApprovalService implements ApprovalOpener
{
    public function __construct(
        private readonly ApprovalRepository $repository,
        private readonly WorkflowResolver $resolver,
        private readonly WorkflowConfigStore $workflow,
        private readonly ApprovalPolicy $policy,
        private readonly UserLifecycleManager $lifecycle,
        private readonly UserDirectory $users,
        private readonly NotificationService $notifications,
        private readonly RegistrationUrls $urls,
        private readonly string $reviewUrl,
        private readonly EventLogger $log,
    ) {}

    public function open(int $userId, string $role): void
    {
        $this->openRequest($userId, $role);
    }

    /**
     * @return list<ApprovalRequest>
     */
    public function listPending(): array
    {
        return $this->repository->listPending();
    }

    /**
     * Close a user's open request as cancelled — used when an admin activates the
     * user out-of-band (e.g. by assigning a role directly).
     */
    public function cancelOpenFor(int $userId, ?int $actorId): void
    {
        $this->repository->transactionally(function () use ($userId, $actorId): void {
            $request = $this->repository->findOpenByUser($userId);
            if ($request !== null) {
                $this->repository->closeRequest($request->id, RequestStatus::Cancelled, $actorId);
            }
        });
    }

    public function openRequest(int $userId, string $role): ApprovalRequest
    {
        $existing = $this->repository->findOpenByUser($userId);
        if ($existing !== null) {
            return $existing; // idempotent
        }

        $config = $this->workflow->load();
        $steps = $this->resolver->resolve($config, $role);
        $request = $this->repository->create($userId, $role, $config->version(), $steps);

        $this->log->log(new Event(EventType::ApprovalRequestOpened, 'request', $request->id, userId: $userId, context: ['role' => $role]));
        $this->notifyApprovers($request);

        return $request;
    }

    public function approve(int $requestId, int $actorId, ?string $note = null): ApprovalDecision
    {
        return $this->repository->transactionally(function () use ($requestId, $actorId, $note): ApprovalDecision {
            $request = $this->guardActionable($requestId, $actorId);
            if (! $request instanceof ApprovalRequest) {
                return $request;
            }

            $step = $request->currentStep();
            $this->repository->updateStep($step->id, StepStatus::Approved, $actorId, $note);
            $this->log->log(new Event(EventType::ApprovalStepApproved, 'step', $step->id, userId: $request->userId, actorId: $actorId));

            $next = $request->nextPendingStepAfter($step->order);
            if ($next !== null) {
                $this->repository->setCurrentStep($requestId, $next->order);

                return ApprovalDecision::Advanced;
            }

            $this->repository->closeRequest($requestId, RequestStatus::Approved, $actorId);
            $this->lifecycle->transition($request->userId, LifecycleState::Active, ['actor_id' => $actorId]);
            $this->log->log(new Event(EventType::ApprovalRequestApproved, 'request', $requestId, userId: $request->userId, actorId: $actorId));
            $this->notifications->sendWelcome($request->userId, (string) $this->users->getEmail($request->userId), $this->urls->login());

            return ApprovalDecision::Approved;
        });
    }

    public function reject(int $requestId, int $actorId, ?string $note = null): ApprovalDecision
    {
        return $this->repository->transactionally(function () use ($requestId, $actorId, $note): ApprovalDecision {
            $request = $this->guardActionable($requestId, $actorId);
            if (! $request instanceof ApprovalRequest) {
                return $request;
            }

            $step = $request->currentStep();
            $this->repository->updateStep($step->id, StepStatus::Rejected, $actorId, $note);
            $this->repository->closeRequest($requestId, RequestStatus::Rejected, $actorId);
            $this->lifecycle->transition($request->userId, LifecycleState::Rejected, ['actor_id' => $actorId]);
            $this->log->log(new Event(EventType::ApprovalRequestRejected, 'request', $requestId, userId: $request->userId, actorId: $actorId));
            $this->notifications->sendRejection($request->userId, (string) $this->users->getEmail($request->userId));

            return ApprovalDecision::Rejected;
        });
    }

    /**
     * Locks and validates the request/current step, returning the request when
     * the actor may act, or the terminal decision otherwise.
     */
    private function guardActionable(int $requestId, int $actorId): ApprovalRequest|ApprovalDecision
    {
        $request = $this->repository->findForUpdate($requestId);

        if ($request === null || ! $request->isOpen()) {
            return ApprovalDecision::AlreadyClosed;
        }

        $step = $request->currentStep();
        if ($step === null || $step->status !== StepStatus::Pending) {
            return ApprovalDecision::AlreadyClosed;
        }

        if (! $this->actorCanAct($step, $actorId)) {
            return ApprovalDecision::Forbidden;
        }

        return $request;
    }

    private function actorCanAct(ApprovalStep $step, int $actorId): bool
    {
        $roles = $this->users->rolesOf($actorId);

        return $this->policy->canAct($step, $roles, $actorId, in_array('administrator', $roles, true));
    }

    private function notifyApprovers(ApprovalRequest $request): void
    {
        $step = $request->currentStep();
        if ($step === null) {
            return;
        }

        foreach ($this->approverIds($step) as $approverId) {
            $email = $this->users->getEmail($approverId);
            if ($email !== null) {
                $this->notifications->sendApproverNotice($approverId, $email, $this->reviewUrl);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function approverIds(ApprovalStep $step): array
    {
        if ($step->approverType === ApproverType::User && $step->approverUserId !== null) {
            return [$step->approverUserId];
        }

        if ($step->approverType === ApproverType::Role && $step->approverRole !== null) {
            $ids = $this->users->usersWithRole($step->approverRole);

            // A deleted or empty approver role must not silently swallow the
            // request — fall back to administrators.
            return $ids !== [] ? $ids : $this->users->usersWithRole('administrator');
        }

        return $this->users->usersWithRole('administrator');
    }
}

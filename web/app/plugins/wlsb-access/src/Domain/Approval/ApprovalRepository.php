<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * Persistence port for approval requests and their steps.
 *
 * `transactionally()` wraps mutating work in a DB transaction; `findForUpdate()`
 * row-locks a request so concurrent approvers are serialised (the loser sees the
 * already-decided step and no double side-effect occurs).
 */
interface ApprovalRepository
{
    public function findOpenByUser(int $userId): ?ApprovalRequest;

    public function find(int $requestId): ?ApprovalRequest;

    public function findForUpdate(int $requestId): ?ApprovalRequest;

    /**
     * @param list<StepDefinition> $steps
     */
    public function create(int $userId, string $role, int $workflowVersion, array $steps): ApprovalRequest;

    public function updateStep(int $stepId, StepStatus $status, int $actedBy, ?string $note): void;

    public function setCurrentStep(int $requestId, int $order): void;

    public function closeRequest(int $requestId, RequestStatus $status, ?int $decidedBy): void;

    /**
     * @return list<ApprovalRequest>
     */
    public function listPending(): array;

    /**
     * @template T
     *
     * @param callable():T $fn
     * @return T
     */
    public function transactionally(callable $fn): mixed;
}

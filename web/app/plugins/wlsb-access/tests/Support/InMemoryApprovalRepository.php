<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Approval\ApprovalRequest;
use Wlsb\Access\Domain\Approval\ApprovalStep;
use Wlsb\Access\Domain\Approval\RequestStatus;
use Wlsb\Access\Domain\Approval\StepStatus;

/**
 * In-memory ApprovalRepository double.
 */
final class InMemoryApprovalRepository implements \Wlsb\Access\Domain\Approval\ApprovalRepository
{
    /** @var array<int, ApprovalRequest> */
    private array $requests = [];

    private int $nextRequestId = 1;

    private int $nextStepId = 1;

    public function findOpenByUser(int $userId): ?ApprovalRequest
    {
        foreach ($this->requests as $request) {
            if ($request->userId === $userId && $request->isOpen()) {
                return $request;
            }
        }

        return null;
    }

    public function find(int $requestId): ?ApprovalRequest
    {
        return $this->requests[$requestId] ?? null;
    }

    public function findForUpdate(int $requestId): ?ApprovalRequest
    {
        return $this->find($requestId);
    }

    public function create(int $userId, string $role, int $workflowVersion, array $steps): ApprovalRequest
    {
        $requestId = $this->nextRequestId++;

        $stepObjects = [];
        foreach ($steps as $definition) {
            $stepObjects[] = new ApprovalStep(
                $this->nextStepId++,
                $requestId,
                $definition->order,
                $definition->approverType,
                $definition->approverRole,
                $definition->approverUserId,
                StepStatus::Pending,
            );
        }

        $firstOrder = $stepObjects === [] ? 1 : $stepObjects[0]->order;

        $request = new ApprovalRequest(
            $requestId,
            $userId,
            $role,
            $workflowVersion,
            RequestStatus::Pending,
            $firstOrder,
            $stepObjects,
        );

        $this->requests[$requestId] = $request;

        return $request;
    }

    public function updateStep(int $stepId, StepStatus $status, int $actedBy, ?string $note): void
    {
        foreach ($this->requests as $requestId => $request) {
            $steps = array_map(
                fn(ApprovalStep $step): ApprovalStep => $step->id === $stepId
                    ? new ApprovalStep($step->id, $step->requestId, $step->order, $step->approverType, $step->approverRole, $step->approverUserId, $status, $actedBy, $note)
                    : $step,
                $request->steps,
            );
            $this->requests[$requestId] = $this->withSteps($request, $steps);
        }
    }

    public function setCurrentStep(int $requestId, int $order): void
    {
        $request = $this->requests[$requestId] ?? null;
        if ($request !== null) {
            $this->requests[$requestId] = new ApprovalRequest(
                $request->id,
                $request->userId,
                $request->requestedRole,
                $request->workflowVersion,
                $request->status,
                $order,
                $request->steps,
            );
        }
    }

    public function closeRequest(int $requestId, RequestStatus $status, ?int $decidedBy): void
    {
        $request = $this->requests[$requestId] ?? null;
        if ($request !== null) {
            $this->requests[$requestId] = new ApprovalRequest(
                $request->id,
                $request->userId,
                $request->requestedRole,
                $request->workflowVersion,
                $status,
                $request->currentStepOrder,
                $request->steps,
            );
        }
    }

    public function listPending(): array
    {
        return array_values(array_filter($this->requests, static fn(ApprovalRequest $r): bool => $r->isOpen()));
    }

    public function transactionally(callable $fn): mixed
    {
        return $fn();
    }

    /**
     * @param list<ApprovalStep> $steps
     */
    private function withSteps(ApprovalRequest $request, array $steps): ApprovalRequest
    {
        return new ApprovalRequest(
            $request->id,
            $request->userId,
            $request->requestedRole,
            $request->workflowVersion,
            $request->status,
            $request->currentStepOrder,
            $steps,
        );
    }
}

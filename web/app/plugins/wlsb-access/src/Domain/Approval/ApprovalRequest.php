<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * A persisted approval request (one row of wlsb_approval_requests) with its
 * ordered steps.
 */
final class ApprovalRequest
{
    /**
     * @param list<ApprovalStep> $steps
     */
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $requestedRole,
        public readonly int $workflowVersion,
        public readonly RequestStatus $status,
        public readonly int $currentStepOrder,
        public readonly array $steps = [],
    ) {}

    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Pending;
    }

    public function currentStep(): ?ApprovalStep
    {
        foreach ($this->steps as $step) {
            if ($step->order === $this->currentStepOrder) {
                return $step;
            }
        }

        return null;
    }

    public function nextPendingStepAfter(int $order): ?ApprovalStep
    {
        $candidates = array_filter(
            $this->steps,
            static fn(ApprovalStep $step): bool => $step->order > $order && $step->status === StepStatus::Pending,
        );

        usort($candidates, static fn(ApprovalStep $a, ApprovalStep $b): int => $a->order <=> $b->order);

        return $candidates[0] ?? null;
    }
}

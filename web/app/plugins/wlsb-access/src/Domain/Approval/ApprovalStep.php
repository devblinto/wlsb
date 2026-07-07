<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * A persisted approval step (one row of wlsb_approval_steps).
 */
final class ApprovalStep
{
    public function __construct(
        public readonly int $id,
        public readonly int $requestId,
        public readonly int $order,
        public readonly ApproverType $approverType,
        public readonly ?string $approverRole,
        public readonly ?int $approverUserId,
        public readonly StepStatus $status,
        public readonly ?int $actedBy = null,
        public readonly ?string $note = null,
    ) {}
}

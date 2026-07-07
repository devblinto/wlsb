<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * A step as defined by the workflow config — the template snapshotted into a
 * concrete ApprovalStep row when a request is opened.
 */
final class StepDefinition
{
    public function __construct(
        public readonly int $order,
        public readonly ApproverType $approverType,
        public readonly ?string $approverRole = null,
        public readonly ?int $approverUserId = null,
    ) {}
}

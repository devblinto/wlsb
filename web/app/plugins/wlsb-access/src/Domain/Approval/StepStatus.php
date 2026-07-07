<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * Decision state of a single approval step.
 */
enum StepStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Skipped = 'skipped';
}

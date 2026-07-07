<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Approval;

/**
 * Outcome of an approve/reject action.
 */
enum ApprovalDecision
{
    case Approved;   // final step approved → user activated
    case Rejected;   // request rejected → user rejected
    case Advanced;   // a step approved, more steps remain
    case Forbidden;  // actor may not act on the current step
    case AlreadyClosed; // request already decided/closed (idempotent no-op)
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * Lifecycle of an approval request (the case). Stored as a string (VARCHAR)
 * rather than a native MySQL ENUM so new values never require an ALTER.
 */
enum RequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}

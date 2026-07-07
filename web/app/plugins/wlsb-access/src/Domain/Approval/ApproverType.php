<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * Whether a step is approvable by holders of a role or by one specific user.
 */
enum ApproverType: string
{
    case Role = 'role';
    case User = 'user';
}

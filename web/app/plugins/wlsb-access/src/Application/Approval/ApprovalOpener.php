<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Approval;

/**
 * Narrow seam for the verification flow to open an approval request without
 * depending on the full ApprovalService — keeps EmailVerificationService easy to
 * test in isolation.
 */
interface ApprovalOpener
{
    public function open(int $userId, string $role): void;
}

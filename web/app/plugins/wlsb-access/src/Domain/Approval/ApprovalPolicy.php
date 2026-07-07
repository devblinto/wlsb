<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

/**
 * Decides whether an actor may act on a given approval step.
 *
 * Administrators are a universal fallback approver, so a deleted or empty
 * approver role can never permanently lock a request.
 */
final class ApprovalPolicy
{
    /**
     * @param list<string> $actorRoleSlugs
     */
    public function canAct(ApprovalStep $step, array $actorRoleSlugs, int $actorId, bool $isAdministrator): bool
    {
        if ($isAdministrator) {
            return true;
        }

        if ($step->approverType === ApproverType::Role) {
            return $step->approverRole !== null && in_array($step->approverRole, $actorRoleSlugs, true);
        }

        return $step->approverType === ApproverType::User && $step->approverUserId === $actorId;
    }
}

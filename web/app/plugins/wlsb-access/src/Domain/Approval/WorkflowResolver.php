<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Approval;

use Wlsb\Access\Domain\Workflow\WorkflowConfig;

/**
 * Turns a role's workflow config into an ordered list of approval steps.
 *
 * A role that requires approval but configures no steps defaults to a single
 * administrator-approval step, so "requires approval" is never a dead-end.
 */
final class WorkflowResolver
{
    /**
     * @return list<StepDefinition>
     */
    public function resolve(WorkflowConfig $config, string $role): array
    {
        $workflow = $config->forRole($role);

        if ($workflow === null || ! $workflow->requiresApproval) {
            return [];
        }

        $steps = [];
        foreach ($workflow->steps as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $steps[] = new StepDefinition(
                (int) ($raw['order'] ?? ($index + 1)),
                ApproverType::tryFrom((string) ($raw['approver_type'] ?? 'role')) ?? ApproverType::Role,
                isset($raw['approver_role']) ? (string) $raw['approver_role'] : null,
                isset($raw['approver_user_id']) ? (int) $raw['approver_user_id'] : null,
            );
        }

        if ($steps === []) {
            $steps[] = new StepDefinition(1, ApproverType::Role, 'administrator');
        }

        usort($steps, static fn(StepDefinition $a, StepDefinition $b): int => $a->order <=> $b->order);

        return $steps;
    }
}

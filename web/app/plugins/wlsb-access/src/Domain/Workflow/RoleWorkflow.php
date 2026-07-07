<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Workflow;

/**
 * Per-role registration/approval settings.
 *
 * `steps` (the ordered approval chain) is carried for forward-compatibility with
 * the Phase 3 approval engine but is unused in Phase 2, where `requiresApproval`
 * simply parks a verified user in PendingApproval until the engine ships.
 *
 * @phpstan-type StepArray array{order:int, approver_type:string, approver_role?:string, approver_user_id?:int}
 */
final class RoleWorkflow
{
    /**
     * @param list<array<string, mixed>> $steps
     */
    public function __construct(
        public readonly string $slug,
        public readonly bool $registerable = false,
        public readonly bool $selfSelectable = false,
        public readonly bool $requiresApproval = false,
        public readonly array $steps = [],
    ) {}
}

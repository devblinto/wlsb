<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Approval\ApproverType;
use Wlsb\Access\Domain\Approval\WorkflowResolver;
use Wlsb\Access\Domain\Workflow\RoleWorkflow;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;

test('a role without approval resolves to no steps', function (): void {
    $config = new WorkflowConfig(['subscriber' => new RoleWorkflow('subscriber', registerable: true, selfSelectable: true)]);

    expect((new WorkflowResolver())->resolve($config, 'subscriber'))->toBe([]);
});

test('an approval role with configured steps resolves them, ordered', function (): void {
    $config = new WorkflowConfig([
        'wlsb_vendor' => new RoleWorkflow('wlsb_vendor', registerable: true, selfSelectable: true, requiresApproval: true, steps: [
            ['order' => 2, 'approver_type' => 'user', 'approver_user_id' => 7],
            ['order' => 1, 'approver_type' => 'role', 'approver_role' => 'editor'],
        ]),
    ]);

    $steps = (new WorkflowResolver())->resolve($config, 'wlsb_vendor');

    expect($steps)->toHaveCount(2)
        ->and($steps[0]->order)->toBe(1)
        ->and($steps[0]->approverType)->toBe(ApproverType::Role)
        ->and($steps[0]->approverRole)->toBe('editor')
        ->and($steps[1]->approverType)->toBe(ApproverType::User)
        ->and($steps[1]->approverUserId)->toBe(7);
});

test('an approval role with no configured steps defaults to a single administrator step', function (): void {
    $config = new WorkflowConfig([
        'wlsb_vendor' => new RoleWorkflow('wlsb_vendor', registerable: true, selfSelectable: true, requiresApproval: true),
    ]);

    $steps = (new WorkflowResolver())->resolve($config, 'wlsb_vendor');

    expect($steps)->toHaveCount(1)
        ->and($steps[0]->order)->toBe(1)
        ->and($steps[0]->approverType)->toBe(ApproverType::Role)
        ->and($steps[0]->approverRole)->toBe('administrator');
});

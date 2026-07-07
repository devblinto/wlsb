<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Workflow\RoleWorkflow;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;

test('the default config lets subscribers self-register with no approval', function (): void {
    $config = WorkflowConfig::default();

    expect($config->isSelfSelectable('subscriber'))->toBeTrue()
        ->and($config->requiresApproval('subscriber'))->toBeFalse()
        ->and($config->selfSelectableRoles())->toContain('subscriber');
});

test('administrator can never be registerable or self-selectable, even if configured', function (): void {
    $config = new WorkflowConfig([
        'administrator' => new RoleWorkflow('administrator', registerable: true, selfSelectable: true),
    ]);

    expect($config->isSelfSelectable('administrator'))->toBeFalse()
        ->and($config->isRegisterable('administrator'))->toBeFalse()
        ->and($config->selfSelectableRoles())->not->toContain('administrator');
});

test('requiresApproval and self-selectability reflect the configuration', function (): void {
    $config = new WorkflowConfig([
        'wlsb_vendor' => new RoleWorkflow('wlsb_vendor', registerable: true, selfSelectable: true, requiresApproval: true),
        'editor' => new RoleWorkflow('editor', registerable: true, selfSelectable: false),
    ]);

    expect($config->requiresApproval('wlsb_vendor'))->toBeTrue()
        ->and($config->isSelfSelectable('wlsb_vendor'))->toBeTrue()
        ->and($config->isSelfSelectable('editor'))->toBeFalse()   // registerable but not self-selectable
        ->and($config->isRegisterable('editor'))->toBeTrue()
        ->and($config->requiresApproval('unknown_role'))->toBeFalse();
});

test('config serialises and rebuilds identically', function (): void {
    $config = new WorkflowConfig([
        'wlsb_vendor' => new RoleWorkflow('wlsb_vendor', registerable: true, selfSelectable: true, requiresApproval: true),
    ], version: 3);

    $rebuilt = WorkflowConfig::fromArray($config->toArray());

    expect($rebuilt->toArray())->toBe($config->toArray())
        ->and($rebuilt->version())->toBe(3)
        ->and($rebuilt->requiresApproval('wlsb_vendor'))->toBeTrue();
});

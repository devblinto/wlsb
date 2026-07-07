<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Workflow\RoleWorkflow;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Infrastructure\Workflow\OptionWorkflowConfigStore;

beforeEach(function (): void {
    $GLOBALS['wlsb_test_options'] = [];
});

test('returns the default config when the option is unset', function (): void {
    $store = new OptionWorkflowConfigStore('wlsb_access_workflow_config');

    expect($store->load()->isSelfSelectable('subscriber'))->toBeTrue();
});

test('persists and reloads a configuration', function (): void {
    $store = new OptionWorkflowConfigStore('wlsb_access_workflow_config');
    $store->save(new WorkflowConfig([
        'wlsb_vendor' => new RoleWorkflow('wlsb_vendor', registerable: true, selfSelectable: true, requiresApproval: true),
    ]));

    expect($store->load()->requiresApproval('wlsb_vendor'))->toBeTrue()
        ->and($store->load()->isSelfSelectable('subscriber'))->toBeFalse(); // replaced default
});

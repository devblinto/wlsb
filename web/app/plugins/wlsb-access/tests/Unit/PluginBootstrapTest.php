<?php

declare(strict_types=1);

use Wlsb\Access\Plugin;

beforeEach(function (): void {
    require_once __DIR__ . '/../wp-stubs.php';
    $GLOBALS['wlsb_test_hooks'] = ['actions' => [], 'filters' => [], 'activation' => [], 'deactivation' => []];
    $GLOBALS['wlsb_test_options'] = [];
});

test('the entry file boots the plugin and registers its lifecycle hooks', function (): void {
    require __DIR__ . '/../../wlsb-access.php';

    $hooks = $GLOBALS['wlsb_test_hooks'];

    expect($hooks['activation'])->toHaveCount(1)
        ->and($hooks['deactivation'])->toHaveCount(1)
        ->and($hooks['actions'])->toContain('plugins_loaded')
        ->and($hooks['actions'])->toContain('init');
});

test('register wires a container exposing the access services', function (): void {
    $plugin = Plugin::register(__DIR__ . '/../../wlsb-access.php');

    $capabilities = $plugin->container()->get(\Wlsb\Access\Domain\Capabilities\CapabilityRegistry::class);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($capabilities->has(\Wlsb\Access\Domain\Capabilities\Cap::MANAGE_ACCESS))->toBeTrue()
        ->and($plugin->container()->has(\Wlsb\Access\Application\Reconciliation::class))->toBeTrue();
});

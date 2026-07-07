<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\MatrixFormMapper;

$capabilityKeys = ['wlsb_manage_access', 'wlsb_approve_requests'];
$roleSlugs = ['administrator', 'editor'];

// administrator holds manage_access by default; nobody holds approve by default here.
$defaultState = [
    'administrator' => ['wlsb_manage_access' => true, 'wlsb_approve_requests' => false],
    'editor' => ['wlsb_manage_access' => false, 'wlsb_approve_requests' => false],
];

test('records a toggle only when the submitted state differs from the default', function () use ($roleSlugs, $capabilityKeys, $defaultState): void {
    // editor gets approve (new grant); administrator keeps manage_access (unchanged).
    $checked = [
        'administrator' => ['wlsb_manage_access' => '1'],
        'editor' => ['wlsb_approve_requests' => '1'],
    ];

    $toggles = (new MatrixFormMapper())->toToggles($checked, $roleSlugs, $capabilityKeys, $defaultState);

    expect($toggles)->toBe(['editor' => ['wlsb_approve_requests' => true]]);
});

test('an unchecked default-granted capability becomes an explicit revoke toggle', function () use ($roleSlugs, $capabilityKeys, $defaultState): void {
    // administrator's manage_access box is unchecked → toggle false.
    $checked = ['editor' => []];

    $toggles = (new MatrixFormMapper())->toToggles($checked, $roleSlugs, $capabilityKeys, $defaultState);

    expect($toggles)->toBe(['administrator' => ['wlsb_manage_access' => false]]);
});

test('nothing is recorded when the submission matches the defaults exactly', function () use ($roleSlugs, $capabilityKeys, $defaultState): void {
    $checked = ['administrator' => ['wlsb_manage_access' => '1']];

    $toggles = (new MatrixFormMapper())->toToggles($checked, $roleSlugs, $capabilityKeys, $defaultState);

    expect($toggles)->toBe([]);
});

test('only presented roles and capabilities are considered; junk input is ignored', function () use ($roleSlugs, $capabilityKeys, $defaultState): void {
    $checked = [
        'administrator' => ['wlsb_manage_access' => '1', 'injected_cap' => '1'],
        'ghost_role' => ['wlsb_manage_access' => '1'],
    ];

    $toggles = (new MatrixFormMapper())->toToggles($checked, $roleSlugs, $capabilityKeys, $defaultState);

    expect($toggles)->toBe([]);
});

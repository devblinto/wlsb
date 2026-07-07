<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleOverrides;
use Wlsb\Access\Infrastructure\Roles\OptionRoleOverrideRepository;

beforeEach(function (): void {
    require_once __DIR__ . '/../../../wp-stubs.php';
    $GLOBALS['wlsb_test_options'] = [];
});

test('returns empty overrides when the option is unset', function (): void {
    $repository = new OptionRoleOverrideRepository('wlsb_role_overrides');

    expect($repository->load()->toArray())->toBe((new RoleOverrides())->toArray());
});

test('persists overrides to the option and reads them back', function (): void {
    $repository = new OptionRoleOverrideRepository('wlsb_role_overrides');
    $overrides = new RoleOverrides(
        customRoles: [new RoleBlueprint('vendor', 'Vendor', ['read'])],
        capToggles: ['administrator' => ['wlsb_manage_approvals' => false]],
        tombstones: ['old_role'],
    );

    $repository->save($overrides);

    expect($GLOBALS['wlsb_test_options']['wlsb_role_overrides'])->toBeArray()
        ->and($repository->load()->toArray())->toBe($overrides->toArray());
});

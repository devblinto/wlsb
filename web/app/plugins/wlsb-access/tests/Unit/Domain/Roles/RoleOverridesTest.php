<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleOverrides;

test('an empty overrides set exposes empty deltas', function (): void {
    $overrides = new RoleOverrides();

    expect($overrides->customRoles())->toBe([])
        ->and($overrides->tombstones())->toBe([])
        ->and($overrides->togglesFor('vendor'))->toBe([])
        ->and($overrides->renameFor('vendor'))->toBeNull()
        ->and($overrides->isTombstoned('vendor'))->toBeFalse();
});

test('accessors expose the configured deltas', function (): void {
    $overrides = new RoleOverrides(
        customRoles: [new RoleBlueprint('vendor', 'Vendor', ['read'])],
        capToggles: ['administrator' => ['wlsb_manage_approvals' => false], 'editor' => ['wlsb_approve_requests' => true]],
        renames: ['contributor' => 'Author Lite'],
        tombstones: ['old_role'],
    );

    expect($overrides->customRoles()[0]->slug)->toBe('vendor')
        ->and($overrides->togglesFor('administrator'))->toBe(['wlsb_manage_approvals' => false])
        ->and($overrides->togglesFor('editor'))->toBe(['wlsb_approve_requests' => true])
        ->and($overrides->renameFor('contributor'))->toBe('Author Lite')
        ->and($overrides->isTombstoned('old_role'))->toBeTrue();
});

test('serialises to an array and rebuilds identically', function (): void {
    $overrides = new RoleOverrides(
        customRoles: [new RoleBlueprint('vendor', 'Vendor', ['read', 'edit_posts'], protected: false)],
        capToggles: ['administrator' => ['wlsb_manage_approvals' => false]],
        renames: ['contributor' => 'Author Lite'],
        tombstones: ['old_role'],
    );

    $rebuilt = RoleOverrides::fromArray($overrides->toArray());

    expect($rebuilt->toArray())->toBe($overrides->toArray())
        ->and($rebuilt->customRoles()[0]->capabilities)->toBe(['read', 'edit_posts'])
        ->and($rebuilt->togglesFor('administrator'))->toBe(['wlsb_manage_approvals' => false]);
});

test('fromArray tolerates missing keys and non-arrays', function (): void {
    $overrides = RoleOverrides::fromArray(['tombstones' => ['x']]);

    expect($overrides->tombstones())->toBe(['x'])
        ->and($overrides->customRoles())->toBe([])
        ->and($overrides->togglesFor('any'))->toBe([]);

    expect(RoleOverrides::fromArray([])->toArray())->toBe((new RoleOverrides())->toArray());
});

<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleOverrides;

test('withCapToggles replaces the toggles while preserving other deltas', function (): void {
    $original = new RoleOverrides(
        customRoles: [new RoleBlueprint('vendor', 'Vendor', ['read'])],
        capToggles: ['administrator' => ['wlsb_manage_approvals' => false]],
        renames: ['staff' => 'Team'],
        tombstones: ['old_role'],
    );

    $updated = $original->withCapToggles(['editor' => ['wlsb_approve_requests' => true]]);

    expect($updated->allToggles())->toBe(['editor' => ['wlsb_approve_requests' => true]])
        ->and($updated->customRoles()[0]->slug)->toBe('vendor')
        ->and($updated->renameFor('staff'))->toBe('Team')
        ->and($updated->isTombstoned('old_role'))->toBeTrue()
        // original untouched (immutability)
        ->and($original->allToggles())->toBe(['administrator' => ['wlsb_manage_approvals' => false]]);
});

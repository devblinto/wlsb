<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Capabilities\Capability;
use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;
use Wlsb\Access\Domain\Roles\CapabilityGrant;
use Wlsb\Access\Domain\Roles\EffectiveRole;
use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleOverrides;
use Wlsb\Access\Domain\Roles\RoleReconciler;
use Wlsb\Access\Domain\Roles\RoleRegistry;
use Wlsb\Access\Tests\Support\InMemoryRolesGateway;

function makeRoleReconciler(): RoleReconciler
{
    $caps = new CapabilityRegistry();
    $caps->add(new Capability('wlsb_manage_access', 'Manage access', 'access', protected: true));
    $caps->add(new Capability('wlsb_manage_approvals', 'Manage approvals', 'access'));
    $caps->add(new Capability('wlsb_approve_requests', 'Approve requests', 'access'));
    $caps->add(new Capability('wlsb_core', 'Core', 'access', protected: true));

    $roles = new RoleRegistry();
    $roles->addBlueprint(new RoleBlueprint('wlsb_pending', 'Pending approval', [], protected: true));
    $roles->addBlueprint(new RoleBlueprint('staff', 'Staff', ['read', 'wlsb_core']));
    $roles->addGrant(new CapabilityGrant('administrator', 'wlsb_manage_access', protected: true));
    $roles->addGrant(new CapabilityGrant('administrator', 'wlsb_manage_approvals'));

    return new RoleReconciler($roles, $caps);
}

/** @param list<EffectiveRole> $roles */
function roleBySlug(array $roles, string $slug): ?EffectiveRole
{
    foreach ($roles as $role) {
        if ($role->slug === $slug) {
            return $role;
        }
    }

    return null;
}

// --- effectiveOwnedRoles --------------------------------------------------

test('effective owned roles include the defaults when there are no overrides', function (): void {
    $roles = makeRoleReconciler()->effectiveOwnedRoles(new RoleOverrides());

    expect(array_map(fn(EffectiveRole $r): string => $r->slug, $roles))
        ->toContain('wlsb_pending')
        ->toContain('staff')
        ->and(roleBySlug($roles, 'wlsb_pending')->capabilities)->toBe([])
        ->and(roleBySlug($roles, 'wlsb_pending')->protected)->toBeTrue();
});

test('custom roles are added and renames applied', function (): void {
    $overrides = new RoleOverrides(
        customRoles: [new RoleBlueprint('vendor', 'Vendor', ['read'])],
        renames: ['staff' => 'Team'],
    );

    $roles = makeRoleReconciler()->effectiveOwnedRoles($overrides);

    expect(roleBySlug($roles, 'vendor')?->capabilities)->toBe(['read'])
        ->and(roleBySlug($roles, 'staff')?->displayName)->toBe('Team');
});

test('tombstoning removes a non-protected owned role but keeps a protected one', function (): void {
    $overrides = new RoleOverrides(tombstones: ['staff', 'wlsb_pending']);

    $slugs = array_map(
        fn(EffectiveRole $r): string => $r->slug,
        makeRoleReconciler()->effectiveOwnedRoles($overrides),
    );

    expect($slugs)->not->toContain('staff')
        ->and($slugs)->toContain('wlsb_pending');
});

test('cap toggles add/remove caps on an owned role, but protected caps are re-asserted', function (): void {
    $overrides = new RoleOverrides(
        capToggles: ['staff' => ['read' => false, 'wlsb_core' => false, 'edit_posts' => true]],
    );

    $staff = roleBySlug(makeRoleReconciler()->effectiveOwnedRoles($overrides), 'staff');

    expect($staff->capabilities)->toContain('edit_posts')
        ->and($staff->capabilities)->toContain('wlsb_core')  // protected → re-asserted
        ->and($staff->capabilities)->not->toContain('read');
});

// --- effectiveGrants ------------------------------------------------------

test('effective grants include the protected and non-protected defaults', function (): void {
    $grants = makeRoleReconciler()->effectiveGrants(new RoleOverrides());

    $map = [];
    foreach ($grants as $grant) {
        $map[$grant->roleSlug . ':' . $grant->capability] = $grant->granted;
    }

    expect($map)->toHaveKey('administrator:wlsb_manage_access')
        ->and($map['administrator:wlsb_manage_access'])->toBeTrue()
        ->and($map['administrator:wlsb_manage_approvals'])->toBeTrue();
});

test('a toggle on a non-owned role becomes a grant', function (): void {
    $overrides = new RoleOverrides(capToggles: ['editor' => ['wlsb_approve_requests' => true]]);

    $grants = array_filter(
        makeRoleReconciler()->effectiveGrants($overrides),
        fn(CapabilityGrant $g): bool => $g->roleSlug === 'editor' && $g->capability === 'wlsb_approve_requests',
    );

    expect($grants)->toHaveCount(1)
        ->and(array_values($grants)[0]->granted)->toBeTrue();
});

test('a protected default grant cannot be revoked by an admin toggle', function (): void {
    $overrides = new RoleOverrides(capToggles: ['administrator' => ['wlsb_manage_access' => false]]);

    $grants = array_filter(
        makeRoleReconciler()->effectiveGrants($overrides),
        fn(CapabilityGrant $g): bool => $g->roleSlug === 'administrator' && $g->capability === 'wlsb_manage_access',
    );

    expect(array_values($grants)[0]->granted)->toBeTrue();
});

test('a non-protected default grant can be revoked by an admin toggle', function (): void {
    $overrides = new RoleOverrides(capToggles: ['administrator' => ['wlsb_manage_approvals' => false]]);

    $grants = array_filter(
        makeRoleReconciler()->effectiveGrants($overrides),
        fn(CapabilityGrant $g): bool => $g->roleSlug === 'administrator' && $g->capability === 'wlsb_manage_approvals',
    );

    expect(array_values($grants)[0]->granted)->toBeFalse();
});

// --- materialize ----------------------------------------------------------

test('materialize creates owned roles and applies grants onto existing roles', function (): void {
    $gateway = new InMemoryRolesGateway([
        'administrator' => ['name' => 'Administrator', 'caps' => ['manage_options' => true]],
    ]);

    $result = makeRoleReconciler()->materialize(new RoleOverrides(), $gateway);
    $snapshot = $gateway->snapshot();

    expect($snapshot)->toHaveKey('wlsb_pending')
        ->and($snapshot['wlsb_pending']['caps'])->toBe([])
        ->and($snapshot['staff']['caps'])->toHaveKey('read')
        ->and($snapshot['administrator']['caps'])->toHaveKey('wlsb_manage_access')
        ->and($snapshot['administrator']['caps'])->toHaveKey('wlsb_manage_approvals')
        ->and($snapshot['administrator']['caps'])->toHaveKey('manage_options') // untouched
        ->and($result->added)->toContain('wlsb_pending')->toContain('staff')
        ->and($result->isEmpty())->toBeFalse();
});

test('materialize is idempotent — a second run changes nothing', function (): void {
    $gateway = new InMemoryRolesGateway([
        'administrator' => ['name' => 'Administrator', 'caps' => ['manage_options' => true]],
    ]);
    $reconciler = makeRoleReconciler();

    $reconciler->materialize(new RoleOverrides(), $gateway);
    $result = $reconciler->materialize(new RoleOverrides(), $gateway);

    expect($result->isEmpty())->toBeTrue();
});

test('materialize removes a tombstoned owned role that exists', function (): void {
    $gateway = new InMemoryRolesGateway([
        'administrator' => ['name' => 'Administrator', 'caps' => []],
        'staff' => ['name' => 'Staff', 'caps' => ['read' => true, 'wlsb_core' => true]],
    ]);

    $result = makeRoleReconciler()->materialize(new RoleOverrides(tombstones: ['staff']), $gateway);

    expect($gateway->snapshot())->not->toHaveKey('staff')
        ->and($result->removed)->toContain('staff');
});

test('materialize skips grants for roles that do not exist', function (): void {
    $gateway = new InMemoryRolesGateway([]); // no administrator

    makeRoleReconciler()->materialize(new RoleOverrides(), $gateway);

    expect($gateway->snapshot())->not->toHaveKey('administrator')
        ->and($gateway->snapshot())->toHaveKey('wlsb_pending');
});

test('materialize renames and updates capabilities when they differ', function (): void {
    $gateway = new InMemoryRolesGateway([
        'administrator' => ['name' => 'Administrator', 'caps' => []],
        'staff' => ['name' => 'Old Staff', 'caps' => ['read' => true]], // missing wlsb_core
    ]);

    $result = makeRoleReconciler()->materialize(new RoleOverrides(), $gateway);

    expect($gateway->snapshot()['staff']['name'])->toBe('Staff')
        ->and($gateway->snapshot()['staff']['caps'])->toHaveKey('wlsb_core')
        ->and($result->updated)->toContain('staff');
});

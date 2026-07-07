<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\CustomRoleManager;
use Wlsb\Access\Application\Access\InvalidRoleOperation;
use Wlsb\Access\Domain\Roles\RoleOverrides;

$existing = ['administrator', 'editor', 'subscriber', 'wlsb_pending'];

test('create adds a custom role blueprint with no capabilities', function () use ($existing): void {
    $overrides = (new CustomRoleManager())->create(new RoleOverrides(), 'wlsb_vendor', 'Vendor', $existing);

    expect($overrides->customRoles())->toHaveCount(1)
        ->and($overrides->customRoles()[0]->slug)->toBe('wlsb_vendor')
        ->and($overrides->customRoles()[0]->displayName)->toBe('Vendor')
        ->and($overrides->customRoles()[0]->capabilities)->toBe([])
        ->and($overrides->customRoles()[0]->protected)->toBeFalse();
});

test('create rejects an empty slug or empty name', function () use ($existing): void {
    expect(fn() => (new CustomRoleManager())->create(new RoleOverrides(), '', 'Vendor', $existing))
        ->toThrow(InvalidRoleOperation::class);
    expect(fn() => (new CustomRoleManager())->create(new RoleOverrides(), 'wlsb_vendor', '  ', $existing))
        ->toThrow(InvalidRoleOperation::class);
});

test('create rejects a slug that collides with an existing role', function () use ($existing): void {
    expect(fn() => (new CustomRoleManager())->create(new RoleOverrides(), 'editor', 'Editor Two', $existing))
        ->toThrow(InvalidRoleOperation::class, 'already exists');
});

test('create rejects a duplicate custom role', function () use ($existing): void {
    $manager = new CustomRoleManager();
    $overrides = $manager->create(new RoleOverrides(), 'wlsb_vendor', 'Vendor', $existing);

    expect(fn() => $manager->create($overrides, 'wlsb_vendor', 'Vendor Two', $existing))
        ->toThrow(InvalidRoleOperation::class);
});

test('create un-tombstones a previously deleted custom role', function () use ($existing): void {
    $manager = new CustomRoleManager();
    $overrides = $manager->create(new RoleOverrides(), 'wlsb_vendor', 'Vendor', $existing);
    $overrides = $manager->delete($overrides, 'wlsb_vendor');

    expect($overrides->isTombstoned('wlsb_vendor'))->toBeTrue();

    // The role may still exist in WP until the next reconcile, but recreating it is allowed.
    $overrides = $manager->create($overrides, 'wlsb_vendor', 'Vendor Again', [...$existing, 'wlsb_vendor']);

    expect($overrides->isTombstoned('wlsb_vendor'))->toBeFalse()
        ->and(array_map(fn($r) => $r->displayName, $overrides->customRoles()))->toContain('Vendor Again');
});

test('rename changes a custom role display name', function () use ($existing): void {
    $manager = new CustomRoleManager();
    $overrides = $manager->create(new RoleOverrides(), 'wlsb_vendor', 'Vendor', $existing);

    $overrides = $manager->rename($overrides, 'wlsb_vendor', 'Supplier');

    expect($overrides->customRoles()[0]->displayName)->toBe('Supplier');
});

test('rename rejects a role that is not a custom role', function (): void {
    expect(fn() => (new CustomRoleManager())->rename(new RoleOverrides(), 'editor', 'Nope'))
        ->toThrow(InvalidRoleOperation::class);
});

test('delete tombstones a custom role', function () use ($existing): void {
    $manager = new CustomRoleManager();
    $overrides = $manager->create(new RoleOverrides(), 'wlsb_vendor', 'Vendor', $existing);

    $overrides = $manager->delete($overrides, 'wlsb_vendor');

    expect($overrides->isTombstoned('wlsb_vendor'))->toBeTrue()
        ->and($overrides->hasCustomRole('wlsb_vendor'))->toBeTrue(); // blueprint retained for reconciler removal
});

test('delete rejects a role that is not a custom role (e.g. a core role)', function (): void {
    expect(fn() => (new CustomRoleManager())->delete(new RoleOverrides(), 'administrator'))
        ->toThrow(InvalidRoleOperation::class);
});

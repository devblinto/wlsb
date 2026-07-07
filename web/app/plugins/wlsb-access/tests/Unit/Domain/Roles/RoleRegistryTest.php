<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Roles\CapabilityGrant;
use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleRegistry;

test('a blueprint reports which capabilities it grants', function (): void {
    $blueprint = new RoleBlueprint('vendor', 'Vendor', ['read', 'wlsb_view_dashboard']);

    expect($blueprint->grants('read'))->toBeTrue()
        ->and($blueprint->grants('manage_options'))->toBeFalse();
});

test('withDisplayName returns an immutable copy', function (): void {
    $blueprint = new RoleBlueprint('vendor', 'Vendor', ['read']);

    $renamed = $blueprint->withDisplayName('Supplier');

    expect($renamed->displayName)->toBe('Supplier')
        ->and($renamed->slug)->toBe('vendor')
        ->and($renamed->capabilities)->toBe(['read'])
        ->and($blueprint->displayName)->toBe('Vendor'); // original untouched
});

test('a capability grant defaults to granted and unprotected', function (): void {
    $grant = new CapabilityGrant('administrator', 'wlsb_manage_approvals');

    expect($grant->roleSlug)->toBe('administrator')
        ->and($grant->capability)->toBe('wlsb_manage_approvals')
        ->and($grant->granted)->toBeTrue()
        ->and($grant->protected)->toBeFalse();
});

test('the registry stores and returns blueprints and grants in registration order', function (): void {
    $registry = new RoleRegistry();
    $registry->addBlueprint(new RoleBlueprint('wlsb_pending', 'Pending', [], protected: true));
    $registry->addBlueprint(new RoleBlueprint('vendor', 'Vendor', ['read']));
    $registry->addGrant(new CapabilityGrant('administrator', 'wlsb_manage_access', protected: true));

    expect($registry->blueprintSlugs())->toBe(['wlsb_pending', 'vendor'])
        ->and($registry->hasBlueprint('vendor'))->toBeTrue()
        ->and($registry->blueprint('wlsb_pending')?->protected)->toBeTrue()
        ->and($registry->grants())->toHaveCount(1)
        ->and($registry->grants()[0]->protected)->toBeTrue();
});

test('re-registering a blueprint slug replaces it', function (): void {
    $registry = new RoleRegistry();
    $registry->addBlueprint(new RoleBlueprint('vendor', 'Vendor', ['read']));
    $registry->addBlueprint(new RoleBlueprint('vendor', 'Supplier', ['read', 'edit_posts']));

    expect($registry->blueprints())->toHaveCount(1)
        ->and($registry->blueprint('vendor')?->displayName)->toBe('Supplier')
        ->and($registry->blueprint('vendor')?->capabilities)->toBe(['read', 'edit_posts']);
});

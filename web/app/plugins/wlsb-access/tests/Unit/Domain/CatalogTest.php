<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Catalog;
use Wlsb\Access\Domain\Roles\Role;

test('the default capabilities include a protected management capability, grouped', function (): void {
    $caps = Catalog::capabilities();

    expect($caps->has(Cap::MANAGE_ACCESS))->toBeTrue()
        ->and($caps->get(Cap::MANAGE_ACCESS)?->protected)->toBeTrue()
        ->and($caps->protectedKeys())->toContain(Cap::MANAGE_ACCESS)
        ->and($caps->has(Cap::MANAGE_APPROVALS))->toBeTrue()
        ->and($caps->has(Cap::APPROVE_REQUESTS))->toBeTrue()
        ->and($caps->groups())->not->toBe([]);
});

test('the default roles define the protected zero-cap pending holding role', function (): void {
    $roles = Catalog::roles();

    expect($roles->hasBlueprint(Role::PENDING))->toBeTrue()
        ->and($roles->blueprint(Role::PENDING)?->protected)->toBeTrue()
        ->and($roles->blueprint(Role::PENDING)?->capabilities)->toBe([]);
});

test('the administrator receives a protected manage-access grant to prevent lockout', function (): void {
    $grantMap = [];
    foreach (Catalog::roles()->grants() as $grant) {
        $grantMap[$grant->roleSlug . ':' . $grant->capability] = $grant->protected;
    }

    expect($grantMap)->toHaveKey('administrator:' . Cap::MANAGE_ACCESS)
        ->and($grantMap['administrator:' . Cap::MANAGE_ACCESS])->toBeTrue()
        ->and($grantMap)->toHaveKey('administrator:' . Cap::MANAGE_APPROVALS)
        ->and($grantMap)->toHaveKey('administrator:' . Cap::APPROVE_REQUESTS);
});

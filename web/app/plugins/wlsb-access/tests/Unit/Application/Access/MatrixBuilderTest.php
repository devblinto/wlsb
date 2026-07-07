<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\MatrixBuilder;
use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Catalog;
use Wlsb\Access\Domain\Roles\Role;
use Wlsb\Access\Domain\Roles\RoleOverrides;
use Wlsb\Access\Domain\Roles\RoleReconciler;

function matrixBuilder(): MatrixBuilder
{
    return new MatrixBuilder(new RoleReconciler(Catalog::roles(), Catalog::capabilities()));
}

$caps = [Cap::MANAGE_ACCESS, Cap::MANAGE_APPROVALS, Cap::APPROVE_REQUESTS];
$roles = ['administrator', 'editor', Role::PENDING];

test('default state reflects the code defaults (admin grants, others empty)', function () use ($roles, $caps): void {
    $state = matrixBuilder()->defaultState($roles, $caps);

    expect($state['administrator'][Cap::MANAGE_ACCESS])->toBeTrue()
        ->and($state['administrator'][Cap::APPROVE_REQUESTS])->toBeTrue()
        ->and($state['editor'][Cap::MANAGE_ACCESS])->toBeFalse()
        ->and($state[Role::PENDING][Cap::MANAGE_ACCESS])->toBeFalse();
});

test('checked state applies admin overrides on top of the defaults', function () use ($roles, $caps): void {
    $overrides = new RoleOverrides(capToggles: [
        'editor' => [Cap::APPROVE_REQUESTS => true],
        'administrator' => [Cap::MANAGE_APPROVALS => false],
    ]);

    $state = matrixBuilder()->checkedState($roles, $caps, $overrides);

    expect($state['editor'][Cap::APPROVE_REQUESTS])->toBeTrue()          // added by override
        ->and($state['administrator'][Cap::MANAGE_APPROVALS])->toBeFalse() // revoked by override
        ->and($state['administrator'][Cap::MANAGE_ACCESS])->toBeTrue();    // untouched default
});

test('protected pairs identify grants that cannot be revoked (admin manage-access)', function (): void {
    $pairs = matrixBuilder()->protectedPairs();

    expect($pairs)->toHaveKey('administrator|' . Cap::MANAGE_ACCESS)
        ->and($pairs)->not->toHaveKey('administrator|' . Cap::MANAGE_APPROVALS);
});

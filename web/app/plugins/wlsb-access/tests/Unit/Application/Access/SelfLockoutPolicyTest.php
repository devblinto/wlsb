<?php

declare(strict_types=1);

use Wlsb\Access\Application\Access\SelfLockoutPolicy;
use Wlsb\Access\Domain\Capabilities\Cap;

test('a change that revokes the management cap from a role the actor holds is a lockout', function (): void {
    $policy = new SelfLockoutPolicy(actorRoleSlugs: ['administrator']);

    $wouldLock = $policy->wouldLockOut(
        ['administrator' => [Cap::MANAGE_ACCESS => false]],
        Cap::MANAGE_ACCESS,
    );

    expect($wouldLock)->toBeTrue();
});

test('revoking the management cap from a role the actor does not hold is fine', function (): void {
    $policy = new SelfLockoutPolicy(actorRoleSlugs: ['administrator']);

    $wouldLock = $policy->wouldLockOut(
        ['editor' => [Cap::MANAGE_ACCESS => false]],
        Cap::MANAGE_ACCESS,
    );

    expect($wouldLock)->toBeFalse();
});

test('leaving the management cap in place is not a lockout', function (): void {
    $policy = new SelfLockoutPolicy(actorRoleSlugs: ['administrator']);

    expect($policy->wouldLockOut(['administrator' => [Cap::MANAGE_ACCESS => true]], Cap::MANAGE_ACCESS))->toBeFalse()
        ->and($policy->wouldLockOut([], Cap::MANAGE_ACCESS))->toBeFalse();
});

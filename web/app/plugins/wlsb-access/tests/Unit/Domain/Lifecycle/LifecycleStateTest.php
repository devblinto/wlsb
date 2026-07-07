<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Lifecycle\LifecycleTransitions;

test('only the active state may authenticate', function (): void {
    expect(LifecycleState::Active->canAuthenticate())->toBeTrue()
        ->and(LifecycleState::PendingEmailVerification->canAuthenticate())->toBeFalse()
        ->and(LifecycleState::PendingApproval->canAuthenticate())->toBeFalse()
        ->and(LifecycleState::Rejected->canAuthenticate())->toBeFalse()
        ->and(LifecycleState::Suspended->canAuthenticate())->toBeFalse();
});

test('the happy-path transitions are allowed', function (): void {
    expect(LifecycleTransitions::isAllowed(LifecycleState::PendingEmailVerification, LifecycleState::Active))->toBeTrue()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::PendingEmailVerification, LifecycleState::PendingApproval))->toBeTrue()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::PendingApproval, LifecycleState::Active))->toBeTrue()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::PendingApproval, LifecycleState::Rejected))->toBeTrue()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::Active, LifecycleState::Suspended))->toBeTrue()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::Suspended, LifecycleState::Active))->toBeTrue();
});

test('re-application transitions from rejected are allowed', function (): void {
    expect(LifecycleTransitions::isAllowed(LifecycleState::Rejected, LifecycleState::PendingEmailVerification))->toBeTrue()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::Rejected, LifecycleState::PendingApproval))->toBeTrue();
});

test('nonsensical transitions are rejected', function (): void {
    expect(LifecycleTransitions::isAllowed(LifecycleState::Active, LifecycleState::PendingEmailVerification))->toBeFalse()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::PendingEmailVerification, LifecycleState::Suspended))->toBeFalse()
        ->and(LifecycleTransitions::isAllowed(LifecycleState::Active, LifecycleState::Active))->toBeFalse();
});

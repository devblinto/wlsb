<?php

declare(strict_types=1);

use Wlsb\Access\Application\Lifecycle\IllegalTransition;
use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Tests\Support\InMemoryEventLogger;
use Wlsb\Access\Tests\Support\InMemoryUserDirectory;

function lifecycleManager(InMemoryUserDirectory $users, ?InMemoryEventLogger $log = null): UserLifecycleManager
{
    return new UserLifecycleManager($users, 'wlsb_pending', $log ?? new InMemoryEventLogger());
}

test('activating a verified user grants their requested role', function (): void {
    $users = new InMemoryUserDirectory();
    $id = $users->create('vendor1', 'v@example.test', 'hash', 'wlsb_pending');
    $users->setStatus($id, LifecycleState::PendingEmailVerification);
    $users->setRequestedRole($id, 'wlsb_vendor');

    lifecycleManager($users)->transition($id, LifecycleState::Active);

    expect($users->getStatus($id))->toBe(LifecycleState::Active)
        ->and($users->roleOf($id))->toBe('wlsb_vendor');
});

test('an illegal transition throws and changes nothing', function (): void {
    $users = new InMemoryUserDirectory();
    $id = $users->create('u', 'u@example.test', 'hash', 'wlsb_pending');
    $users->setStatus($id, LifecycleState::Active);

    expect(fn() => lifecycleManager($users)->transition($id, LifecycleState::PendingEmailVerification))
        ->toThrow(IllegalTransition::class);

    expect($users->getStatus($id))->toBe(LifecycleState::Active);
});

test('suspending an active user strips them to the holding role and destroys sessions', function (): void {
    $users = new InMemoryUserDirectory();
    $id = $users->create('u', 'u@example.test', 'hash', 'wlsb_vendor');
    $users->setStatus($id, LifecycleState::Active);

    lifecycleManager($users)->transition($id, LifecycleState::Suspended, ['actor_id' => 9]);

    expect($users->getStatus($id))->toBe(LifecycleState::Suspended)
        ->and($users->roleOf($id))->toBe('wlsb_pending')
        ->and($users->sessionsDestroyed[$id] ?? 0)->toBe(1);
});

test('a status change is written to the audit log', function (): void {
    $users = new InMemoryUserDirectory();
    $log = new InMemoryEventLogger();
    $id = $users->create('u', 'u@example.test', 'hash', 'wlsb_pending');
    $users->setStatus($id, LifecycleState::PendingEmailVerification);
    $users->setRequestedRole($id, 'subscriber');

    lifecycleManager($users, $log)->transition($id, LifecycleState::Active, ['actor_id' => 7]);

    expect($log->all())->toHaveCount(1)
        ->and($log->all()[0]->type->value)->toBe('user.status_changed')
        ->and($log->all()[0]->actorId)->toBe(7);
});

test('assertCanAuthenticate blocks non-active managed users but allows active and unmanaged ones', function (): void {
    $users = new InMemoryUserDirectory();
    $manager = lifecycleManager($users);

    $pending = $users->create('p', 'p@example.test', 'h', 'wlsb_pending');
    $users->setStatus($pending, LifecycleState::PendingApproval);

    $active = $users->create('a', 'a@example.test', 'h', 'subscriber');
    $users->setStatus($active, LifecycleState::Active);

    $unmanaged = $users->create('legacy', 'l@example.test', 'h', 'administrator'); // no status

    expect($manager->assertCanAuthenticate($pending))->toBe(LifecycleState::PendingApproval)
        ->and($manager->assertCanAuthenticate($active))->toBeNull()
        ->and($manager->assertCanAuthenticate($unmanaged))->toBeNull();
});

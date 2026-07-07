<?php

declare(strict_types=1);

use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Application\Registration\EmailVerificationService;
use Wlsb\Access\Application\Registration\RegistrationException;
use Wlsb\Access\Application\Registration\RegistrationInput;
use Wlsb\Access\Application\Registration\RegistrationService;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Tokens\HmacTokenHasher;
use Wlsb\Access\Domain\Users\UserMeta;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Tests\Support\ArrayMailer;
use Wlsb\Access\Tests\Support\FakeRegistrationUrls;
use Wlsb\Access\Tests\Support\FixedTokenGenerator;
use Wlsb\Access\Tests\Support\FrozenClock;
use Wlsb\Access\Tests\Support\InMemoryEventLogger;
use Wlsb\Access\Tests\Support\InMemoryUserDirectory;
use Wlsb\Access\Tests\Support\InMemoryWorkflowConfigStore;

function makeRegistration(InMemoryUserDirectory $users, ArrayMailer $mailer, InMemoryEventLogger $log): RegistrationService
{
    $store = new InMemoryWorkflowConfigStore(WorkflowConfig::default());
    $verification = new EmailVerificationService(
        $users,
        new UserLifecycleManager($users, 'wlsb_pending', $log),
        $store,
        new FixedTokenGenerator(),
        new HmacTokenHasher('secret'),
        new NotificationService($mailer, $log, 'Site'),
        new FakeRegistrationUrls(),
        new FrozenClock(new DateTimeImmutable('@1000')),
        $log,
    );

    return new RegistrationService($users, $store, $verification, $log, 'wlsb_pending');
}

test('registration creates a pending holding-role user and sends verification', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $log = new InMemoryEventLogger();

    makeRegistration($users, $mailer, $log)->register(
        new RegistrationInput('new@example.test', 'newuser', 'pw12345', 'subscriber'),
    );

    $id = $users->findByEmail('new@example.test');

    expect($id)->not->toBeNull()
        ->and($users->roleOf($id))->toBe('wlsb_pending')
        ->and($users->getStatus($id))->toBe(LifecycleState::PendingEmailVerification)
        ->and($users->getRequestedRole($id))->toBe('subscriber')
        ->and($users->getMeta($id, UserMeta::TOKEN_HASH))->not->toBeNull()
        ->and($mailer->last()->key)->toBe('verification');
});

test('an invalid email is rejected', function (): void {
    $users = new InMemoryUserDirectory();

    expect(fn() => makeRegistration($users, new ArrayMailer(), new InMemoryEventLogger())
        ->register(new RegistrationInput('not-an-email', 'u', 'pw12345', 'subscriber')))
        ->toThrow(RegistrationException::class);
});

test('a non-self-selectable role and the administrator role are both rejected', function (): void {
    $users = new InMemoryUserDirectory();
    $service = makeRegistration($users, new ArrayMailer(), new InMemoryEventLogger());

    expect(fn() => $service->register(new RegistrationInput('a@example.test', 'a', 'pw12345', 'editor')))
        ->toThrow(RegistrationException::class);
    expect(fn() => $service->register(new RegistrationInput('b@example.test', 'b', 'pw12345', 'administrator')))
        ->toThrow(RegistrationException::class);
});

test('a duplicate username is rejected', function (): void {
    $users = new InMemoryUserDirectory();
    $users->create('taken', 'other@example.test', 'h', 'subscriber');

    expect(fn() => makeRegistration($users, new ArrayMailer(), new InMemoryEventLogger())
        ->register(new RegistrationInput('new@example.test', 'taken', 'pw12345', 'subscriber')))
        ->toThrow(RegistrationException::class);
});

test('registering an already-used email neither creates a user nor reveals it (anti-enumeration)', function (): void {
    $users = new InMemoryUserDirectory();
    $service = makeRegistration($users, new ArrayMailer(), new InMemoryEventLogger());
    $service->register(new RegistrationInput('dup@example.test', 'first', 'pw12345', 'subscriber'));

    // Second attempt with the same email, different username — must not throw and must not create a second user.
    $service->register(new RegistrationInput('dup@example.test', 'second', 'pw12345', 'subscriber'));

    expect($users->findByLogin('second'))->toBeNull();
});

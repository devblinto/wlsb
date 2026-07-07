<?php

declare(strict_types=1);

use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Application\Registration\EmailVerificationService;
use Wlsb\Access\Application\Registration\ResendResult;
use Wlsb\Access\Application\Registration\VerificationResult;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Tokens\HmacTokenHasher;
use Wlsb\Access\Domain\Users\UserMeta;
use Wlsb\Access\Domain\Workflow\RoleWorkflow;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Tests\Support\ArrayMailer;
use Wlsb\Access\Tests\Support\FakeRegistrationUrls;
use Wlsb\Access\Tests\Support\FixedTokenGenerator;
use Wlsb\Access\Tests\Support\FrozenClock;
use Wlsb\Access\Tests\Support\InMemoryEventLogger;
use Wlsb\Access\Tests\Support\InMemoryUserDirectory;
use Wlsb\Access\Tests\Support\InMemoryWorkflowConfigStore;

function makeVerifier(
    InMemoryUserDirectory $users,
    ArrayMailer $mailer,
    InMemoryEventLogger $log,
    int $nowTs,
    WorkflowConfig $config,
    string $token = 'fixed-raw-token',
    ?\Wlsb\Access\Application\Approval\ApprovalOpener $approvals = null,
): EmailVerificationService {
    return new EmailVerificationService(
        $users,
        new UserLifecycleManager($users, 'wlsb_pending', $log),
        new InMemoryWorkflowConfigStore($config),
        new FixedTokenGenerator($token),
        new HmacTokenHasher('secret'),
        new NotificationService($mailer, $log, 'Site'),
        new FakeRegistrationUrls(),
        new FrozenClock(new DateTimeImmutable('@' . $nowTs)),
        $log,
        ttlSeconds: 3600,
        resendThrottleSeconds: 300,
        approvals: $approvals,
    );
}

function pendingUser(InMemoryUserDirectory $users, string $role = 'subscriber'): int
{
    $id = $users->create('u', 'u@example.test', 'hash', 'wlsb_pending');
    $users->setStatus($id, LifecycleState::PendingEmailVerification);
    $users->setRequestedRole($id, $role);

    return $id;
}

test('issueToken stores the hash and expiry and sends a verification email with the link', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);

    makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, WorkflowConfig::default())
        ->issueToken($id, 'u@example.test');

    expect($users->getMeta($id, UserMeta::TOKEN_HASH))->toBe((new HmacTokenHasher('secret'))->hash('fixed-raw-token'))
        ->and($users->getMeta($id, UserMeta::TOKEN_EXPIRES))->toBe('4600') // 1000 + 3600
        ->and($mailer->last()->key)->toBe('verification')
        ->and($mailer->last()->html)->toContain('token=fixed-raw-token');
});

test('a valid token activates a no-approval user, sends welcome, and consumes the token', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);
    $verifier = makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, WorkflowConfig::default());
    $verifier->issueToken($id, 'u@example.test');

    $result = $verifier->verify($id, 'fixed-raw-token');

    expect($result)->toBe(VerificationResult::Verified)
        ->and($users->getStatus($id))->toBe(LifecycleState::Active)
        ->and($users->roleOf($id))->toBe('subscriber')
        ->and($users->getMeta($id, UserMeta::TOKEN_HASH))->toBeNull()  // consumed
        ->and($mailer->last()->key)->toBe('welcome');
});

test('a valid token for an approval-required role moves to pending_approval and emails awaiting-approval', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);
    $config = new WorkflowConfig(['subscriber' => new RoleWorkflow('subscriber', registerable: true, selfSelectable: true, requiresApproval: true)]);
    $verifier = makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, $config);
    $verifier->issueToken($id, 'u@example.test');

    $result = $verifier->verify($id, 'fixed-raw-token');

    expect($result)->toBe(VerificationResult::Verified)
        ->and($users->getStatus($id))->toBe(LifecycleState::PendingApproval)
        ->and($users->roleOf($id))->toBe('wlsb_pending') // real role NOT granted yet
        ->and($mailer->last()->key)->toBe('awaiting_approval');
});

test('verifying an approval-required role opens an approval request', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);
    $config = new WorkflowConfig(['subscriber' => new RoleWorkflow('subscriber', registerable: true, selfSelectable: true, requiresApproval: true)]);
    $spy = new \Wlsb\Access\Tests\Support\SpyApprovalOpener();
    $verifier = makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, $config, approvals: $spy);
    $verifier->issueToken($id, 'u@example.test');

    $verifier->verify($id, 'fixed-raw-token');

    expect($spy->opened)->toBe([[$id, 'subscriber']]);
});

test('an expired token is rejected and leaves the user pending with the token intact', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);
    makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, WorkflowConfig::default())->issueToken($id, 'u@example.test');

    // Verify well after expiry (1000 + 3600 = 4600).
    $result = makeVerifier($users, $mailer, new InMemoryEventLogger(), 5000, WorkflowConfig::default())->verify($id, 'fixed-raw-token');

    expect($result)->toBe(VerificationResult::Expired)
        ->and($users->getStatus($id))->toBe(LifecycleState::PendingEmailVerification)
        ->and($users->getMeta($id, UserMeta::TOKEN_HASH))->not->toBeNull();
});

test('a wrong token is invalid; an already-active user is already verified', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);
    $verifier = makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, WorkflowConfig::default());
    $verifier->issueToken($id, 'u@example.test');

    expect($verifier->verify($id, 'wrong-token'))->toBe(VerificationResult::Invalid);

    $users->setStatus($id, LifecycleState::Active);
    expect($verifier->verify($id, 'fixed-raw-token'))->toBe(VerificationResult::AlreadyVerified);
});

test('resend is throttled inside the window and sends a fresh token after it', function (): void {
    $users = new InMemoryUserDirectory();
    $mailer = new ArrayMailer();
    $id = pendingUser($users);
    makeVerifier($users, $mailer, new InMemoryEventLogger(), 1000, WorkflowConfig::default())->issueToken($id, 'u@example.test');

    // 100s later — inside the 300s throttle.
    expect(makeVerifier($users, $mailer, new InMemoryEventLogger(), 1100, WorkflowConfig::default())->resend($id))
        ->toBe(ResendResult::Throttled);

    // 400s later — past the throttle.
    expect(makeVerifier($users, $mailer, new InMemoryEventLogger(), 1400, WorkflowConfig::default())->resend($id))
        ->toBe(ResendResult::Sent);
});

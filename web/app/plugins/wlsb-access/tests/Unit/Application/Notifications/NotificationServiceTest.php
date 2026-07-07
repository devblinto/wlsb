<?php

declare(strict_types=1);

use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Tests\Support\ArrayMailer;
use Wlsb\Access\Tests\Support\InMemoryEventLogger;

function notifications(ArrayMailer $mailer, InMemoryEventLogger $log): NotificationService
{
    return new NotificationService($mailer, $log, 'WLSB Portal');
}

test('the verification email carries the link and is logged as sent', function (): void {
    $mailer = new ArrayMailer();
    $log = new InMemoryEventLogger();

    $ok = notifications($mailer, $log)->sendVerification(42, 'user@example.test', 'https://site.test/verify?token=abc');

    $message = $mailer->last();

    expect($ok)->toBeTrue()
        ->and($message->to)->toBe('user@example.test')
        ->and($message->key)->toBe('verification')
        ->and($message->subject)->toContain('WLSB Portal')
        ->and($message->html)->toContain('https://site.test/verify?token=abc')
        ->and($message->text)->toContain('https://site.test/verify?token=abc')
        ->and($log->all())->toHaveCount(1)
        ->and($log->all()[0]->type->value)->toBe('notification.sent');
});

test('a delivery failure returns false and is logged as failed, without leaking the recipient', function (): void {
    $mailer = new ArrayMailer(succeeds: false);
    $log = new InMemoryEventLogger();

    $ok = notifications($mailer, $log)->sendVerification(42, 'user@example.test', 'https://site.test/verify');

    expect($ok)->toBeFalse()
        ->and($log->all()[0]->type->value)->toBe('notification.failed')
        ->and($log->all()[0]->context)->toBe(['key' => 'verification']); // no email address stored
});

test('the welcome email links to login', function (): void {
    $mailer = new ArrayMailer();

    notifications($mailer, new InMemoryEventLogger())->sendWelcome(42, 'user@example.test', 'https://site.test/login');

    expect($mailer->last()->key)->toBe('welcome')
        ->and($mailer->last()->html)->toContain('https://site.test/login');
});

test('the awaiting-approval email is sent', function (): void {
    $mailer = new ArrayMailer();

    notifications($mailer, new InMemoryEventLogger())->sendAwaitingApproval(42, 'user@example.test');

    expect($mailer->last()->key)->toBe('awaiting_approval')
        ->and($mailer->last()->to)->toBe('user@example.test');
});

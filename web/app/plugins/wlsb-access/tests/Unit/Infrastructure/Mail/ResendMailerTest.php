<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Mail\EmailMessage;
use Wlsb\Access\Infrastructure\Mail\ResendMailer;

test('builds a Resend API payload with a named from address', function (): void {
    $mailer = new ResendMailer('re_key', 'no-reply@site.test', 'WLSB Portal');

    $payload = $mailer->payload(new EmailMessage(
        to: 'user@example.test',
        subject: 'Hello',
        html: '<p>Hi</p>',
        text: 'Hi',
        key: 'verification',
    ));

    expect($payload['from'])->toBe('WLSB Portal <no-reply@site.test>')
        ->and($payload['to'])->toBe(['user@example.test'])
        ->and($payload['subject'])->toBe('Hello')
        ->and($payload['html'])->toBe('<p>Hi</p>')
        ->and($payload['text'])->toBe('Hi');
});

test('omits the display name and the text part when they are empty', function (): void {
    $mailer = new ResendMailer('re_key', 'no-reply@site.test', '');

    $payload = $mailer->payload(new EmailMessage(
        to: 'user@example.test',
        subject: 'Hello',
        html: '<p>Hi</p>',
    ));

    expect($payload['from'])->toBe('no-reply@site.test')
        ->and($payload)->not->toHaveKey('text');
});

test('send returns false (without HTTP) when the API key is not configured', function (): void {
    $mailer = new ResendMailer(null, 'no-reply@site.test', 'WLSB');

    expect($mailer->send(new EmailMessage('u@example.test', 'S', '<p>x</p>')))->toBeFalse();
});

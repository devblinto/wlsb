<?php

declare(strict_types=1);

use Wlsb\Access\Delivery\Privacy\PrivacyIntegration;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Users\UserMeta;
use Wlsb\Access\Tests\Support\InMemoryUserDirectory;

test('export returns the user lifecycle status and requested role', function (): void {
    $users = new InMemoryUserDirectory();
    $id = $users->create('u', 'u@example.test', 'h', 'wlsb_pending');
    $users->setStatus($id, LifecycleState::PendingApproval);
    $users->setRequestedRole($id, 'wlsb_vendor');

    $export = (new PrivacyIntegration($users))->export('u@example.test');

    expect($export['done'])->toBeTrue()
        ->and($export['data'][0]['data'][0]['value'])->toBe('pending_approval')
        ->and($export['data'][0]['data'][1]['value'])->toBe('wlsb_vendor');
});

test('export is empty for an unknown email', function (): void {
    $export = (new PrivacyIntegration(new InMemoryUserDirectory()))->export('nobody@example.test');

    expect($export['data'])->toBe([])->and($export['done'])->toBeTrue();
});

test('erase removes the verification token meta', function (): void {
    $users = new InMemoryUserDirectory();
    $id = $users->create('u', 'u@example.test', 'h', 'wlsb_pending');
    $users->setMeta($id, UserMeta::TOKEN_HASH, 'abc');
    $users->setMeta($id, UserMeta::TOKEN_EXPIRES, '123');

    $result = (new PrivacyIntegration($users))->erase('u@example.test');

    expect($result['items_removed'])->toBeTrue()
        ->and($users->getMeta($id, UserMeta::TOKEN_HASH))->toBeNull()
        ->and($users->getMeta($id, UserMeta::TOKEN_EXPIRES))->toBeNull();
});

<?php

declare(strict_types=1);

use Wlsb\Access\Domain\Tokens\HmacTokenHasher;

test('hashing is deterministic for the same raw token and secret', function (): void {
    $hasher = new HmacTokenHasher('secret-salt');

    expect($hasher->hash('abc123'))->toBe($hasher->hash('abc123'));
});

test('the hash is not the raw token (leak-safe storage)', function (): void {
    $hasher = new HmacTokenHasher('secret-salt');

    expect($hasher->hash('abc123'))->not->toBe('abc123')
        ->and(strlen($hasher->hash('abc123')))->toBe(64); // sha256 hex
});

test('different tokens or secrets produce different hashes', function (): void {
    expect((new HmacTokenHasher('s1'))->hash('a'))->not->toBe((new HmacTokenHasher('s1'))->hash('b'))
        ->and((new HmacTokenHasher('s1'))->hash('a'))->not->toBe((new HmacTokenHasher('s2'))->hash('a'));
});

test('verify accepts the matching raw token and rejects others', function (): void {
    $hasher = new HmacTokenHasher('secret-salt');
    $stored = $hasher->hash('the-real-token');

    expect($hasher->verify('the-real-token', $stored))->toBeTrue()
        ->and($hasher->verify('a-forgery', $stored))->toBeFalse()
        ->and($hasher->verify('the-real-token', 'not-a-hash'))->toBeFalse();
});

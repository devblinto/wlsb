<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Tokens;

/**
 * HMAC-SHA256 token hasher. The secret is injected (in production, a WordPress
 * salt), so the class stays pure and unit-testable. Verification is constant-time
 * to avoid leaking token validity through timing.
 */
final class HmacTokenHasher implements TokenHasher
{
    public function __construct(private readonly string $secret) {}

    public function hash(string $raw): string
    {
        return hash_hmac('sha256', $raw, $this->secret);
    }

    public function verify(string $raw, string $storedHash): bool
    {
        return hash_equals($storedHash, $this->hash($raw));
    }
}

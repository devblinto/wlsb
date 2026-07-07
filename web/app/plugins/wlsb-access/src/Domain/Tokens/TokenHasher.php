<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Tokens;

/**
 * Hashes verification tokens for at-rest storage. Only the hash is ever
 * persisted, so a database leak cannot reveal usable tokens.
 */
interface TokenHasher
{
    public function hash(string $raw): string;

    public function verify(string $raw, string $storedHash): bool;
}

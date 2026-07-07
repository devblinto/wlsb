<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Tokens;

use Wlsb\Access\Domain\Tokens\TokenGenerator;

/**
 * 256 bits of CSPRNG entropy rendered as a 64-character hex string.
 */
final class RandomTokenGenerator implements TokenGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}

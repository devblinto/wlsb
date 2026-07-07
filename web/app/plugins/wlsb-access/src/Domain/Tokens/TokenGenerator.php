<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Tokens;

/**
 * Produces a cryptographically-random raw token. Behind an interface so tests
 * can inject a fixed value and stay deterministic.
 */
interface TokenGenerator
{
    public function generate(): string;
}

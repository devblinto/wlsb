<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Tokens\TokenGenerator;

/**
 * Returns a preset token so verification flows are deterministic in tests.
 */
final class FixedTokenGenerator implements TokenGenerator
{
    public function __construct(private string $token = 'fixed-raw-token') {}

    public function generate(): string
    {
        return $this->token;
    }
}

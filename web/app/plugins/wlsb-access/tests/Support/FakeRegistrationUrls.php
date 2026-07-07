<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Application\Registration\RegistrationUrls;

/**
 * Predictable URLs for tests.
 */
final class FakeRegistrationUrls implements RegistrationUrls
{
    public function verify(int $userId, string $rawToken): string
    {
        return "https://site.test/verify?uid={$userId}&token={$rawToken}";
    }

    public function login(): string
    {
        return 'https://site.test/login';
    }

    public function status(): string
    {
        return 'https://site.test/status';
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

/**
 * Builds the front-end URLs referenced in lifecycle emails (verify link, login,
 * status). Behind an interface so the services stay WordPress-free and tests can
 * assert predictable links.
 */
interface RegistrationUrls
{
    public function verify(int $userId, string $rawToken): string;

    public function login(): string;

    public function status(): string;
}

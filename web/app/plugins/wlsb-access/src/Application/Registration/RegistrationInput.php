<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

/**
 * The sanitised inputs from the registration form.
 */
final class RegistrationInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $login,
        public readonly string $password,
        public readonly string $role,
    ) {}
}

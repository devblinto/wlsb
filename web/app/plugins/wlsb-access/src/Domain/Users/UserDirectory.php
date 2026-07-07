<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Users;

use Wlsb\Access\Domain\Lifecycle\LifecycleState;

/**
 * Port over WordPress users. Keeping every user read/write behind this interface
 * lets the registration, verification, and lifecycle services be unit-tested
 * with an in-memory double and zero WordPress.
 */
interface UserDirectory
{
    public function findByEmail(string $email): ?int;

    public function findByLogin(string $login): ?int;

    /**
     * Create a user in the given (holding) role and return its id.
     */
    public function create(string $login, string $email, string $password, string $role): int;

    public function exists(int $userId): bool;

    public function getEmail(int $userId): ?string;

    public function setEmail(int $userId, string $email): void;

    public function getLogin(int $userId): ?string;

    public function getStatus(int $userId): ?LifecycleState;

    public function setStatus(int $userId, LifecycleState $state): void;

    public function getRequestedRole(int $userId): ?string;

    public function setRequestedRole(int $userId, string $role): void;

    /**
     * Replace the user's roles with exactly this one.
     */
    public function setRole(int $userId, string $role): void;

    public function destroySessions(int $userId): void;

    public function getMeta(int $userId, string $key): ?string;

    public function setMeta(int $userId, string $key, string $value): void;

    public function deleteMeta(int $userId, string $key): void;
}

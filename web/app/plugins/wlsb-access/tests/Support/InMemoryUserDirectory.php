<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Users\UserDirectory;

/**
 * In-memory UserDirectory double with introspection helpers for tests.
 */
final class InMemoryUserDirectory implements UserDirectory
{
    /** @var array<int, array<string, mixed>> */
    private array $users = [];

    private int $nextId = 1;

    /** @var array<int, int> */
    public array $sessionsDestroyed = [];

    public function findByEmail(string $email): ?int
    {
        foreach ($this->users as $id => $user) {
            if (strtolower((string) $user['email']) === strtolower($email)) {
                return $id;
            }
        }

        return null;
    }

    public function findByLogin(string $login): ?int
    {
        foreach ($this->users as $id => $user) {
            if ($user['login'] === $login) {
                return $id;
            }
        }

        return null;
    }

    public function create(string $login, string $email, string $password, string $role): int
    {
        $id = $this->nextId++;
        $this->users[$id] = [
            'login' => $login,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'status' => null,
            'requestedRole' => null,
            'meta' => [],
        ];

        return $id;
    }

    public function exists(int $userId): bool
    {
        return isset($this->users[$userId]);
    }

    public function getEmail(int $userId): ?string
    {
        return $this->users[$userId]['email'] ?? null;
    }

    public function setEmail(int $userId, string $email): void
    {
        $this->users[$userId]['email'] = $email;
    }

    public function getLogin(int $userId): ?string
    {
        return $this->users[$userId]['login'] ?? null;
    }

    public function getStatus(int $userId): ?LifecycleState
    {
        return $this->users[$userId]['status'] ?? null;
    }

    public function setStatus(int $userId, LifecycleState $state): void
    {
        $this->users[$userId]['status'] = $state;
    }

    public function getRequestedRole(int $userId): ?string
    {
        return $this->users[$userId]['requestedRole'] ?? null;
    }

    public function setRequestedRole(int $userId, string $role): void
    {
        $this->users[$userId]['requestedRole'] = $role;
    }

    public function setRole(int $userId, string $role): void
    {
        $this->users[$userId]['role'] = $role;
    }

    public function roleOf(int $userId): ?string
    {
        return $this->users[$userId]['role'] ?? null;
    }

    public function rolesOf(int $userId): array
    {
        $role = $this->users[$userId]['role'] ?? null;

        return $role !== null ? [$role] : [];
    }

    public function usersWithRole(string $role): array
    {
        $ids = [];
        foreach ($this->users as $id => $user) {
            if (($user['role'] ?? null) === $role) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function destroySessions(int $userId): void
    {
        $this->sessionsDestroyed[$userId] = ($this->sessionsDestroyed[$userId] ?? 0) + 1;
    }

    public function getMeta(int $userId, string $key): ?string
    {
        return $this->users[$userId]['meta'][$key] ?? null;
    }

    public function setMeta(int $userId, string $key, string $value): void
    {
        $this->users[$userId]['meta'][$key] = $value;
    }

    public function deleteMeta(int $userId, string $key): void
    {
        unset($this->users[$userId]['meta'][$key]);
    }
}

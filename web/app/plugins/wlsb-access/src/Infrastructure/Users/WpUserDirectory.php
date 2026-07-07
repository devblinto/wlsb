<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Users;

use RuntimeException;
use Wlsb\Access\Domain\Lifecycle\LifecycleState;
use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Users\UserMeta;
use WP_Session_Tokens;
use WP_User;

/**
 * WordPress-backed UserDirectory: the concrete bridge between the lifecycle
 * services and WordPress users / user-meta.
 */
final class WpUserDirectory implements UserDirectory
{
    public function findByEmail(string $email): ?int
    {
        $user = get_user_by('email', $email);

        return $user instanceof WP_User ? (int) $user->ID : null;
    }

    public function findByLogin(string $login): ?int
    {
        $user = get_user_by('login', $login);

        return $user instanceof WP_User ? (int) $user->ID : null;
    }

    public function create(string $login, string $email, string $password, string $role): int
    {
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $email,
            'user_pass' => $password,
            'role' => $role,
        ]);

        if (is_wp_error($userId)) {
            throw new RuntimeException($userId->get_error_message());
        }

        return (int) $userId;
    }

    public function exists(int $userId): bool
    {
        return get_userdata($userId) !== false;
    }

    public function getEmail(int $userId): ?string
    {
        $user = get_userdata($userId);

        return $user ? (string) $user->user_email : null;
    }

    public function setEmail(int $userId, string $email): void
    {
        wp_update_user(['ID' => $userId, 'user_email' => $email]);
    }

    public function getLogin(int $userId): ?string
    {
        $user = get_userdata($userId);

        return $user ? (string) $user->user_login : null;
    }

    public function getStatus(int $userId): ?LifecycleState
    {
        $value = get_user_meta($userId, UserMeta::STATUS, true);

        return is_string($value) && $value !== '' ? LifecycleState::tryFrom($value) : null;
    }

    public function setStatus(int $userId, LifecycleState $state): void
    {
        update_user_meta($userId, UserMeta::STATUS, $state->value);
        update_user_meta($userId, UserMeta::STATUS_UPDATED, (string) time());
    }

    public function getRequestedRole(int $userId): ?string
    {
        $value = get_user_meta($userId, UserMeta::REQUESTED_ROLE, true);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setRequestedRole(int $userId, string $role): void
    {
        update_user_meta($userId, UserMeta::REQUESTED_ROLE, $role);
    }

    public function setRole(int $userId, string $role): void
    {
        $user = new WP_User($userId);

        if ($user->exists()) {
            $user->set_role($role);
        }
    }

    public function rolesOf(int $userId): array
    {
        $user = get_userdata($userId);

        return $user ? array_values(array_map('strval', $user->roles)) : [];
    }

    public function usersWithRole(string $role): array
    {
        /** @var list<int|string> $ids */
        $ids = get_users(['role' => $role, 'fields' => 'ID']);

        return array_map('intval', $ids);
    }

    public function destroySessions(int $userId): void
    {
        WP_Session_Tokens::get_instance($userId)->destroy_all();
    }

    public function getMeta(int $userId, string $key): ?string
    {
        $value = get_user_meta($userId, $key, true);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setMeta(int $userId, string $key, string $value): void
    {
        update_user_meta($userId, $key, $value);
    }

    public function deleteMeta(int $userId, string $key): void
    {
        delete_user_meta($userId, $key);
    }
}

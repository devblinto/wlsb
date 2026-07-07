<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Roles\RolesGateway;

/**
 * In-memory RolesGateway double for unit tests. Seeded with existing roles and
 * introspectable via snapshot(), so tests assert the materialised state without
 * touching WordPress.
 */
final class InMemoryRolesGateway implements RolesGateway
{
    /**
     * @param array<string, array{name: string, caps: array<string, bool>}> $roles
     */
    public function __construct(private array $roles = []) {}

    public function roleExists(string $slug): bool
    {
        return isset($this->roles[$slug]);
    }

    public function roleName(string $slug): ?string
    {
        return $this->roles[$slug]['name'] ?? null;
    }

    public function roleCapabilities(string $slug): array
    {
        return $this->roles[$slug]['caps'] ?? [];
    }

    public function addRole(string $slug, string $name, array $capabilities): void
    {
        $this->roles[$slug] = [
            'name' => $name,
            'caps' => array_filter($capabilities),
        ];
    }

    public function removeRole(string $slug): void
    {
        unset($this->roles[$slug]);
    }

    public function renameRole(string $slug, string $name): void
    {
        if (isset($this->roles[$slug])) {
            $this->roles[$slug]['name'] = $name;
        }
    }

    public function grantCap(string $slug, string $cap): void
    {
        if (isset($this->roles[$slug])) {
            $this->roles[$slug]['caps'][$cap] = true;
        }
    }

    public function revokeCap(string $slug, string $cap): void
    {
        unset($this->roles[$slug]['caps'][$cap]);
    }

    /**
     * @return array<string, array{name: string, caps: array<string, bool>}>
     */
    public function snapshot(): array
    {
        return $this->roles;
    }
}

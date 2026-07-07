<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * Port through which the reconciler is the single writer of WordPress roles.
 *
 * Split into small primitives so the reconciler can (a) diff before writing and
 * (b) touch only what changed — fully owning its blueprint roles while making
 * purely additive capability grants onto roles it does not own.
 */
interface RolesGateway
{
    public function roleExists(string $slug): bool;

    /**
     * @return list<string> slugs of all roles that currently exist
     */
    public function allRoleSlugs(): array;

    public function roleName(string $slug): ?string;

    /**
     * @return array<string, bool> capability => granted (only present capabilities)
     */
    public function roleCapabilities(string $slug): array;

    /**
     * @param array<string, bool> $capabilities capability => granted
     */
    public function addRole(string $slug, string $name, array $capabilities): void;

    public function removeRole(string $slug): void;

    public function renameRole(string $slug, string $name): void;

    public function grantCap(string $slug, string $cap): void;

    public function revokeCap(string $slug, string $cap): void;
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * The admin's customisations layered on top of the code defaults: custom roles
 * created through the UI, per-role capability toggles, display-name renames, and
 * deletion tombstones. Persisted as a single option and merged over the defaults
 * by the RoleReconciler, so plugin updates can change defaults without clobbering
 * admin choices.
 */
final class RoleOverrides
{
    /**
     * @param list<RoleBlueprint>                       $customRoles
     * @param array<string, array<string, bool>>        $capToggles  roleSlug => (capKey => granted)
     * @param array<string, string>                     $renames     roleSlug => display name
     * @param list<string>                              $tombstones  owned role slugs to delete
     */
    public function __construct(
        private readonly array $customRoles = [],
        private readonly array $capToggles = [],
        private readonly array $renames = [],
        private readonly array $tombstones = [],
    ) {}

    /**
     * @return list<RoleBlueprint>
     */
    public function customRoles(): array
    {
        return $this->customRoles;
    }

    /**
     * @return array<string, bool> capKey => granted
     */
    public function togglesFor(string $roleSlug): array
    {
        return $this->capToggles[$roleSlug] ?? [];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function allToggles(): array
    {
        return $this->capToggles;
    }

    public function renameFor(string $roleSlug): ?string
    {
        return $this->renames[$roleSlug] ?? null;
    }

    /**
     * @return list<string>
     */
    public function tombstones(): array
    {
        return $this->tombstones;
    }

    public function isTombstoned(string $roleSlug): bool
    {
        return in_array($roleSlug, $this->tombstones, true);
    }

    /**
     * @return array{custom_roles: list<array<string, mixed>>, cap_toggles: array<string, array<string, bool>>, renames: array<string, string>, tombstones: list<string>}
     */
    public function toArray(): array
    {
        return [
            'custom_roles' => array_map(
                static fn(RoleBlueprint $role): array => [
                    'slug' => $role->slug,
                    'display_name' => $role->displayName,
                    'capabilities' => $role->capabilities,
                    'protected' => $role->protected,
                ],
                $this->customRoles,
            ),
            'cap_toggles' => $this->capToggles,
            'renames' => $this->renames,
            'tombstones' => $this->tombstones,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $customRoles = [];

        foreach (self::arrayValue($data, 'custom_roles') as $role) {
            if (! is_array($role) || ! isset($role['slug'], $role['display_name'])) {
                continue;
            }

            $customRoles[] = new RoleBlueprint(
                (string) $role['slug'],
                (string) $role['display_name'],
                array_values(array_map('strval', (array) ($role['capabilities'] ?? []))),
                (bool) ($role['protected'] ?? false),
            );
        }

        return new self(
            $customRoles,
            self::arrayValue($data, 'cap_toggles'),
            self::arrayValue($data, 'renames'),
            array_values(array_map('strval', self::arrayValue($data, 'tombstones'))),
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int|string, mixed>
     */
    private static function arrayValue(array $data, string $key): array
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    }
}

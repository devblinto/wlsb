<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Workflow;

/**
 * Which roles may be self-selected at registration and which require approval.
 *
 * The administrator role is hard-excluded from registration/self-selection here,
 * independent of what is configured — a defence-in-depth guard against
 * privilege-escalation via self-service signup.
 */
final class WorkflowConfig
{
    private const NEVER_REGISTERABLE = ['administrator'];

    /**
     * @param array<string, RoleWorkflow> $roles
     */
    public function __construct(
        private readonly array $roles,
        private readonly int $version = 1,
    ) {}

    public static function default(): self
    {
        return new self([
            'subscriber' => new RoleWorkflow('subscriber', registerable: true, selfSelectable: true, requiresApproval: false),
        ]);
    }

    public function forRole(string $slug): ?RoleWorkflow
    {
        return $this->roles[$slug] ?? null;
    }

    public function isRegisterable(string $slug): bool
    {
        if (in_array($slug, self::NEVER_REGISTERABLE, true)) {
            return false;
        }

        return ($this->roles[$slug] ?? null)?->registerable ?? false;
    }

    public function isSelfSelectable(string $slug): bool
    {
        $workflow = $this->roles[$slug] ?? null;

        return $this->isRegisterable($slug) && $workflow !== null && $workflow->selfSelectable;
    }

    public function requiresApproval(string $slug): bool
    {
        return ($this->roles[$slug] ?? null)?->requiresApproval ?? false;
    }

    /**
     * @return list<string>
     */
    public function selfSelectableRoles(): array
    {
        $slugs = [];
        foreach (array_keys($this->roles) as $slug) {
            if ($this->isSelfSelectable($slug)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return array{version:int, roles:array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        $roles = [];
        foreach ($this->roles as $slug => $workflow) {
            $roles[$slug] = [
                'registerable' => $workflow->registerable,
                'self_selectable' => $workflow->selfSelectable,
                'requires_approval' => $workflow->requiresApproval,
                'steps' => $workflow->steps,
            ];
        }

        return ['version' => $this->version, 'roles' => $roles];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $roles = [];
        $rawRoles = isset($data['roles']) && is_array($data['roles']) ? $data['roles'] : [];

        foreach ($rawRoles as $slug => $config) {
            if (! is_array($config)) {
                continue;
            }

            $roles[(string) $slug] = new RoleWorkflow(
                (string) $slug,
                (bool) ($config['registerable'] ?? false),
                (bool) ($config['self_selectable'] ?? false),
                (bool) ($config['requires_approval'] ?? false),
                isset($config['steps']) && is_array($config['steps']) ? array_values($config['steps']) : [],
            );
        }

        return new self($roles, (int) ($data['version'] ?? 1));
    }
}

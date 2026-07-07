<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * An instruction to ensure a single capability is present (or absent) on a role
 * the plugin does not own — additive, so it never disturbs the role's other
 * capabilities.
 *
 * A `protected` grant is re-asserted by the reconciler and cannot be overridden
 * by an admin toggle. This is how the administrator is guaranteed to keep
 * `wlsb_manage_access`, preventing self-lockout.
 */
final class CapabilityGrant
{
    public function __construct(
        public readonly string $roleSlug,
        public readonly string $capability,
        public readonly bool $granted = true,
        public readonly bool $protected = false,
    ) {}

    public function withGranted(bool $granted): self
    {
        return new self($this->roleSlug, $this->capability, $granted, $this->protected);
    }
}

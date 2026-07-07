<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * A role the plugin fully owns and materialises into WordPress with an exact
 * capability set — the zero-capability `wlsb_pending` holding role and any
 * admin-created custom roles.
 *
 * Owned roles differ from CapabilityGrant, which only adds/removes individual
 * capabilities onto roles the plugin does NOT own (e.g. the core administrator).
 */
final class RoleBlueprint
{
    /**
     * @param list<string> $capabilities capability keys granted to this role
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly array $capabilities = [],
        public readonly bool $protected = false,
    ) {}

    public function grants(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    public function withDisplayName(string $displayName): self
    {
        return new self($this->slug, $displayName, $this->capabilities, $this->protected);
    }

    /**
     * @param list<string> $capabilities
     */
    public function withCapabilities(array $capabilities): self
    {
        return new self($this->slug, $this->displayName, $capabilities, $this->protected);
    }
}

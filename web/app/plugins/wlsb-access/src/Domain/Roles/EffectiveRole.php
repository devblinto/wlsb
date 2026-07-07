<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * The computed desired state of an owned role after merging code defaults with
 * admin overrides — what the reconciler materialises into WordPress.
 */
final class EffectiveRole
{
    /**
     * @param list<string> $capabilities granted capability keys
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $displayName,
        public readonly array $capabilities,
        public readonly bool $protected = false,
    ) {}

    /**
     * @return array<string, bool> WordPress-shaped capability map (capability => true)
     */
    public function capabilityMap(): array
    {
        return array_fill_keys($this->capabilities, true);
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Capabilities;

/**
 * A single capability the plugin exposes for admin configuration.
 *
 * Capabilities are the atoms of the permission system. Grouping them and
 * marking some `protected` is what keeps "everything is configurable" bounded
 * and safe: protected capabilities cannot be stripped through the UI and are
 * re-asserted by the reconciler.
 */
final class Capability
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
        public readonly bool $protected = false,
        public readonly string $description = '',
    ) {}
}

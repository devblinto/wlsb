<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Capabilities;

/**
 * A UI grouping for related capabilities (e.g. "Access management", "Reports").
 *
 * Groups give the capability matrix a navigable structure so an admin is never
 * confronted with a flat wall of checkboxes.
 */
final class CapabilityGroup
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $order = 100,
    ) {}
}

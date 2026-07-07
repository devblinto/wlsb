<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

/**
 * Stops an administrator from removing their own access to the management
 * screens. This is a UI-level guard layered on top of the reconciler's protected
 * administrator grant, and it also protects non-administrator managers (who hold
 * the management cap via a custom grant that is not protected).
 */
final class SelfLockoutPolicy
{
    /**
     * @param list<string> $actorRoleSlugs the roles the current actor belongs to
     */
    public function __construct(private readonly array $actorRoleSlugs) {}

    /**
     * @param array<string, array<string, bool>> $toggles roleSlug => (capability => granted)
     */
    public function wouldLockOut(array $toggles, string $managementCapability): bool
    {
        foreach ($this->actorRoleSlugs as $slug) {
            if (($toggles[$slug][$managementCapability] ?? null) === false) {
                return true;
            }
        }

        return false;
    }
}

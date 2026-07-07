<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

use Wlsb\Access\Domain\Capabilities\Cap;

/**
 * Emergency recovery: if a site is misconfigured such that nobody can reach the
 * access-management screens, defining the `WLSB_ACCESS_BYPASS` constant to a
 * user's login or email grants that user `wlsb_manage_access` unconditionally.
 *
 * The identifier is compared in constant time, and the grant is applied in a
 * `user_has_cap` filter (see BreakGlassCapabilityFilter) — it is never persisted
 * to the database, so removing the constant fully revokes the bypass.
 */
final class BreakGlass
{
    public function __construct(private readonly ?string $bypassIdentifier) {}

    public function qualifies(string $userLogin, string $userEmail): bool
    {
        if ($this->bypassIdentifier === null || $this->bypassIdentifier === '') {
            return false;
        }

        return hash_equals($this->bypassIdentifier, $userLogin)
            || hash_equals($this->bypassIdentifier, $userEmail);
    }

    /**
     * @param array<string, bool> $capabilities
     * @return array<string, bool>
     */
    public function applyTo(array $capabilities, string $userLogin, string $userEmail): array
    {
        if ($this->qualifies($userLogin, $userEmail)) {
            $capabilities[Cap::MANAGE_ACCESS] = true;
        }

        return $capabilities;
    }
}

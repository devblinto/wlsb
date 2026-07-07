<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

/**
 * Prevents privilege escalation: an actor may only grant capabilities they
 * themselves hold. Administrators bypass this (they may grant any registered
 * capability), which matches WordPress's model where the administrator is the
 * ceiling of authority on a single site.
 *
 * Only *grants* can escalate; revoking a capability is always permitted here.
 */
final class CapabilityEscalationPolicy
{
    /**
     * @param list<string> $actorCapabilities
     */
    public function __construct(
        private readonly array $actorCapabilities,
        private readonly bool $actorIsAdministrator = false,
    ) {}

    public function mayGrant(string $capability): bool
    {
        return $this->actorIsAdministrator
            || in_array($capability, $this->actorCapabilities, true);
    }

    /**
     * Distinct capabilities the submission tries to grant that the actor is not
     * permitted to grant.
     *
     * @param array<string, array<string, bool>> $toggles roleSlug => (capability => granted)
     * @return list<string>
     */
    public function violations(array $toggles): array
    {
        $offenders = [];

        foreach ($toggles as $capabilities) {
            foreach ($capabilities as $capability => $granted) {
                if ($granted && ! $this->mayGrant((string) $capability)) {
                    $offenders[(string) $capability] = true;
                }
            }
        }

        return array_keys($offenders);
    }

    /**
     * @param array<string, array<string, bool>> $toggles
     */
    public function permits(array $toggles): bool
    {
        return $this->violations($toggles) === [];
    }
}

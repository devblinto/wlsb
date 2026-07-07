<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Application\Access\BreakGlass;

/**
 * Applies the break-glass grant through the `user_has_cap` filter.
 *
 * Runtime-only (never persisted), so removing the `WLSB_ACCESS_BYPASS` constant
 * immediately revokes it. Thin adapter over the tested BreakGlass service.
 */
final class BreakGlassCapabilityFilter
{
    public function __construct(private readonly BreakGlass $breakGlass) {}

    /**
     * @param array<string, bool> $allcaps
     * @param list<string>        $caps
     * @param array<int, mixed>   $args
     * @param object              $user
     * @return array<string, bool>
     */
    public function filter(array $allcaps, array $caps, array $args, object $user): array
    {
        if (! isset($user->user_login, $user->user_email)) {
            return $allcaps;
        }

        return $this->breakGlass->applyTo(
            $allcaps,
            (string) $user->user_login,
            (string) $user->user_email,
        );
    }
}

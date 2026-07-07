<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

/**
 * Turns a submitted capability-matrix form into minimal capability-toggle deltas
 * relative to the code defaults.
 *
 * A toggle is recorded only when the submitted checkbox state differs from the
 * default, so setting a box back to its default value clears the override rather
 * than freezing a now-redundant delta. Iteration is driven by the authoritative
 * presented roles/capabilities, so unexpected keys in the submission are ignored
 * (allow-list, not deny-list).
 */
final class MatrixFormMapper
{
    /**
     * @param array<string, array<string, mixed>> $checked      submitted caps[slug][cap], present only when checked
     * @param list<string>                        $roleSlugs    presented rows
     * @param list<string>                        $capabilityKeys presented columns
     * @param array<string, array<string, bool>>  $defaultState slug => (cap => default granted)
     * @return array<string, array<string, bool>> roleSlug => (cap => granted) deltas
     */
    public function toToggles(array $checked, array $roleSlugs, array $capabilityKeys, array $defaultState): array
    {
        $toggles = [];

        foreach ($roleSlugs as $slug) {
            foreach ($capabilityKeys as $capability) {
                $desired = isset($checked[$slug][$capability]);
                $default = $defaultState[$slug][$capability] ?? false;

                if ($desired !== $default) {
                    $toggles[$slug][$capability] = $desired;
                }
            }
        }

        return $toggles;
    }
}

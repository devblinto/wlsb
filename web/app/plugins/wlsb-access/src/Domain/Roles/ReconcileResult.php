<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Roles;

/**
 * A summary of what the reconciler changed when materialising roles, so callers
 * (WP-CLI, boot guard, admin UI) can report and log the outcome. Empty when the
 * live state already matched the desired state.
 */
final class ReconcileResult
{
    /**
     * @param list<string> $added         owned role slugs created
     * @param list<string> $removed       owned role slugs deleted
     * @param list<string> $updated       owned role slugs whose name/capabilities changed
     * @param list<string> $grantsApplied "slug:cap" grant/revoke changes onto non-owned roles
     */
    public function __construct(
        public readonly array $added = [],
        public readonly array $removed = [],
        public readonly array $updated = [],
        public readonly array $grantsApplied = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->added === []
            && $this->removed === []
            && $this->updated === []
            && $this->grantsApplied === [];
    }
}

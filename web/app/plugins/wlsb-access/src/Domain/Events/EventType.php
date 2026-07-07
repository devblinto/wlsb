<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Events;

/**
 * The audit event vocabulary. Backed by strings (stored in the log's
 * `event_type` column) so new events can be added without an ALTER — the PHP
 * enum is the source of truth, not a MySQL ENUM.
 *
 * Phase 1 covers role/capability events; registration, verification, and
 * approval events are added in later phases.
 */
enum EventType: string
{
    case RolesReconciled = 'roles.reconciled';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
}

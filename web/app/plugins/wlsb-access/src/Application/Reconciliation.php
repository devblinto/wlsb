<?php

declare(strict_types=1);

namespace Wlsb\Access\Application;

use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Roles\ReconcileResult;
use Wlsb\Access\Domain\Roles\RoleOverrideRepository;
use Wlsb\Access\Domain\Roles\RoleReconciler;
use Wlsb\Access\Domain\Roles\RolesGateway;
use Wlsb\Access\Infrastructure\Database\MigrationRunner;

/**
 * Idempotent, whole-plugin reconciliation: apply pending schema migrations, then
 * materialise roles/capabilities from `defaults ⊕ overrides`. Safe to call from
 * activation, the boot-time version guard, and `wp wlsb reconcile`.
 *
 * Migrations run first so the event-log table exists before we try to record the
 * reconciliation; a change is only logged when something actually changed, so
 * the audit log is not spammed on every boot.
 *
 * @phpstan-type RunResult array{migrations: list<int>, roles: ReconcileResult}
 */
final class Reconciliation
{
    public function __construct(
        private readonly MigrationRunner $migrations,
        private readonly RoleReconciler $reconciler,
        private readonly RoleOverrideRepository $overrides,
        private readonly RolesGateway $gateway,
        private readonly EventLogger $log,
    ) {}

    /**
     * @return array{migrations: list<int>, roles: ReconcileResult}
     */
    public function run(): array
    {
        $applied = $this->migrations->run();

        $result = $this->reconciler->materialize($this->overrides->load(), $this->gateway);

        if (! $result->isEmpty()) {
            $this->log->log(new Event(
                EventType::RolesReconciled,
                'system',
                context: [
                    'added' => $result->added,
                    'removed' => $result->removed,
                    'updated' => $result->updated,
                    'grants' => $result->grantsApplied,
                    'migrations' => $applied,
                ],
            ));
        }

        return ['migrations' => $applied, 'roles' => $result];
    }
}

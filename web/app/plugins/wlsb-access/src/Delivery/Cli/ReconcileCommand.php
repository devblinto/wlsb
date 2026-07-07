<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Cli;

use Closure;
use Wlsb\Access\Domain\Roles\ReconcileResult;
use WP_CLI;

/**
 * `wp wlsb reconcile` — applies pending schema migrations and materialises
 * roles/capabilities idempotently.
 *
 * This is the preferred, deterministic lifecycle trigger for the Ansible deploy
 * pipeline, where the plugin activation hook may not fire because the site runs
 * with `DISALLOW_FILE_MODS`.
 *
 * The command depends only on a reconcile closure returning the run summary,
 * which keeps it fully unit-testable without WordPress or WP-CLI.
 */
final class ReconcileCommand
{
    /**
     * @param Closure(): array{migrations: list<int>, roles: ReconcileResult} $reconcile
     */
    public function __construct(private readonly Closure $reconcile) {}

    /**
     * @param list<string>          $args
     * @param array<string, string> $assocArgs
     */
    public function __invoke(array $args = [], array $assocArgs = []): void
    {
        $summary = ($this->reconcile)();
        $roles = $summary['roles'];

        $migrations = count($summary['migrations']);
        $roleChanges = count($roles->added)
            + count($roles->removed)
            + count($roles->updated)
            + count($roles->grantsApplied);

        if ($migrations === 0 && $roleChanges === 0) {
            WP_CLI::success('wlsb-access is already up to date; nothing to reconcile.');

            return;
        }

        WP_CLI::success(sprintf(
            'wlsb-access reconciled: %d migration(s), %d role change(s).',
            $migrations,
            $roleChanges,
        ));
    }
}

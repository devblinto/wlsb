<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Lifecycle;

/**
 * The allowed edges of the account state machine. Every status change funnels
 * through UserLifecycleManager, which consults this table, so illegal edges are
 * impossible regardless of which trigger fired.
 */
final class LifecycleTransitions
{
    /**
     * @var array<string, list<string>> from-state value => allowed to-state values
     */
    private const ALLOWED = [
        'pending_email_verification' => ['pending_approval', 'active'],
        'pending_approval' => ['active', 'rejected'],
        'active' => ['suspended'],
        'suspended' => ['active'],
        'rejected' => ['pending_email_verification', 'pending_approval'],
    ];

    private function __construct() {}

    public static function isAllowed(LifecycleState $from, LifecycleState $to): bool
    {
        return in_array($to->value, self::ALLOWED[$from->value] ?? [], true);
    }
}

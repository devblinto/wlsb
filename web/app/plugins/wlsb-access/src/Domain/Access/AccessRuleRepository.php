<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * Loads and persists the admin-configured access rules.
 */
interface AccessRuleRepository
{
    /**
     * @return list<AccessRule>
     */
    public function all(): array;

    /**
     * @param list<AccessRule> $rules
     */
    public function save(array $rules): void;
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Access\AccessRule;
use Wlsb\Access\Domain\Access\AccessRuleRepository;

/**
 * In-memory AccessRuleRepository double; counts all() calls so memoisation can
 * be asserted.
 */
final class InMemoryAccessRuleRepository implements AccessRuleRepository
{
    public int $allCalls = 0;

    /**
     * @param list<AccessRule> $rules
     */
    public function __construct(private array $rules = []) {}

    public function all(): array
    {
        $this->allCalls++;

        return $this->rules;
    }

    public function save(array $rules): void
    {
        $this->rules = array_values($rules);
    }
}

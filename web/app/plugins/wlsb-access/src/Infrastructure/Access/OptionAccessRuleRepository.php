<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Access;

use Wlsb\Access\Domain\Access\AccessRule;
use Wlsb\Access\Domain\Access\AccessRuleRepository;

/**
 * Stores access rules in a single autoloaded option — read on every front-end
 * request, so autoloading keeps it a single, already-cached read.
 */
final class OptionAccessRuleRepository implements AccessRuleRepository
{
    public function __construct(private readonly string $optionName) {}

    public function all(): array
    {
        $data = get_option($this->optionName, []);

        if (! is_array($data)) {
            return [];
        }

        $rules = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $rule = AccessRule::fromArray($row);
                if ($rule !== null) {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    public function save(array $rules): void
    {
        update_option(
            $this->optionName,
            array_map(static fn(AccessRule $rule): array => $rule->toArray(), $rules),
            true,
        );
    }
}

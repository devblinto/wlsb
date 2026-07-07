<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Workflow;

use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;

/**
 * Stores the workflow config in a single autoloaded option, falling back to the
 * safe default (subscribers self-register, no approval) when unset.
 */
final class OptionWorkflowConfigStore implements WorkflowConfigStore
{
    public function __construct(private readonly string $optionName) {}

    public function load(): WorkflowConfig
    {
        $data = get_option($this->optionName, null);

        if (! is_array($data) || $data === []) {
            return WorkflowConfig::default();
        }

        return WorkflowConfig::fromArray($data);
    }

    public function save(WorkflowConfig $config): void
    {
        update_option($this->optionName, $config->toArray(), true);
    }
}

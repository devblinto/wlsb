<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;

/**
 * In-memory WorkflowConfigStore double.
 */
final class InMemoryWorkflowConfigStore implements WorkflowConfigStore
{
    public function __construct(private WorkflowConfig $config = new WorkflowConfig([])) {}

    public function load(): WorkflowConfig
    {
        return $this->config;
    }

    public function save(WorkflowConfig $config): void
    {
        $this->config = $config;
    }
}

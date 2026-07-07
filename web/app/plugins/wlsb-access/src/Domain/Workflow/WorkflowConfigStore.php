<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Workflow;

/**
 * Loads and persists the registration/approval configuration.
 */
interface WorkflowConfigStore
{
    public function load(): WorkflowConfig;

    public function save(WorkflowConfig $config): void;
}

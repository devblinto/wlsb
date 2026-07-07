<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Approval;

use RuntimeException;
use Throwable;
use Wlsb\Access\Domain\Approval\ApprovalRepository;
use Wlsb\Access\Domain\Approval\ApprovalRequest;
use Wlsb\Access\Domain\Approval\ApprovalStep;
use Wlsb\Access\Domain\Approval\ApproverType;
use Wlsb\Access\Domain\Approval\RequestStatus;
use Wlsb\Access\Domain\Approval\StepStatus;

/**
 * WordPress-backed ApprovalRepository. All reads use `%i` identifier and
 * `%d`/`%s` value placeholders via `$wpdb->prepare()`; writes use
 * `$wpdb->insert()` / `update()`, which parameterise automatically.
 *
 * Each method aliases the injected handle to a local `$wpdb` — the constructor
 * receives it (kept loosely typed as `object` so the class loads without a full
 * WordPress bootstrap), and the local name lets static analysis verify the
 * prepared queries. Behaviour is verified end-to-end in DDEV.
 */
final class WpdbApprovalRepository implements ApprovalRepository
{
    private readonly string $requestsTable;

    private readonly string $stepsTable;

    public function __construct(private readonly object $wpdb)
    {
        $this->requestsTable = $wpdb->prefix . 'wlsb_approval_requests';
        $this->stepsTable = $wpdb->prefix . 'wlsb_approval_steps';
    }

    public function findOpenByUser(int $userId): ?ApprovalRequest
    {
        $wpdb = $this->wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE user_id = %d AND status = %s ORDER BY id DESC LIMIT 1',
                $this->requestsTable,
                $userId,
                RequestStatus::Pending->value,
            ),
            ARRAY_A,
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function find(int $requestId): ?ApprovalRequest
    {
        $wpdb = $this->wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE id = %d', $this->requestsTable, $requestId),
            ARRAY_A,
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findForUpdate(int $requestId): ?ApprovalRequest
    {
        $wpdb = $this->wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE id = %d FOR UPDATE', $this->requestsTable, $requestId),
            ARRAY_A,
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function create(int $userId, string $role, int $workflowVersion, array $steps): ApprovalRequest
    {
        $wpdb = $this->wpdb;
        $now = current_time('mysql', true);
        $firstOrder = $steps === [] ? 1 : min(array_map(static fn($s): int => $s->order, $steps));

        $wpdb->insert($this->requestsTable, [
            'user_id' => $userId,
            'requested_role' => $role,
            'workflow_key' => $role,
            'workflow_version' => $workflowVersion,
            'status' => RequestStatus::Pending->value,
            'current_step_order' => $firstOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $requestId = (int) $wpdb->insert_id;

        foreach ($steps as $definition) {
            $wpdb->insert($this->stepsTable, [
                'request_id' => $requestId,
                'step_order' => $definition->order,
                'approver_type' => $definition->approverType->value,
                'approver_role' => $definition->approverRole,
                'approver_user_id' => $definition->approverUserId,
                'status' => StepStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->find($requestId) ?? throw new RuntimeException('Failed to load created approval request.');
    }

    public function updateStep(int $stepId, StepStatus $status, int $actedBy, ?string $note): void
    {
        $now = current_time('mysql', true);

        $this->wpdb->update(
            $this->stepsTable,
            [
                'status' => $status->value,
                'acted_by' => $actedBy,
                'acted_at' => $now,
                'note' => $note,
                'updated_at' => $now,
            ],
            ['id' => $stepId],
        );
    }

    public function setCurrentStep(int $requestId, int $order): void
    {
        $this->wpdb->update(
            $this->requestsTable,
            ['current_step_order' => $order, 'updated_at' => current_time('mysql', true)],
            ['id' => $requestId],
        );
    }

    public function closeRequest(int $requestId, RequestStatus $status, ?int $decidedBy): void
    {
        $now = current_time('mysql', true);

        $this->wpdb->update(
            $this->requestsTable,
            [
                'status' => $status->value,
                'decided_by' => $decidedBy,
                'decided_at' => $now,
                'updated_at' => $now,
            ],
            ['id' => $requestId],
        );
    }

    public function listPending(): array
    {
        $wpdb = $this->wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i WHERE status = %s ORDER BY created_at ASC', $this->requestsTable, RequestStatus::Pending->value),
            ARRAY_A,
        );

        return array_map([$this, 'hydrate'], is_array($rows) ? $rows : []);
    }

    public function transactionally(callable $fn): mixed
    {
        $wpdb = $this->wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            $result = $fn();
            $wpdb->query('COMMIT');

            return $result;
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ApprovalRequest
    {
        $requestId = (int) $row['id'];

        return new ApprovalRequest(
            $requestId,
            (int) $row['user_id'],
            (string) $row['requested_role'],
            (int) $row['workflow_version'],
            RequestStatus::from((string) $row['status']),
            (int) $row['current_step_order'],
            $this->loadSteps($requestId),
        );
    }

    /**
     * @return list<ApprovalStep>
     */
    private function loadSteps(int $requestId): array
    {
        $wpdb = $this->wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM %i WHERE request_id = %d ORDER BY step_order ASC', $this->stepsTable, $requestId),
            ARRAY_A,
        );

        return array_map(
            static fn(array $row): ApprovalStep => new ApprovalStep(
                (int) $row['id'],
                (int) $row['request_id'],
                (int) $row['step_order'],
                ApproverType::from((string) $row['approver_type']),
                $row['approver_role'] !== null ? (string) $row['approver_role'] : null,
                $row['approver_user_id'] !== null ? (int) $row['approver_user_id'] : null,
                StepStatus::from((string) $row['status']),
                $row['acted_by'] !== null ? (int) $row['acted_by'] : null,
                $row['note'] !== null ? (string) $row['note'] : null,
            ),
            is_array($rows) ? $rows : [],
        );
    }
}

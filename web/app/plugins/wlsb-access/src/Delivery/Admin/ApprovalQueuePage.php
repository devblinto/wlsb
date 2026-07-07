<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Application\Approval\ApprovalDecision;
use Wlsb\Access\Application\Approval\ApprovalService;
use Wlsb\Access\Domain\Approval\ApprovalRequest;
use Wlsb\Access\Domain\Approval\ApproverType;
use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Users\UserDirectory;

/**
 * Admin submenu: the pending-approval queue. Approvers act on requests here; the
 * ApprovalService enforces who may act and serialises concurrent decisions.
 */
final class ApprovalQueuePage
{
    use AccessAdminSupport;

    public const SLUG = 'wlsb-approvals';

    public const ACTION = 'wlsb_decide_approval';

    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly UserDirectory $users,
    ) {}

    public function registerSubmenu(): void
    {
        add_submenu_page(
            AccessMatrixPage::SLUG,
            __('Approvals', 'wlsb-access'),
            __('Approvals', 'wlsb-access'),
            Cap::APPROVE_REQUESTS,
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        $this->assertCan(Cap::APPROVE_REQUESTS);

        $rows = array_map([$this, 'toRow'], $this->approvals->listPending());
        $notice = $this->currentNotice();
        $formAction = admin_url('admin-post.php');

        require dirname(__DIR__, 3) . '/templates/admin/approvals.php';
    }

    public function handleDecision(): void
    {
        $this->assertCan(Cap::APPROVE_REQUESTS);
        check_admin_referer(self::ACTION);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $requestId = isset($_POST['request_id']) ? absint(wp_unslash($_POST['request_id'])) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';

        $actorId = get_current_user_id();
        $result = $decision === 'approve'
            ? $this->approvals->approve($requestId, $actorId)
            : ($decision === 'reject' ? $this->approvals->reject($requestId, $actorId) : ApprovalDecision::AlreadyClosed);

        $this->redirectTo(self::SLUG, ['wlsb_notice' => $this->noticeFor($result)]);
    }

    private function noticeFor(ApprovalDecision $decision): string
    {
        return match ($decision) {
            ApprovalDecision::Approved => 'approved',
            ApprovalDecision::Rejected => 'rejected',
            ApprovalDecision::Advanced => 'advanced',
            ApprovalDecision::Forbidden => 'forbidden',
            ApprovalDecision::AlreadyClosed => 'closed',
        };
    }

    /**
     * @return array{id:int, login:string, email:string, role:string, approver:string}
     */
    private function toRow(ApprovalRequest $request): array
    {
        $step = $request->currentStep();

        if ($step === null) {
            $approver = '';
        } elseif ($step->approverType === ApproverType::User) {
            $approver = (string) ($this->users->getLogin((int) $step->approverUserId) ?? ('#' . $step->approverUserId));
        } else {
            $approver = (string) ($step->approverRole ?? '');
        }

        return [
            'id' => $request->id,
            'login' => (string) ($this->users->getLogin($request->userId) ?? ''),
            'email' => (string) ($this->users->getEmail($request->userId) ?? ''),
            'role' => $request->requestedRole,
            'approver' => $approver,
        ];
    }

    private function currentNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        $notice = isset($_GET['wlsb_notice']) ? sanitize_key(wp_unslash($_GET['wlsb_notice'])) : '';

        return in_array($notice, ['approved', 'rejected', 'advanced', 'forbidden', 'closed'], true) ? $notice : '';
    }
}

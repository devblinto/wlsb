<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Roles\Role;
use Wlsb\Access\Domain\Workflow\RoleWorkflow;
use Wlsb\Access\Domain\Workflow\WorkflowConfig;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;

/**
 * Admin submenu: configure, per role, whether it is self-registerable and
 * whether registration requires approval (and by which role). Administrator is
 * never listed — it can never be self-selected.
 */
final class WorkflowConfigPage
{
    use AccessAdminSupport;

    public const SLUG = 'wlsb-workflow';

    public const ACTION = 'wlsb_save_workflow';

    public function __construct(private readonly WorkflowConfigStore $store) {}

    public function registerSubmenu(): void
    {
        add_submenu_page(
            AccessMatrixPage::SLUG,
            __('Registration workflow', 'wlsb-access'),
            __('Workflow', 'wlsb-access'),
            Cap::MANAGE_APPROVALS,
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        $this->assertCan(Cap::MANAGE_APPROVALS);

        $config = $this->store->load();
        $roles = $this->configurableRoles();
        $notice = $this->currentNotice();
        $formAction = admin_url('admin-post.php');

        require dirname(__DIR__, 3) . '/templates/admin/workflow.php';
    }

    public function handleSave(): void
    {
        $this->assertCan(Cap::MANAGE_APPROVALS);
        check_admin_referer(self::ACTION);

        $version = $this->store->load()->version() + 1;
        $roles = [];

        foreach (array_keys($this->configurableRoles()) as $slug) {
            $registerable = $this->checked('registerable', $slug);
            $requiresApproval = $this->checked('requires_approval', $slug);

            if (! $registerable && ! $requiresApproval) {
                continue;
            }

            $steps = $requiresApproval
                ? [['order' => 1, 'approver_type' => 'role', 'approver_role' => $this->approver($slug)]]
                : [];

            $roles[$slug] = new RoleWorkflow(
                $slug,
                $registerable,
                $this->checked('self_selectable', $slug),
                $requiresApproval,
                $steps,
            );
        }

        $this->store->save(new WorkflowConfig($roles, $version));
        $this->redirectTo(self::SLUG, ['wlsb_notice' => 'saved']);
    }

    /**
     * @return array<string, string> slug => display name (excludes administrator + holding role)
     */
    private function configurableRoles(): array
    {
        $roles = [];
        foreach (wp_roles()->role_names as $slug => $name) {
            if ($slug === 'administrator' || $slug === Role::PENDING) {
                continue;
            }
            $roles[(string) $slug] = (string) translate_user_role((string) $name);
        }

        return $roles;
    }

    private function checked(string $group, string $slug): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset($_POST[$group]) && is_array($_POST[$group]) && isset($_POST[$group][$slug]);
    }

    private function approver(string $slug): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $value = isset($_POST['approver'][$slug]) ? sanitize_key(wp_unslash($_POST['approver'][$slug])) : '';

        return $value !== '' ? $value : 'administrator';
    }

    private function currentNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        return isset($_GET['wlsb_notice']) && sanitize_key(wp_unslash($_GET['wlsb_notice'])) === 'saved' ? 'saved' : '';
    }
}

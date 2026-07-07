<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Domain\Capabilities\Cap;

/**
 * Shared WordPress-boundary helpers for the access admin screens: capability
 * gating, actor IP capture for the audit log, and post-redirect-get redirects.
 */
trait AccessAdminSupport
{
    private function assertCanManage(): void
    {
        if (! current_user_can(Cap::MANAGE_ACCESS)) {
            wp_die(
                esc_html__('You do not have permission to manage access.', 'wlsb-access'),
                '',
                ['response' => 403],
            );
        }
    }

    private function actorIp(): ?string
    {
        $raw = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        if ($raw === '') {
            return null;
        }

        $packed = inet_pton($raw);

        return $packed === false ? null : $packed;
    }

    /**
     * @param array<string, string> $args
     */
    private function redirectTo(string $pageSlug, array $args): void
    {
        wp_safe_redirect(add_query_arg(
            array_merge(['page' => $pageSlug], $args),
            admin_url('admin.php'),
        ));

        exit;
    }
}

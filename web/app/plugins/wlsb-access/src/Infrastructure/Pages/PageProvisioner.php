<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Pages;

/**
 * Ensures the front-end pages that host the registration/login/verification
 * shortcodes exist, so the flow works out of the box. Idempotent: runs during
 * reconcile and only creates a page when its stored id no longer resolves to a
 * live page. Page ids are kept in the `wlsb_access_pages` option.
 */
final class PageProvisioner
{
    private const OPTION = 'wlsb_access_pages';

    public function ensure(): void
    {
        $ids = get_option(self::OPTION, []);
        $ids = is_array($ids) ? $ids : [];
        $changed = false;

        foreach ($this->pages() as $key => $page) {
            $existing = isset($ids[$key]) ? get_post((int) $ids[$key]) : null;

            if ($existing !== null && $existing->post_status !== 'trash') {
                continue;
            }

            $pageId = wp_insert_post([
                'post_title' => $page['title'],
                'post_content' => $page['content'],
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
            ]);

            if (! is_wp_error($pageId) && $pageId > 0) {
                $ids[$key] = (int) $pageId;
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::OPTION, $ids, true);
        }
    }

    public function pageId(string $key): ?int
    {
        $ids = get_option(self::OPTION, []);

        return is_array($ids) && isset($ids[$key]) ? (int) $ids[$key] : null;
    }

    public function url(string $key): string
    {
        $id = $this->pageId($key);
        $url = $id !== null ? get_permalink($id) : false;

        return $url !== false ? $url : home_url('/');
    }

    /**
     * @return array<string, array{title:string, content:string}>
     */
    private function pages(): array
    {
        return [
            'register' => ['title' => __('Register', 'wlsb-access'), 'content' => '[wlsb_register]'],
            'login' => ['title' => __('Log in', 'wlsb-access'), 'content' => '[wlsb_login]'],
            'verify' => ['title' => __('Verify your email', 'wlsb-access'), 'content' => '[wlsb_verify_email]'],
            'resend' => ['title' => __('Resend verification', 'wlsb-access'), 'content' => '[wlsb_resend_verification]'],
            'status' => ['title' => __('Registration status', 'wlsb-access'), 'content' => '[wlsb_registration_status]'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Access;

use WP_Post;

/**
 * A post/page metabox to require a capability for viewing the content. The
 * FrontendGuard (front end) and RestGuard (REST) both enforce it.
 */
final class ContentAccessMetabox
{
    private const NONCE = 'wlsb_access_meta';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add']);
        add_action('save_post', [$this, 'save'], 10, 2);
    }

    public function add(): void
    {
        add_meta_box(
            'wlsb-access-content',
            __('Access control', 'wlsb-access'),
            [$this, 'render'],
            ['post', 'page'],
            'side',
        );
    }

    public function render(WP_Post $post): void
    {
        $value = (string) get_post_meta($post->ID, FrontendGuard::CAP_META, true);
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        echo '<p><label for="wlsb_required_cap">'
            . esc_html__('Required capability', 'wlsb-access')
            . '</label></p>';
        echo '<input type="text" id="wlsb_required_cap" name="wlsb_required_cap" class="widefat" value="'
            . esc_attr($value) . '" placeholder="wlsb_view_content" />';
        echo '<p class="description">'
            . esc_html__('Leave blank for public. Otherwise, only users with this capability can view the content.', 'wlsb-access')
            . '</p>';
    }

    public function save(int $postId, WP_Post $post): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_key(wp_unslash($_POST[self::NONCE . '_nonce'])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }

        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || ! current_user_can('edit_post', $postId)) {
            return;
        }

        $cap = isset($_POST['wlsb_required_cap']) ? sanitize_key(wp_unslash($_POST['wlsb_required_cap'])) : '';

        if ($cap === '') {
            delete_post_meta($postId, FrontendGuard::CAP_META);
        } else {
            update_post_meta($postId, FrontendGuard::CAP_META, $cap);
        }
    }
}

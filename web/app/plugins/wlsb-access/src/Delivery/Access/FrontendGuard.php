<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Access;

use Wlsb\Access\Application\Access\AccessPolicy;
use Wlsb\Access\Domain\Access\AccessDecision;
use Wlsb\Access\Domain\Access\RequestContext;
use Wlsb\Access\Domain\Access\RuleEffect;
use WP_Post;

/**
 * Enforces access on the front end at `template_redirect` (query resolved,
 * nothing rendered yet). Applies both the per-content required capability (the
 * post metabox) and the global access rules, then denies / redirects / sends to
 * login as decided.
 */
final class FrontendGuard
{
    public const CAP_META = '_wlsb_required_cap';

    public function __construct(private readonly AccessPolicy $policy) {}

    public function guard(): void
    {
        if (is_admin()) {
            return;
        }

        $context = $this->context();

        // Content-level: a page/post with a required capability the user lacks.
        if ($context->postId !== null) {
            $requiredCap = (string) get_post_meta($context->postId, self::CAP_META, true);
            if ($requiredCap !== '' && ! $this->policy->authorize($requiredCap)) {
                $this->apply(AccessDecision::block(RuleEffect::Deny));

                return;
            }
        }

        // Global rules.
        $decision = $this->policy->decide($context);
        if (! $decision->isAllowed()) {
            $this->apply($decision);
        }
    }

    private function context(): RequestContext
    {
        $postId = null;
        $postType = null;

        if (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof WP_Post) {
                $postId = (int) $post->ID;
                $postType = (string) $post->post_type;
            }
        }

        $uri = isset($_SERVER['REQUEST_URI'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
            : '/';
        $path = (string) (wp_parse_url($uri, PHP_URL_PATH) ?: '/');

        return new RequestContext($path, $postId, $postType, is_front_page());
    }

    private function apply(AccessDecision $decision): void
    {
        if ($decision->effect === RuleEffect::Redirect) {
            $target = $decision->redirect !== null && $decision->redirect !== '' ? $decision->redirect : home_url('/');
            wp_safe_redirect($target);
            exit;
        }

        // Deny / RequireLogin: guests go to login (with a return path); logged-in
        // users who still lack access get a 403.
        if (! is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        status_header(403);
        wp_die(
            esc_html__('You do not have permission to view this page.', 'wlsb-access'),
            '',
            ['response' => 403],
        );
    }
}

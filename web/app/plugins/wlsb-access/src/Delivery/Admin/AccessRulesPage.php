<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Domain\Access\AccessRule;
use Wlsb\Access\Domain\Access\AccessRuleRepository;
use Wlsb\Access\Domain\Access\MatcherKind;
use Wlsb\Access\Domain\Access\RuleEffect;
use Wlsb\Access\Domain\Capabilities\Cap;

/**
 * Admin submenu: manage global access rules (matcher + required capability +
 * effect). Content-level access uses the per-post metabox instead; these rules
 * cover cross-cutting cases (a URL prefix, a whole post type).
 */
final class AccessRulesPage
{
    use AccessAdminSupport;

    public const SLUG = 'wlsb-access-rules';

    public const ACTION = 'wlsb_save_rules';

    public function __construct(private readonly AccessRuleRepository $rules) {}

    public function registerSubmenu(): void
    {
        add_submenu_page(
            AccessMatrixPage::SLUG,
            __('Access rules', 'wlsb-access'),
            __('Rules', 'wlsb-access'),
            Cap::MANAGE_ACCESS,
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        $this->assertCanManage();

        $rules = $this->rules->all();
        $notice = $this->currentNotice();
        $formAction = admin_url('admin-post.php');

        require dirname(__DIR__, 3) . '/templates/admin/rules.php';
    }

    public function handleSave(): void
    {
        $this->assertCanManage();
        check_admin_referer(self::ACTION);

        $deletes = $this->submittedDeletes();
        $kept = array_values(array_filter(
            $this->rules->all(),
            static fn(AccessRule $rule): bool => ! in_array($rule->id, $deletes, true),
        ));

        $new = $this->submittedNewRule();
        if ($new !== null) {
            $kept[] = $new;
        }

        $this->rules->save($kept);
        $this->redirectTo(self::SLUG, ['wlsb_notice' => 'saved']);
    }

    /**
     * @return list<string>
     */
    private function submittedDeletes(): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = isset($_POST['delete']) && is_array($_POST['delete']) ? wp_unslash($_POST['delete']) : [];

        return array_values(array_map(static fn($id): string => sanitize_text_field((string) $id), (array) $raw));
    }

    private function submittedNewRule(): ?AccessRule
    {
        $kind = MatcherKind::tryFrom($this->post('new_kind'));
        $effect = RuleEffect::tryFrom($this->post('new_effect'));
        $capability = $this->post('new_capability');
        $value = $this->post('new_value');

        if ($kind === null || $effect === null) {
            return null;
        }

        // Require enough to be a meaningful rule.
        if ($kind !== MatcherKind::Any && $value === '') {
            return null;
        }

        return new AccessRule(
            substr(md5(uniqid($capability . $value, true)), 0, 12),
            $kind,
            $value,
            $capability,
            $effect,
            $this->post('new_redirect') !== '' ? $this->post('new_redirect') : null,
            max(0, (int) $this->post('new_priority')),
        );
    }

    private function post(string $key): string
    {
        // Nonce verified in handleSave() before any field is read.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function currentNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        return isset($_GET['wlsb_notice']) && sanitize_key(wp_unslash($_GET['wlsb_notice'])) === 'saved' ? 'saved' : '';
    }
}

<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Application\Access\CapabilityEscalationPolicy;
use Wlsb\Access\Application\Access\MatrixBuilder;
use Wlsb\Access\Application\Access\MatrixFormMapper;
use Wlsb\Access\Application\Access\SelfLockoutPolicy;
use Wlsb\Access\Application\Reconciliation;
use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;
use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Roles\RoleOverrideRepository;

/**
 * Admin screen: the roles × capabilities matrix.
 *
 * Thin WordPress-boundary controller. Every decision (which toggles are deltas,
 * whether a grant would escalate privilege, whether it would lock the actor out)
 * is delegated to the unit-tested services; this class only handles menu
 * registration, capability gating, nonce/CSRF, input sanitisation, rendering,
 * and redirects.
 */
final class AccessMatrixPage
{
    use AccessAdminSupport;

    public const SLUG = 'wlsb-access';

    public const ACTION = 'wlsb_save_access';

    public function __construct(
        private readonly CapabilityRegistry $capabilities,
        private readonly MatrixBuilder $matrix,
        private readonly RoleOverrideRepository $overrides,
        private readonly MatrixFormMapper $mapper,
        private readonly Reconciliation $reconciliation,
        private readonly EventLogger $log,
    ) {}

    public function registerMenu(): void
    {
        add_menu_page(
            __('Access management', 'wlsb-access'),
            __('Access', 'wlsb-access'),
            Cap::MANAGE_ACCESS,
            self::SLUG,
            [$this, 'render'],
            'dashicons-lock',
            71,
        );
    }

    public function render(): void
    {
        $this->assertCanManage();

        $roles = $this->roleList();
        $roleSlugs = array_keys($roles);
        $capabilityKeys = $this->capabilityKeys();

        $checked = $this->matrix->checkedState($roleSlugs, $capabilityKeys, $this->overrides->load());
        $protectedPairs = $this->matrix->protectedPairs();
        $groups = $this->capabilities->groups();
        $capabilities = $this->capabilities;
        $notice = $this->currentNotice();
        $formAction = admin_url('admin-post.php');

        require dirname(__DIR__, 3) . '/templates/admin/matrix.php';
    }

    public function handleSave(): void
    {
        $this->assertCanManage();
        check_admin_referer(self::ACTION);

        $roles = $this->roleList();
        $roleSlugs = array_keys($roles);
        $capabilityKeys = $this->capabilityKeys();

        $defaultState = $this->matrix->defaultState($roleSlugs, $capabilityKeys);
        $toggles = $this->mapper->toToggles($this->submittedChecks(), $roleSlugs, $capabilityKeys, $defaultState);
        $toggles = $this->stripProtected($toggles);

        $user = wp_get_current_user();
        $actorCapabilities = array_keys(array_filter((array) $user->allcaps));
        $isAdministrator = in_array('administrator', (array) $user->roles, true);

        $escalation = new CapabilityEscalationPolicy($actorCapabilities, $isAdministrator);
        if (! $escalation->permits($toggles)) {
            $this->redirectTo(self::SLUG, ['wlsb_error' => 'escalation']);
        }

        $lockout = new SelfLockoutPolicy((array) $user->roles);
        if ($lockout->wouldLockOut($toggles, Cap::MANAGE_ACCESS)) {
            $this->redirectTo(self::SLUG, ['wlsb_error' => 'lockout']);
        }

        $this->overrides->save($this->overrides->load()->withCapToggles($toggles));
        $this->reconciliation->run();

        $this->log->log(new Event(
            EventType::RoleUpdated,
            'role',
            actorId: get_current_user_id() ?: null,
            actorIp: $this->actorIp(),
            context: ['toggles' => $toggles],
        ));

        $this->redirectTo(self::SLUG, ['wlsb_notice' => 'saved']);
    }

    /**
     * @return array<string, string> slug => display name
     */
    private function roleList(): array
    {
        $roles = [];

        foreach (wp_roles()->roles as $slug => $data) {
            $roles[(string) $slug] = is_array($data) && isset($data['name']) ? (string) $data['name'] : (string) $slug;
        }

        return $roles;
    }

    /**
     * @return list<string>
     */
    private function capabilityKeys(): array
    {
        return array_map(
            static fn($capability): string => $capability->key,
            $this->capabilities->all(),
        );
    }

    /**
     * Sanitised map of the checked matrix boxes. Iteration in the mapper is
     * driven by the authoritative role/capability lists, so extra keys here are
     * harmless; we still sanitise every key.
     *
     * @return array<string, array<string, bool>>
     */
    private function submittedChecks(): array
    {
        // Nonce verified in handleSave() via check_admin_referer() before this runs.
        // Only key *presence* is read (values are never used) and every key is run
        // through sanitize_key() below, so the raw array itself needs no value sanitisation.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = isset($_POST['caps']) && is_array($_POST['caps']) ? wp_unslash($_POST['caps']) : [];

        $checked = [];
        foreach ((array) $raw as $slug => $capabilities) {
            if (! is_array($capabilities)) {
                continue;
            }

            foreach ($capabilities as $capability => $ignored) {
                $checked[sanitize_key((string) $slug)][sanitize_key((string) $capability)] = true;
            }
        }

        return $checked;
    }

    /**
     * @param array<string, array<string, bool>> $toggles
     * @return array<string, array<string, bool>>
     */
    private function stripProtected(array $toggles): array
    {
        foreach (array_keys($this->matrix->protectedPairs()) as $pair) {
            [$slug, $capability] = explode('|', $pair, 2);
            unset($toggles[$slug][$capability]);

            if (isset($toggles[$slug]) && $toggles[$slug] === []) {
                unset($toggles[$slug]);
            }
        }

        return $toggles;
    }

    private function currentNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
        if (isset($_GET['wlsb_notice']) && sanitize_key((string) wp_unslash($_GET['wlsb_notice'])) === 'saved') {
            return 'saved';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
        $error = isset($_GET['wlsb_error']) ? sanitize_key((string) wp_unslash($_GET['wlsb_error'])) : '';

        return in_array($error, ['escalation', 'lockout'], true) ? $error : '';
    }
}

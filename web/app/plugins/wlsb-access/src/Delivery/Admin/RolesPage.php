<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Admin;

use Wlsb\Access\Application\Access\CustomRoleManager;
use Wlsb\Access\Application\Access\InvalidRoleOperation;
use Wlsb\Access\Application\Reconciliation;
use Wlsb\Access\Domain\Capabilities\Cap;
use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Roles\RoleBlueprint;
use Wlsb\Access\Domain\Roles\RoleOverrideRepository;
use Wlsb\Access\Domain\Roles\RoleOverrides;

/**
 * Admin submenu: manage admin-created custom roles (create / rename / delete).
 *
 * Thin controller: all validation and override mutation lives in the unit-tested
 * CustomRoleManager. New roles are created with no capabilities (granted later
 * via the capability matrix), and only custom roles can be renamed or deleted —
 * so this screen can neither escalate privilege nor damage core/owned roles.
 */
final class RolesPage
{
    use AccessAdminSupport;

    public const SLUG = 'wlsb-access-roles';

    public const ACTION = 'wlsb_save_roles';

    public function __construct(
        private readonly RoleOverrideRepository $overrides,
        private readonly CustomRoleManager $manager,
        private readonly Reconciliation $reconciliation,
        private readonly EventLogger $log,
    ) {}

    public function registerSubmenu(): void
    {
        add_submenu_page(
            AccessMatrixPage::SLUG,
            __('Roles', 'wlsb-access'),
            __('Roles', 'wlsb-access'),
            Cap::MANAGE_ACCESS,
            self::SLUG,
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        $this->assertCanManage();

        $customRoles = array_values(array_filter(
            $this->overrides->load()->customRoles(),
            fn(RoleBlueprint $role): bool => ! $this->overrides->load()->isTombstoned($role->slug),
        ));

        $notice = $this->readNotice();
        $errorMessage = $this->readError();
        $formAction = admin_url('admin-post.php');

        require dirname(__DIR__, 3) . '/templates/admin/roles.php';
    }

    public function handleSave(): void
    {
        $this->assertCanManage();
        check_admin_referer(self::ACTION);

        $overrides = $this->overrides->load();
        $activeCustomSlugs = $this->activeCustomSlugs($overrides);
        $summary = ['created' => [], 'renamed' => [], 'deleted' => []];

        try {
            foreach ($this->submittedDeletes() as $slug) {
                if (in_array($slug, $activeCustomSlugs, true)) {
                    $overrides = $this->manager->delete($overrides, $slug);
                    $summary['deleted'][] = $slug;
                }
            }

            foreach ($this->submittedRenames() as $slug => $name) {
                if (in_array($slug, $activeCustomSlugs, true) && ! in_array($slug, $summary['deleted'], true)) {
                    $before = $overrides;
                    $overrides = $this->manager->rename($overrides, $slug, $name);
                    if ($before !== $overrides) {
                        $summary['renamed'][] = $slug;
                    }
                }
            }

            [$newSlug, $newName] = $this->submittedNewRole();
            if ($newSlug !== '' || $newName !== '') {
                $overrides = $this->manager->create($overrides, $newSlug, $newName, $this->existingRoleSlugs());
                $summary['created'][] = $newSlug;
            }
        } catch (InvalidRoleOperation $exception) {
            set_transient($this->errorKey(), $exception->getMessage(), 30);
            $this->redirectTo(self::SLUG, ['wlsb_error' => '1']);
        }

        $this->overrides->save($overrides);
        $this->reconciliation->run();

        $this->log->log(new Event(
            EventType::RoleUpdated,
            'role',
            actorId: get_current_user_id() ?: null,
            actorIp: $this->actorIp(),
            context: $summary,
        ));

        $this->redirectTo(self::SLUG, ['wlsb_notice' => 'saved']);
    }

    /**
     * @return list<string>
     */
    private function existingRoleSlugs(): array
    {
        return array_map('strval', array_keys(wp_roles()->roles));
    }

    /**
     * @return list<string> slugs of custom roles that are not tombstoned
     */
    private function activeCustomSlugs(RoleOverrides $overrides): array
    {
        $slugs = [];
        foreach ($overrides->customRoles() as $role) {
            if (! $overrides->isTombstoned($role->slug)) {
                $slugs[] = $role->slug;
            }
        }

        return $slugs;
    }

    /**
     * @return array{0: string, 1: string} sanitised [slug, name]
     */
    private function submittedNewRole(): array
    {
        // Nonce verified in handleSave() before this runs.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $slug = isset($_POST['new_role_slug']) ? sanitize_key((string) wp_unslash($_POST['new_role_slug'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $name = isset($_POST['new_role_name']) ? sanitize_text_field((string) wp_unslash($_POST['new_role_name'])) : '';

        return [$slug, $name];
    }

    /**
     * @return array<string, string> slug => new display name
     */
    private function submittedRenames(): array
    {
        // Keys and values are sanitised in the loop below; nonce verified in handleSave().
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = isset($_POST['role_name']) && is_array($_POST['role_name']) ? wp_unslash($_POST['role_name']) : [];

        $renames = [];
        foreach ((array) $raw as $slug => $name) {
            $renames[sanitize_key((string) $slug)] = sanitize_text_field((string) $name);
        }

        return $renames;
    }

    /**
     * @return list<string> sanitised slugs
     */
    private function submittedDeletes(): array
    {
        // Values are sanitised with sanitize_key below; nonce verified in handleSave().
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = isset($_POST['delete']) && is_array($_POST['delete']) ? wp_unslash($_POST['delete']) : [];

        return array_values(array_map(
            static fn($slug): string => sanitize_key((string) $slug),
            (array) $raw,
        ));
    }

    private function readNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        return isset($_GET['wlsb_notice']) && sanitize_key((string) wp_unslash($_GET['wlsb_notice'])) === 'saved'
            ? 'saved'
            : '';
    }

    private function readError(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        if (! isset($_GET['wlsb_error'])) {
            return '';
        }

        $message = get_transient($this->errorKey());
        delete_transient($this->errorKey());

        return is_string($message) ? $message : '';
    }

    private function errorKey(): string
    {
        return 'wlsb_roles_error_' . get_current_user_id();
    }
}

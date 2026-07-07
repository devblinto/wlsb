<?php

declare(strict_types=1);

namespace Wlsb\Access;

use Wlsb\Access\Application\Access\BreakGlass;
use Wlsb\Access\Application\Access\CustomRoleManager;
use Wlsb\Access\Application\Access\MatrixBuilder;
use Wlsb\Access\Application\Access\MatrixFormMapper;
use Wlsb\Access\Application\Lifecycle\UserLifecycleManager;
use Wlsb\Access\Application\Notifications\NotificationService;
use Wlsb\Access\Application\Reconciliation;
use Wlsb\Access\Application\Registration\EmailVerificationService;
use Wlsb\Access\Application\Registration\RegistrationService;
use Wlsb\Access\Application\Registration\RegistrationUrls;
use Wlsb\Access\Delivery\Admin\AccessMatrixPage;
use Wlsb\Access\Delivery\Admin\BreakGlassCapabilityFilter;
use Wlsb\Access\Delivery\Admin\RolesPage;
use Wlsb\Access\Delivery\Auth\AuthenticationGuard;
use Wlsb\Access\Delivery\Cli\ReconcileCommand;
use Wlsb\Access\Delivery\Frontend\RegistrationShortcodes;
use Wlsb\Access\Delivery\Privacy\PrivacyIntegration;
use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;
use Wlsb\Access\Domain\Catalog;
use Wlsb\Access\Domain\Clock\Clock;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Mail\Mailer;
use Wlsb\Access\Domain\Roles\Role;
use Wlsb\Access\Domain\Roles\RoleOverrideRepository;
use Wlsb\Access\Domain\Roles\RoleReconciler;
use Wlsb\Access\Domain\Roles\RoleRegistry;
use Wlsb\Access\Domain\Roles\RolesGateway;
use Wlsb\Access\Domain\Tokens\HmacTokenHasher;
use Wlsb\Access\Domain\Tokens\TokenGenerator;
use Wlsb\Access\Domain\Tokens\TokenHasher;
use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;
use Wlsb\Access\Infrastructure\Clock\SystemClock;
use Wlsb\Access\Infrastructure\Database\MigrationRunner;
use Wlsb\Access\Infrastructure\Database\Migrations\CreateEventLogTable;
use Wlsb\Access\Infrastructure\Database\OptionVersionStore;
use Wlsb\Access\Infrastructure\Database\VersionStore;
use Wlsb\Access\Infrastructure\Events\WpdbEventLogger;
use Wlsb\Access\Infrastructure\Mail\ResendMailer;
use Wlsb\Access\Infrastructure\Pages\PageProvisioner;
use Wlsb\Access\Infrastructure\Pages\WpRegistrationUrls;
use Wlsb\Access\Infrastructure\Roles\OptionRoleOverrideRepository;
use Wlsb\Access\Infrastructure\Roles\WpRolesGateway;
use Wlsb\Access\Infrastructure\Tokens\RandomTokenGenerator;
use Wlsb\Access\Infrastructure\Users\WpUserDirectory;
use Wlsb\Access\Infrastructure\Workflow\OptionWorkflowConfigStore;
use Wlsb\Access\Support\Container;

/**
 * Plugin bootstrap: builds the service container and wires WordPress lifecycle
 * hooks. Kept thin — every unit with real logic is unit-tested in isolation.
 *
 * Reconciliation (schema migrations + role/capability materialisation) is driven
 * from three places because the site runs with `DISALLOW_FILE_MODS`, so the
 * activation hook may never fire in production:
 *   - `activate()`               — normal local/dev activation,
 *   - the `plugins_loaded` guard — first prod boot and every version upgrade,
 *   - `wp wlsb reconcile`        — the deterministic Ansible deploy step.
 * All three call the same idempotent Reconciliation service.
 */
final class Plugin
{
    public const VERSION = '0.2.0';

    public const TEXT_DOMAIN = 'wlsb-access';

    private const VERSION_OPTION = 'wlsb_access_version';

    private const DB_VERSION_OPTION = 'wlsb_access_db_version';

    private const OVERRIDES_OPTION = 'wlsb_role_overrides';

    private const WORKFLOW_OPTION = 'wlsb_access_workflow_config';

    private const PAGES_READY_OPTION = 'wlsb_access_pages_ready';

    private const EVENT_LOG_TABLE = 'wlsb_event_log';

    private function __construct(
        private readonly string $file,
        private readonly Container $container,
    ) {}

    public static function register(string $file): self
    {
        $plugin = new self($file, self::buildContainer());

        register_activation_hook($file, [$plugin, 'activate']);
        register_deactivation_hook($file, [$plugin, 'deactivate']);
        add_action('plugins_loaded', [$plugin, 'boot']);
        add_action('init', [$plugin, 'loadTextDomain']);

        // Admin screens (resolved lazily on admin requests only).
        add_action('admin_menu', static function () use ($plugin): void {
            $plugin->container->get(AccessMatrixPage::class)->registerMenu();
            $plugin->container->get(RolesPage::class)->registerSubmenu();
        });
        add_action('admin_post_' . AccessMatrixPage::ACTION, static fn() => $plugin->container->get(AccessMatrixPage::class)->handleSave());
        add_action('admin_post_' . RolesPage::ACTION, static fn() => $plugin->container->get(RolesPage::class)->handleSave());

        // Break-glass recovery: runtime-only grant via user_has_cap.
        add_filter('user_has_cap', [$plugin->container->get(BreakGlassCapabilityFilter::class), 'filter'], 10, 4);

        // Front-end shortcodes + GDPR integration.
        add_action('init', static function () use ($plugin): void {
            $plugin->container->get(RegistrationShortcodes::class)->register();
            $plugin->container->get(PrivacyIntegration::class)->register();
        });

        // Block non-active accounts from authenticating (standard login + app passwords).
        add_filter('wp_authenticate_user', static fn($user, $password = '') => $plugin->container->get(AuthenticationGuard::class)->filter($user, (string) $password), 20, 2);
        add_filter('wp_authenticate_application_password', static fn($input, $user) => $plugin->container->get(AuthenticationGuard::class)->filterApplicationPassword($input, $user), 20, 2);

        // Provision the front-end pages on the first admin request after install/upgrade.
        // Deferred to admin_init because post creation is unsafe on plugins_loaded.
        add_action('admin_init', static function () use ($plugin): void {
            if (! get_option(self::PAGES_READY_OPTION)) {
                $plugin->container->get(PageProvisioner::class)->ensure();
                update_option(self::PAGES_READY_OPTION, '1');
            }
        });

        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command(
                'wlsb reconcile',
                new ReconcileCommand(static fn(): array => $plugin->reconcile()),
            );
        }

        return $plugin;
    }

    public function activate(): void
    {
        $this->reconcile();
        $this->container->get(PageProvisioner::class)->ensure();
        update_option(self::PAGES_READY_OPTION, '1');
        update_option(self::VERSION_OPTION, self::VERSION, true);
    }

    public function deactivate(): void
    {
        // Data (roles, overrides, event log) is always preserved on deactivation.
        // Nothing scheduled to tear down yet.
    }

    /**
     * Boot-time version guard: reconcile once per install/upgrade, then no-op
     * (a single autoloaded-option read) on every subsequent request.
     *
     * The reconcile itself is deferred to `init` (priority 20, after the text
     * domain loads at 10) because it resolves translated role/capability labels,
     * and calling translation functions before `init` is incorrect on WP 6.7+.
     */
    public function boot(): void
    {
        if (get_option(self::VERSION_OPTION) !== self::VERSION) {
            add_action('init', [$this, 'runUpgrade'], 20);
        }
    }

    public function runUpgrade(): void
    {
        $this->reconcile();
        // Re-provision pages on the next admin request (safe context).
        delete_option(self::PAGES_READY_OPTION);
        update_option(self::VERSION_OPTION, self::VERSION, true);
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename($this->file)) . '/languages',
        );
    }

    /**
     * Apply pending migrations and materialise roles/capabilities. Idempotent.
     *
     * @return array{migrations: list<int>, roles: \Wlsb\Access\Domain\Roles\ReconcileResult}
     */
    public function reconcile(): array
    {
        return $this->container->get(Reconciliation::class)->run();
    }

    public function container(): Container
    {
        return $this->container;
    }

    private static function buildContainer(): Container
    {
        $container = new Container();

        $container->singleton(
            VersionStore::class,
            static fn(): VersionStore => new OptionVersionStore(self::DB_VERSION_OPTION),
        );

        $container->singleton(Clock::class, static fn(): Clock => new SystemClock());

        $container->singleton(CapabilityRegistry::class, static function (): CapabilityRegistry {
            $registry = Catalog::capabilities();
            do_action('wlsb/capabilities/register', $registry);

            return $registry;
        });

        $container->singleton(RoleRegistry::class, static function (): RoleRegistry {
            $registry = Catalog::roles();
            do_action('wlsb/roles/register', $registry);

            return $registry;
        });

        $container->singleton(
            RoleOverrideRepository::class,
            static fn(): RoleOverrideRepository => new OptionRoleOverrideRepository(self::OVERRIDES_OPTION),
        );

        $container->singleton(RolesGateway::class, static fn(): RolesGateway => new WpRolesGateway());

        $container->singleton(
            RoleReconciler::class,
            static fn(Container $c): RoleReconciler => new RoleReconciler(
                $c->get(RoleRegistry::class),
                $c->get(CapabilityRegistry::class),
            ),
        );

        $container->singleton(
            EventLogger::class,
            static fn(Container $c): EventLogger => new WpdbEventLogger(
                $GLOBALS['wpdb'],
                $GLOBALS['wpdb']->prefix . self::EVENT_LOG_TABLE,
                $c->get(Clock::class),
            ),
        );

        $container->singleton(
            MigrationRunner::class,
            static fn(Container $c): MigrationRunner => new MigrationRunner(
                $c->get(VersionStore::class),
                [new CreateEventLogTable($GLOBALS['wpdb'])],
            ),
        );

        $container->bind(
            Reconciliation::class,
            static fn(Container $c): Reconciliation => new Reconciliation(
                $c->get(MigrationRunner::class),
                $c->get(RoleReconciler::class),
                $c->get(RoleOverrideRepository::class),
                $c->get(RolesGateway::class),
                $c->get(EventLogger::class),
            ),
        );

        $container->singleton(MatrixBuilder::class, static fn(Container $c): MatrixBuilder => new MatrixBuilder($c->get(RoleReconciler::class)));
        $container->singleton(MatrixFormMapper::class, static fn(): MatrixFormMapper => new MatrixFormMapper());
        $container->singleton(CustomRoleManager::class, static fn(): CustomRoleManager => new CustomRoleManager());

        $container->singleton(BreakGlass::class, static fn(): BreakGlass => new BreakGlass(
            defined('WLSB_ACCESS_BYPASS') ? (string) WLSB_ACCESS_BYPASS : null,
        ));
        $container->singleton(
            BreakGlassCapabilityFilter::class,
            static fn(Container $c): BreakGlassCapabilityFilter => new BreakGlassCapabilityFilter($c->get(BreakGlass::class)),
        );

        $container->singleton(
            AccessMatrixPage::class,
            static fn(Container $c): AccessMatrixPage => new AccessMatrixPage(
                $c->get(CapabilityRegistry::class),
                $c->get(MatrixBuilder::class),
                $c->get(RoleOverrideRepository::class),
                $c->get(MatrixFormMapper::class),
                $c->get(Reconciliation::class),
                $c->get(EventLogger::class),
            ),
        );

        $container->singleton(
            RolesPage::class,
            static fn(Container $c): RolesPage => new RolesPage(
                $c->get(RoleOverrideRepository::class),
                $c->get(CustomRoleManager::class),
                $c->get(Reconciliation::class),
                $c->get(EventLogger::class),
            ),
        );

        // --- Phase 2: registration, verification, notifications -------------

        $container->singleton(UserDirectory::class, static fn(): UserDirectory => new WpUserDirectory());
        $container->singleton(PageProvisioner::class, static fn(): PageProvisioner => new PageProvisioner());
        $container->singleton(RegistrationUrls::class, static fn(Container $c): RegistrationUrls => new WpRegistrationUrls($c->get(PageProvisioner::class)));
        $container->singleton(TokenGenerator::class, static fn(): TokenGenerator => new RandomTokenGenerator());
        $container->singleton(TokenHasher::class, static fn(): TokenHasher => new HmacTokenHasher((string) wp_salt('auth')));
        $container->singleton(WorkflowConfigStore::class, static fn(): WorkflowConfigStore => new OptionWorkflowConfigStore(self::WORKFLOW_OPTION));

        $container->singleton(Mailer::class, static function (): Mailer {
            $apiKey = defined('RESEND_API_KEY') && RESEND_API_KEY !== '' ? (string) RESEND_API_KEY : null;
            $fromEmail = defined('WLSB_MAIL_FROM') && WLSB_MAIL_FROM !== ''
                ? (string) WLSB_MAIL_FROM
                : 'no-reply@' . ((string) (wp_parse_url(home_url(), PHP_URL_HOST) ?: 'example.com'));
            $fromName = defined('WLSB_MAIL_FROM_NAME') && WLSB_MAIL_FROM_NAME !== ''
                ? (string) WLSB_MAIL_FROM_NAME
                : (string) get_bloginfo('name');

            return new ResendMailer($apiKey, $fromEmail, $fromName);
        });

        $container->singleton(NotificationService::class, static fn(Container $c): NotificationService => new NotificationService(
            $c->get(Mailer::class),
            $c->get(EventLogger::class),
            (string) get_bloginfo('name'),
        ));

        $container->singleton(UserLifecycleManager::class, static fn(Container $c): UserLifecycleManager => new UserLifecycleManager(
            $c->get(UserDirectory::class),
            Role::PENDING,
            $c->get(EventLogger::class),
        ));

        $container->singleton(EmailVerificationService::class, static fn(Container $c): EmailVerificationService => new EmailVerificationService(
            $c->get(UserDirectory::class),
            $c->get(UserLifecycleManager::class),
            $c->get(WorkflowConfigStore::class),
            $c->get(TokenGenerator::class),
            $c->get(TokenHasher::class),
            $c->get(NotificationService::class),
            $c->get(RegistrationUrls::class),
            $c->get(Clock::class),
            $c->get(EventLogger::class),
        ));

        $container->singleton(RegistrationService::class, static fn(Container $c): RegistrationService => new RegistrationService(
            $c->get(UserDirectory::class),
            $c->get(WorkflowConfigStore::class),
            $c->get(EmailVerificationService::class),
            $c->get(EventLogger::class),
            Role::PENDING,
        ));

        $container->singleton(RegistrationShortcodes::class, static fn(Container $c): RegistrationShortcodes => new RegistrationShortcodes(
            $c->get(RegistrationService::class),
            $c->get(EmailVerificationService::class),
            $c->get(WorkflowConfigStore::class),
            $c->get(UserDirectory::class),
            $c->get(PageProvisioner::class),
        ));

        $container->singleton(AuthenticationGuard::class, static fn(Container $c): AuthenticationGuard => new AuthenticationGuard($c->get(UserLifecycleManager::class)));
        $container->singleton(PrivacyIntegration::class, static fn(Container $c): PrivacyIntegration => new PrivacyIntegration($c->get(UserDirectory::class)));

        return $container;
    }
}

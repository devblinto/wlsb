<?php

declare(strict_types=1);

namespace Wlsb\Access;

use Wlsb\Access\Application\Access\BreakGlass;
use Wlsb\Access\Application\Access\MatrixBuilder;
use Wlsb\Access\Application\Access\MatrixFormMapper;
use Wlsb\Access\Application\Reconciliation;
use Wlsb\Access\Delivery\Admin\AccessMatrixPage;
use Wlsb\Access\Delivery\Admin\BreakGlassCapabilityFilter;
use Wlsb\Access\Delivery\Cli\ReconcileCommand;
use Wlsb\Access\Domain\Capabilities\CapabilityRegistry;
use Wlsb\Access\Domain\Catalog;
use Wlsb\Access\Domain\Clock\Clock;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Roles\RoleOverrideRepository;
use Wlsb\Access\Domain\Roles\RoleReconciler;
use Wlsb\Access\Domain\Roles\RoleRegistry;
use Wlsb\Access\Domain\Roles\RolesGateway;
use Wlsb\Access\Infrastructure\Clock\SystemClock;
use Wlsb\Access\Infrastructure\Database\MigrationRunner;
use Wlsb\Access\Infrastructure\Database\Migrations\CreateEventLogTable;
use Wlsb\Access\Infrastructure\Database\OptionVersionStore;
use Wlsb\Access\Infrastructure\Database\VersionStore;
use Wlsb\Access\Infrastructure\Events\WpdbEventLogger;
use Wlsb\Access\Infrastructure\Roles\OptionRoleOverrideRepository;
use Wlsb\Access\Infrastructure\Roles\WpRolesGateway;
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
    public const VERSION = '0.1.0';

    public const TEXT_DOMAIN = 'wlsb-access';

    private const VERSION_OPTION = 'wlsb_access_version';

    private const DB_VERSION_OPTION = 'wlsb_access_db_version';

    private const OVERRIDES_OPTION = 'wlsb_role_overrides';

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

        // Admin capability-matrix screen (resolved lazily on admin requests only).
        add_action('admin_menu', static fn() => $plugin->container->get(AccessMatrixPage::class)->registerMenu());
        add_action('admin_post_' . AccessMatrixPage::ACTION, static fn() => $plugin->container->get(AccessMatrixPage::class)->handleSave());

        // Break-glass recovery: runtime-only grant via user_has_cap.
        add_filter('user_has_cap', [$plugin->container->get(BreakGlassCapabilityFilter::class), 'filter'], 10, 4);

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
     */
    public function boot(): void
    {
        if (get_option(self::VERSION_OPTION) !== self::VERSION) {
            $this->reconcile();
            update_option(self::VERSION_OPTION, self::VERSION, true);
        }
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

        return $container;
    }
}

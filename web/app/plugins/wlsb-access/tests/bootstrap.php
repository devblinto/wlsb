<?php

/**
 * Test bootstrap for the wlsb-access plugin.
 *
 * The plugin ships a self-contained PSR-4 autoloader (no runtime Composer
 * dependency). We register it here so unit tests can resolve `Wlsb\Access\*`
 * classes. The guard keeps the harness runnable even before the autoloader
 * class file exists (e.g. during its own TDD bootstrap).
 */

declare(strict_types=1);

// Benign WordPress function/constant stubs (i18n passthroughs, option/hook
// recorders) so any unit can call the small WP surface it depends on.
require_once __DIR__ . '/wp-stubs.php';

$autoloaderFile = dirname(__DIR__) . '/src/Support/Autoloader.php';

if (is_file($autoloaderFile)) {
    require_once $autoloaderFile;

    (new \Wlsb\Access\Support\Autoloader(
        dirname(__DIR__) . '/src',
        'Wlsb\\Access\\',
    ))->register();

    // Reusable test-support classes (fakes, in-memory doubles).
    (new \Wlsb\Access\Support\Autoloader(
        __DIR__,
        'Wlsb\\Access\\Tests\\',
    ))->register();
}

<?php

declare(strict_types=1);

/**
 * Architecture guards — cheap invariants that protect the security posture as
 * the plugin grows.
 */

/**
 * @return list<string>
 */
function pluginSourceFiles(): array
{
    $dir = new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src');
    $files = [];
    foreach (new RecursiveIteratorIterator($dir) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test('no source file exposes a __return_true REST/permission callback', function (): void {
    foreach (pluginSourceFiles() as $file) {
        expect(str_contains((string) file_get_contents($file), '__return_true'))
            ->toBeFalse("{$file} uses __return_true — REST/permission callbacks must be real checks.");
    }
});

test('the plugin registers no unguarded REST routes', function (): void {
    // The plugin enforces REST access via rest_pre_dispatch, not register_rest_route.
    foreach (pluginSourceFiles() as $file) {
        expect(str_contains((string) file_get_contents($file), 'register_rest_route'))
            ->toBeFalse("{$file} registers a REST route — add an explicit permission_callback and update this guard.");
    }
});

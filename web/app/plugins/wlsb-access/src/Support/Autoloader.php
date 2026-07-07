<?php

declare(strict_types=1);

namespace Wlsb\Access\Support;

/**
 * Minimal, self-contained PSR-4 autoloader.
 *
 * The plugin has no runtime Composer dependency, so it registers this loader
 * from its bootstrap to resolve `Wlsb\Access\*` classes. Keeping it tiny and
 * dependency-free means the plugin loads correctly whether or not the project
 * autoloader has been dumped with the plugin's classes.
 */
final class Autoloader
{
    private readonly string $baseDir;

    private readonly string $prefix;

    public function __construct(string $baseDir, string $prefix)
    {
        $this->baseDir = rtrim($baseDir, '/\\');
        $this->prefix = ltrim($prefix, '\\');
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'load']);
    }

    /**
     * Resolve a fully-qualified class name to its PSR-4 file path, or null when
     * the class does not belong to this loader's namespace prefix.
     */
    public function resolve(string $class): ?string
    {
        $class = ltrim($class, '\\');

        if (! str_starts_with($class, $this->prefix)) {
            return null;
        }

        $relative = substr($class, strlen($this->prefix));

        return $this->baseDir . '/' . str_replace('\\', '/', $relative) . '.php';
    }

    /**
     * Autoload callback: include the class file when it belongs to this loader
     * and exists on disk.
     */
    public function load(string $class): bool
    {
        $file = $this->resolve($class);

        if ($file === null || ! is_file($file)) {
            return false;
        }

        require $file;

        return true;
    }
}

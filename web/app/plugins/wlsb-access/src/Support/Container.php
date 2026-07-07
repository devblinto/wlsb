<?php

declare(strict_types=1);

namespace Wlsb\Access\Support;

use Closure;

/**
 * Tiny dependency-injection container.
 *
 * Intentionally minimal: bind factories (fresh each resolve) or singletons
 * (resolved once, then cached). Factories receive the container so they can
 * resolve their own dependencies. This keeps the plugin's wiring explicit and
 * testable without pulling in a framework-scale container.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $factories = [];

    /** @var array<string, bool> */
    private array $shared = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (! isset($this->factories[$id])) {
            // $id is an internal binding key from our own code (a class name),
            // never user input, and this developer-facing exception is not rendered
            // as HTML output — so no escaping is warranted here.
            throw NotFoundException::forId($id); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if (($this->shared[$id] ?? false) && array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $resolved = ($this->factories[$id])($this);

        if ($this->shared[$id] ?? false) {
            $this->instances[$id] = $resolved;
        }

        return $resolved;
    }
}

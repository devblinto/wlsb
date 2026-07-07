<?php

/**
 * Minimal WordPress function stubs for unit-testing WP-boundary wiring without
 * a full WordPress bootstrap. Each stub is guarded so it is only defined once
 * and never collides with a real WordPress environment. Hook registrations and
 * options are recorded in globals the tests can inspect.
 *
 * This is test-support scaffolding, not part of the shipped plugin runtime.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$GLOBALS['wlsb_test_hooks'] ??= [
    'actions' => [],
    'filters' => [],
    'activation' => [],
    'deactivation' => [],
];
$GLOBALS['wlsb_test_options'] ??= [];

if (! function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $args = 1): bool
    {
        $GLOBALS['wlsb_test_hooks']['actions'][] = $hook;

        return true;
    }
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $args = 1): bool
    {
        $GLOBALS['wlsb_test_hooks']['filters'][] = $hook;

        return true;
    }
}

if (! function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, $callback): void
    {
        $GLOBALS['wlsb_test_hooks']['activation'][] = $file;
    }
}

if (! function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, $callback): void
    {
        $GLOBALS['wlsb_test_hooks']['deactivation'][] = $file;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, $default = false)
    {
        return $GLOBALS['wlsb_test_options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, $value, $autoload = null): bool
    {
        $GLOBALS['wlsb_test_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (! function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain(string $domain, $deprecated = false, $path = false): bool
    {
        return true;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('_x')) {
    function _x(string $text, string $context, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, ...$args): void {}
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
}

if (! class_exists('WP_CLI')) {
    /**
     * Minimal WP-CLI stub recording emitted messages and registered commands.
     */
    class WP_CLI
    {
        /** @var list<array{0:string,1:string}> */
        public static array $messages = [];

        /** @var array<string, mixed> */
        public static array $commands = [];

        public static function reset(): void
        {
            self::$messages = [];
            self::$commands = [];
        }

        public static function success(string $message): void
        {
            self::$messages[] = ['success', $message];
        }

        public static function log(string $message): void
        {
            self::$messages[] = ['log', $message];
        }

        public static function error(string $message): void
        {
            self::$messages[] = ['error', $message];
        }

        public static function add_command(string $name, $callable, array $args = []): void
        {
            self::$commands[$name] = $callable;
        }
    }
}

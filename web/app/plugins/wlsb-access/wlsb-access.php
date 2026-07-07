<?php

/**
 * Plugin Name:       WLSB Access
 * Plugin URI:        https://github.com/devblinto/wlsb
 * Description:       Users, roles, permissions, and approval-workflow management — admin-defined roles and granular capabilities, role-selectable registration behind email verification and a configurable approval step, and capability-driven access control.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.3
 * Author:            Right Left Agency
 * Author URI:        https://rightleftagency.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wlsb-access
 * Domain Path:       /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

require_once __DIR__ . '/src/Support/Autoloader.php';

(new \Wlsb\Access\Support\Autoloader(__DIR__ . '/src', 'Wlsb\\Access\\'))->register();

\Wlsb\Access\Plugin::register(__FILE__);

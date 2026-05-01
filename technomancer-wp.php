<?php
/**
 * Plugin Name: Technomancer WP
 * Plugin URI: https://github.com/sinappsus-agency/technomancer-wp
 * Description: Multi-flow WordPress and WooCommerce automation plugin for n8n, Notifuse, and ERPNext.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: SINAPPSUS
 * License: GPL-2.0-or-later
 * Text Domain: technomancer-wp
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('TECHNOMANCER_WP_VERSION', '0.1.0');
define('TECHNOMANCER_WP_FILE', __FILE__);
define('TECHNOMANCER_WP_PATH', plugin_dir_path(__FILE__));
define('TECHNOMANCER_WP_URL', plugin_dir_url(__FILE__));

require_once TECHNOMANCER_WP_PATH . 'src/Core/Autoloader.php';

\TechnomancerWp\Connector\Core\Autoloader::register();

register_activation_hook(__FILE__, static function (): void {
    \TechnomancerWp\Connector\Core\Installer::activate();
});

add_action('plugins_loaded', static function (): void {
    \TechnomancerWp\Connector\Core\Updater::boot();
    \TechnomancerWp\Connector\Core\Plugin::boot();
});

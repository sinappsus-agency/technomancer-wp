<?php
/**
 * Plugin Name: SINAPPSUS n8n Connector
 * Plugin URI: https://sinappsus.com/
 * Description: Multi-flow WordPress and WooCommerce automation plugin for n8n, Notifuse, and ERPNext.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: SINAPPSUS
 * License: GPL-2.0-or-later
 * Text Domain: sinappsus-n8n-connector
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SINAPPSUS_N8N_CONNECTOR_VERSION', '0.1.0');
define('SINAPPSUS_N8N_CONNECTOR_FILE', __FILE__);
define('SINAPPSUS_N8N_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('SINAPPSUS_N8N_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once SINAPPSUS_N8N_CONNECTOR_PATH . 'src/Core/Autoloader.php';

\Sinappsus\N8nConnector\Core\Autoloader::register();

register_activation_hook(__FILE__, static function (): void {
    \Sinappsus\N8nConnector\Core\Installer::activate();
});

add_action('plugins_loaded', static function (): void {
    \Sinappsus\N8nConnector\Core\Plugin::boot();
});
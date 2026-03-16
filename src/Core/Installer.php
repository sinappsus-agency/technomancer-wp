<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Core;

final class Installer
{
    public static function activate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();
        $flowsTable = $wpdb->prefix . 'snc_flows';
        $logsTable = $wpdb->prefix . 'snc_logs';

        $flowsSql = "CREATE TABLE {$flowsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            trigger_key VARCHAR(190) NOT NULL,
            webhook_url TEXT NOT NULL,
            secret_key VARCHAR(190) DEFAULT '',
            payload_mode VARCHAR(20) NOT NULL DEFAULT 'standard',
            filters LONGTEXT NULL,
            settings LONGTEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY trigger_key (trigger_key),
            KEY is_enabled (is_enabled)
        ) {$charsetCollate};";

        $logsSql = "CREATE TABLE {$logsTable} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            flow_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            event_key VARCHAR(190) NOT NULL,
            entity_type VARCHAR(50) NOT NULL DEFAULT '',
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL,
            message TEXT NULL,
            payload LONGTEXT NULL,
            response_code INT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY flow_id (flow_id),
            KEY event_key (event_key),
            KEY entity_lookup (entity_type, entity_id)
        ) {$charsetCollate};";

        dbDelta($flowsSql);
        dbDelta($logsSql);

        add_option('snc_settings', [
            'api_token' => wp_generate_password(40, false, false),
            'signing_secret' => wp_generate_password(64, true, true),
            'trusted_origins' => [],
            'notifuse' => [
                'base_url' => '',
                'api_key' => '',
                'default_list_id' => '',
                'signup_on_registration' => 0,
                'signup_on_checkout' => 0,
                'allow_unsubscribe' => 1,
            ],
            'erpnext' => [
                'host_url' => '',
                'api_key' => '',
                'api_secret' => '',
                'company' => '',
                'warehouse' => '',
                'item_group' => '',
                'price_list' => '',
                'customer_group' => 'Commercial',
                'territory' => 'All Territories',
                'sync_customers' => 0,
                'sync_orders' => 0,
                'sync_products' => 0,
                'sync_stock' => 0,
                'stock_source' => 'erpnext',
                'sync_interval' => 'hourly',
                'customer_mapping' => [],
                'product_mapping' => [],
            ],
        ]);
    }
}
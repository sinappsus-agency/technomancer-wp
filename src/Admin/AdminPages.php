<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Admin;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Events\EventRegistry;
use Sinappsus\N8nConnector\Flows\FlowRepository;
use Sinappsus\N8nConnector\Flows\Logger;
use Sinappsus\N8nConnector\Integrations\Erpnext\Client as ErpnextClient;
use Sinappsus\N8nConnector\Integrations\Notifuse\Client as NotifuseClient;

final class AdminPages
{
    private FlowRepository $flows;

    private Logger $logger;

    private NotifuseClient $notifuseClient;

    private ErpnextClient $erpnextClient;

    public function __construct(FlowRepository $flows, Logger $logger, NotifuseClient $notifuseClient, ErpnextClient $erpnextClient)
    {
        $this->flows = $flows;
        $this->logger = $logger;
        $this->notifuseClient = $notifuseClient;
        $this->erpnextClient = $erpnextClient;
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'SINAPPSUS n8n Connector',
            'SINAPPSUS n8n',
            'manage_options',
            'snc-overview',
            [$this, 'renderOverview'],
            'dashicons-randomize'
        );

        add_submenu_page('snc-overview', 'Overview', 'Overview', 'manage_options', 'snc-overview', [$this, 'renderOverview']);
        add_submenu_page('snc-overview', 'Flows', 'Flows', 'manage_options', 'snc-flows', [$this, 'renderFlows']);
        add_submenu_page('snc-overview', 'Event Catalog', 'Event Catalog', 'manage_options', 'snc-events', [$this, 'renderEvents']);
        add_submenu_page('snc-overview', 'API Access', 'API Access', 'manage_options', 'snc-api', [$this, 'renderApiAccess']);
        add_submenu_page('snc-overview', 'Notifuse', 'Notifuse', 'manage_options', 'snc-notifuse', [$this, 'renderNotifuse']);
        add_submenu_page('snc-overview', 'ERPNext', 'ERPNext', 'manage_options', 'snc-erpnext', [$this, 'renderErpnext']);
        add_submenu_page('snc-overview', 'Logs', 'Logs', 'manage_options', 'snc-logs', [$this, 'renderLogs']);
        add_submenu_page('snc-overview', 'Tools', 'Tools', 'manage_options', 'snc-tools', [$this, 'renderTools']);
    }

    public function registerSettings(): void
    {
        register_setting('snc_settings_group', 'snc_settings', [$this, 'sanitizeSettings']);
    }

    public function sanitizeSettings(array $settings): array
    {
        $trustedOrigins = isset($settings['trusted_origins']) ? explode(PHP_EOL, (string) $settings['trusted_origins']) : [];
        $trustedOrigins = array_values(array_filter(array_map('trim', $trustedOrigins)));
        $customerMapping = $this->sanitizeMappingSetting($settings['erpnext']['customer_mapping'] ?? []);
        $productMapping = $this->sanitizeMappingSetting($settings['erpnext']['product_mapping'] ?? []);

        return [
            'api_token' => sanitize_text_field((string) ($settings['api_token'] ?? '')),
            'signing_secret' => sanitize_text_field((string) ($settings['signing_secret'] ?? '')),
            'trusted_origins' => $trustedOrigins,
            'notifuse' => [
                'base_url' => esc_url_raw((string) ($settings['notifuse']['base_url'] ?? '')),
                'api_key' => sanitize_text_field((string) ($settings['notifuse']['api_key'] ?? '')),
                'default_list_id' => sanitize_text_field((string) ($settings['notifuse']['default_list_id'] ?? '')),
                'signup_on_registration' => empty($settings['notifuse']['signup_on_registration']) ? 0 : 1,
                'signup_on_checkout' => empty($settings['notifuse']['signup_on_checkout']) ? 0 : 1,
                'allow_unsubscribe' => empty($settings['notifuse']['allow_unsubscribe']) ? 0 : 1,
            ],
            'erpnext' => [
                'host_url' => esc_url_raw((string) ($settings['erpnext']['host_url'] ?? '')),
                'api_key' => sanitize_text_field((string) ($settings['erpnext']['api_key'] ?? '')),
                'api_secret' => sanitize_text_field((string) ($settings['erpnext']['api_secret'] ?? '')),
                'company' => sanitize_text_field((string) ($settings['erpnext']['company'] ?? '')),
                'warehouse' => sanitize_text_field((string) ($settings['erpnext']['warehouse'] ?? '')),
                'item_group' => sanitize_text_field((string) ($settings['erpnext']['item_group'] ?? '')),
                'price_list' => sanitize_text_field((string) ($settings['erpnext']['price_list'] ?? '')),
                'customer_group' => sanitize_text_field((string) ($settings['erpnext']['customer_group'] ?? 'Commercial')),
                'territory' => sanitize_text_field((string) ($settings['erpnext']['territory'] ?? 'All Territories')),
                'sync_customers' => empty($settings['erpnext']['sync_customers']) ? 0 : 1,
                'sync_orders' => empty($settings['erpnext']['sync_orders']) ? 0 : 1,
                'sync_products' => empty($settings['erpnext']['sync_products']) ? 0 : 1,
                'sync_stock' => empty($settings['erpnext']['sync_stock']) ? 0 : 1,
                'stock_source' => sanitize_text_field((string) ($settings['erpnext']['stock_source'] ?? 'erpnext')),
                'sync_interval' => sanitize_text_field((string) ($settings['erpnext']['sync_interval'] ?? 'hourly')),
                'customer_mapping' => $customerMapping,
                'product_mapping' => $productMapping,
            ],
        ];
    }

    private function sanitizeMappingSetting($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $sanitized = [];
        foreach ($value as $sourceField => $targetField) {
            $source = sanitize_key((string) $sourceField);
            $target = sanitize_text_field((string) $targetField);

            if ($source === '' || $target === '') {
                continue;
            }

            $sanitized[$source] = $target;
        }

        return $sanitized;
    }

    private function erpCustomerSourceFields(): array
    {
        return [
            'erp_customer_name' => 'ERP Customer Name',
            'billing_first_name' => 'Billing First Name',
            'billing_last_name' => 'Billing Last Name',
            'billing_email' => 'Billing Email',
            'billing_phone' => 'Billing Phone',
            'erp_customer_group' => 'ERP Customer Group',
            'erp_territory' => 'ERP Territory',
            'wp_user_id' => 'WordPress User ID',
        ];
    }

    private function erpProductSourceFields(): array
    {
        return [
            'erp_item_code' => 'ERP Item Code',
            'name' => 'Product Name',
            'sku' => 'SKU',
            'description' => 'Description',
            'regular_price' => 'Regular Price',
            'stock_quantity' => 'Stock Quantity',
            'erp_item_group' => 'ERP Item Group',
            'erp_warehouse' => 'ERP Warehouse',
            'wp_product_id' => 'WordPress Product ID',
        ];
    }

    private function renderMappingTable(string $prefix, array $sourceFields, array $savedMapping, string $description): void
    {
        ?>
        <table class="widefat striped" style="max-width:900px;">
            <thead>
                <tr>
                    <th>WordPress Source</th>
                    <th>ERPNext Target Field</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sourceFields as $sourceKey => $sourceLabel) : ?>
                    <tr>
                        <td><?php echo esc_html($sourceLabel); ?></td>
                        <td>
                            <input class="regular-text" name="snc_settings[erpnext][<?php echo esc_attr($prefix); ?>][<?php echo esc_attr($sourceKey); ?>]" value="<?php echo esc_attr((string) ($savedMapping[$sourceKey] ?? '')); ?>" placeholder="ERPNext field name" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php echo esc_html($description); ?></p>
        <?php
    }

    public function saveFlow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_save_flow');

        $id = $this->flows->save([
            'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
            'name' => $_POST['name'] ?? '',
            'trigger_key' => $_POST['trigger_key'] ?? '',
            'webhook_url' => $_POST['webhook_url'] ?? '',
            'secret_key' => $_POST['secret_key'] ?? '',
            'payload_mode' => $_POST['payload_mode'] ?? 'standard',
            'is_enabled' => isset($_POST['is_enabled']) ? 1 : 0,
        ]);

        wp_safe_redirect(add_query_arg(['page' => 'snc-flows', 'flow_saved' => $id], admin_url('admin.php')));
        exit;
    }

    public function deleteFlow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_delete_flow');

        $flowId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($flowId > 0) {
            $this->flows->delete($flowId);
        }

        wp_safe_redirect(add_query_arg(['page' => 'snc-flows'], admin_url('admin.php')));
        exit;
    }

    public function renderOverview(): void
    {
        $flows = $this->flows->all();
        $logs = $this->logger->recent(10);
        $logStats = $this->logger->stats();
        $settings = Settings::all();
        ?>
        <div class="wrap">
            <h1>SINAPPSUS n8n Connector</h1>
            <p>Multi-flow WordPress automation for n8n with plugin-local integrations for Notifuse and ERPNext.</p>
            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;max-width:1100px;">
                <?php $this->card('Active Flows', (string) count(array_filter($flows, static fn(array $flow): bool => ! empty($flow['is_enabled'])))); ?>
                <?php $this->card('Recent Deliveries', (string) count($logs)); ?>
                <?php $this->card('WooCommerce', class_exists('WooCommerce') ? 'Active' : 'Inactive'); ?>
            </div>
            <h2 style="margin-top:32px;">Log Status</h2>
            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;max-width:1100px;">
                <?php $this->card('Total Logs', (string) ($logStats['total'] ?? 0)); ?>
                <?php $this->card('Sent', (string) (($logStats['by_status']['sent'] ?? 0) + ($logStats['by_status']['integration_sent'] ?? 0))); ?>
                <?php $this->card('Failed', (string) (($logStats['by_status']['failed'] ?? 0) + ($logStats['by_status']['integration_failed'] ?? 0) + ($logStats['by_status']['security_failed'] ?? 0))); ?>
                <?php $this->card('Dead Letter', (string) ($logStats['by_status']['dead_letter'] ?? 0)); ?>
            </div>
            <h2 style="margin-top:32px;">API Access</h2>
            <p><strong>Token:</strong> <?php echo esc_html((string) ($settings['api_token'] ?? '')); ?></p>
            <p><strong>Trusted Origins:</strong> <?php echo esc_html(implode(', ', $settings['trusted_origins'] ?? [])); ?></p>
        </div>
        <?php
    }

    public function renderFlows(): void
    {
        $flows = $this->flows->all();
        $editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editingFlow = $editingId > 0 ? $this->flows->find($editingId) : null;
        $definitions = EventRegistry::definitions();
        ?>
        <div class="wrap">
            <h1>Flows</h1>
            <div style="display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:24px;align-items:start;">
                <div>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Trigger</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flows as $flow) : ?>
                                <tr>
                                    <td><?php echo esc_html((string) $flow['name']); ?></td>
                                    <td><?php echo esc_html((string) $flow['trigger_key']); ?></td>
                                    <td><?php echo ! empty($flow['is_enabled']) ? 'Enabled' : 'Disabled'; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(add_query_arg(['page' => 'snc-flows', 'edit' => $flow['id']], admin_url('admin.php'))); ?>">Edit</a>
                                        |
                                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'snc_delete_flow', 'id' => $flow['id']], admin_url('admin-post.php')), 'snc_delete_flow')); ?>">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($flows)) : ?>
                                <tr><td colspan="4">No flows yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2><?php echo $editingFlow ? 'Edit Flow' : 'New Flow'; ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="snc_save_flow" />
                        <input type="hidden" name="id" value="<?php echo esc_attr((string) ($editingFlow['id'] ?? 0)); ?>" />
                        <?php wp_nonce_field('snc_save_flow'); ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="snc-name">Name</label></th>
                                <td><input id="snc-name" class="regular-text" name="name" value="<?php echo esc_attr((string) ($editingFlow['name'] ?? '')); ?>" required /></td>
                            </tr>
                            <tr>
                                <th><label for="snc-trigger-key">Trigger</label></th>
                                <td>
                                    <select id="snc-trigger-key" class="regular-text" name="trigger_key" required>
                                        <option value="">Select event</option>
                                        <?php foreach ($definitions as $eventKey => $definition) : ?>
                                            <option value="<?php echo esc_attr($eventKey); ?>" <?php selected((string) ($editingFlow['trigger_key'] ?? ''), $eventKey); ?>>
                                                <?php echo esc_html($definition['group'] . ' - ' . $definition['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="snc-webhook-url">Webhook URL</label></th>
                                <td><input id="snc-webhook-url" class="regular-text" name="webhook_url" type="url" value="<?php echo esc_attr((string) ($editingFlow['webhook_url'] ?? '')); ?>" required /></td>
                            </tr>
                            <tr>
                                <th><label for="snc-secret-key">Signing Secret</label></th>
                                <td><input id="snc-secret-key" class="regular-text" name="secret_key" value="<?php echo esc_attr((string) ($editingFlow['secret_key'] ?? '')); ?>" /></td>
                            </tr>
                            <tr>
                                <th><label for="snc-payload-mode">Payload Mode</label></th>
                                <td>
                                    <select id="snc-payload-mode" name="payload_mode">
                                        <option value="minimal" <?php selected((string) ($editingFlow['payload_mode'] ?? 'standard'), 'minimal'); ?>>Minimal</option>
                                        <option value="standard" <?php selected((string) ($editingFlow['payload_mode'] ?? 'standard'), 'standard'); ?>>Standard</option>
                                        <option value="full" <?php selected((string) ($editingFlow['payload_mode'] ?? 'standard'), 'full'); ?>>Full</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Enabled</th>
                                <td><label><input type="checkbox" name="is_enabled" value="1" <?php checked(! empty($editingFlow['is_enabled']), true); ?> /> Enable this flow</label></td>
                            </tr>
                        </table>
                        <?php submit_button($editingFlow ? 'Update Flow' : 'Create Flow'); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function renderEvents(): void
    {
        $definitions = EventRegistry::definitions();
        ?>
        <div class="wrap">
            <h1>Event Catalog</h1>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Group</th>
                        <th>Hook</th>
                        <th>Entity</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($definitions as $eventKey => $definition) : ?>
                        <tr>
                            <td><?php echo esc_html($eventKey); ?></td>
                            <td><?php echo esc_html($definition['group']); ?></td>
                            <td><?php echo esc_html($definition['hook']); ?></td>
                            <td><?php echo esc_html($definition['entity_type']); ?></td>
                            <td><?php echo esc_html($definition['payload_notes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderApiAccess(): void
    {
        $settings = Settings::all();
        ?>
        <div class="wrap">
            <h1>API Access</h1>
            <form method="post" action="options.php">
                <?php settings_fields('snc_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="snc-api-token">API Token</label></th>
                        <td><input id="snc-api-token" class="regular-text" name="snc_settings[api_token]" value="<?php echo esc_attr((string) ($settings['api_token'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-signing-secret">Signing Secret</label></th>
                        <td>
                            <input id="snc-signing-secret" class="regular-text code" name="snc_settings[signing_secret]" value="<?php echo esc_attr((string) ($settings['signing_secret'] ?? '')); ?>" />
                            <p class="description">Used for HMAC request signing from n8n into WordPress.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-trusted-origins">Trusted Origins</label></th>
                        <td>
                            <textarea id="snc-trusted-origins" class="large-text code" rows="6" name="snc_settings[trusted_origins]"><?php echo esc_textarea(implode(PHP_EOL, $settings['trusted_origins'] ?? [])); ?></textarea>
                            <p class="description">One origin per line. Requests still require a valid bearer token and signature.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save API Settings'); ?>
            </form>
        </div>
        <?php
    }

    public function renderNotifuse(): void
    {
        $settings = Settings::all();
        $notifuse = $settings['notifuse'] ?? [];
        $lists = $this->notifuseClient->getLists();
        ?>
        <div class="wrap">
            <h1>Notifuse</h1>
            <?php if (isset($_GET['notifuse_test'])) : ?>
                <div class="notice <?php echo $_GET['notifuse_test'] === 'success' ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html((string) ($_GET['message'] ?? '')); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('snc_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="snc-notifuse-base-url">Base URL</label></th>
                        <td><input id="snc-notifuse-base-url" class="regular-text" name="snc_settings[notifuse][base_url]" value="<?php echo esc_attr((string) ($notifuse['base_url'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-api-key">API Key</label></th>
                        <td><input id="snc-notifuse-api-key" class="regular-text" name="snc_settings[notifuse][api_key]" value="<?php echo esc_attr((string) ($notifuse['api_key'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-list-id">Default List ID</label></th>
                        <td><input id="snc-notifuse-list-id" class="regular-text" name="snc_settings[notifuse][default_list_id]" value="<?php echo esc_attr((string) ($notifuse['default_list_id'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th>Signup Sources</th>
                        <td>
                            <label><input type="checkbox" name="snc_settings[notifuse][signup_on_registration]" value="1" <?php checked(! empty($notifuse['signup_on_registration']), true); ?> /> Subscribe on user registration</label><br />
                            <label><input type="checkbox" name="snc_settings[notifuse][signup_on_checkout]" value="1" <?php checked(! empty($notifuse['signup_on_checkout']), true); ?> /> Subscribe on WooCommerce checkout</label>
                            <br /><label><input type="checkbox" name="snc_settings[notifuse][allow_unsubscribe]" value="1" <?php checked(! empty($notifuse['allow_unsubscribe']), true); ?> /> Allow unsubscribe forms and widget</label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Notifuse Settings'); ?>
            </form>
            <h2>Available Lists</h2>
            <table class="widefat striped" style="max-width:720px;">
                <thead><tr><th>List ID</th><th>Name</th></tr></thead>
                <tbody>
                <?php foreach ($lists as $list) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($list['id'] ?? $list['uuid'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($list['name'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lists)) : ?>
                    <tr><td colspan="2">No lists loaded yet. Save credentials and test the connection first.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="snc_test_notifuse" />
                <?php wp_nonce_field('snc_test_notifuse'); ?>
                <?php submit_button('Test Notifuse Connection', 'secondary'); ?>
            </form>
            <p>Elementor widgets available when Elementor is active: subscribe and unsubscribe. Shortcodes: <code>[snc_notifuse_subscribe]</code> and <code>[snc_notifuse_unsubscribe]</code>.</p>
        </div>
        <?php
    }

    public function renderErpnext(): void
    {
        $settings = Settings::all();
        $erpnext = $settings['erpnext'] ?? [];
        ?>
        <div class="wrap">
            <h1>ERPNext</h1>
            <?php if (isset($_GET['erpnext_test'])) : ?>
                <div class="notice <?php echo $_GET['erpnext_test'] === 'success' ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html((string) ($_GET['message'] ?? '')); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['erp_contract_test'])) : ?>
                <div class="notice <?php echo $_GET['erp_contract_test'] === 'success' ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html((string) ($_GET['message'] ?? '')); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['erp_action'])) : ?>
                <div class="notice <?php echo $_GET['erp_action'] === 'success' ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html((string) ($_GET['message'] ?? '')); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('snc_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="snc-erp-host-url">Host URL</label></th>
                        <td><input id="snc-erp-host-url" class="regular-text" name="snc_settings[erpnext][host_url]" value="<?php echo esc_attr((string) ($erpnext['host_url'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-api-key">API Key</label></th>
                        <td><input id="snc-erp-api-key" class="regular-text" name="snc_settings[erpnext][api_key]" value="<?php echo esc_attr((string) ($erpnext['api_key'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-api-secret">API Secret</label></th>
                        <td><input id="snc-erp-api-secret" class="regular-text" name="snc_settings[erpnext][api_secret]" value="<?php echo esc_attr((string) ($erpnext['api_secret'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-company">Company</label></th>
                        <td><input id="snc-erp-company" class="regular-text" name="snc_settings[erpnext][company]" value="<?php echo esc_attr((string) ($erpnext['company'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-warehouse">Warehouse</label></th>
                        <td><input id="snc-erp-warehouse" class="regular-text" name="snc_settings[erpnext][warehouse]" value="<?php echo esc_attr((string) ($erpnext['warehouse'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-item-group">Item Group</label></th>
                        <td><input id="snc-erp-item-group" class="regular-text" name="snc_settings[erpnext][item_group]" value="<?php echo esc_attr((string) ($erpnext['item_group'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-price-list">Price List</label></th>
                        <td><input id="snc-erp-price-list" class="regular-text" name="snc_settings[erpnext][price_list]" value="<?php echo esc_attr((string) ($erpnext['price_list'] ?? '')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-customer-group">Customer Group</label></th>
                        <td><input id="snc-erp-customer-group" class="regular-text" name="snc_settings[erpnext][customer_group]" value="<?php echo esc_attr((string) ($erpnext['customer_group'] ?? 'Commercial')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-territory">Territory</label></th>
                        <td><input id="snc-erp-territory" class="regular-text" name="snc_settings[erpnext][territory]" value="<?php echo esc_attr((string) ($erpnext['territory'] ?? 'All Territories')); ?>" /></td>
                    </tr>
                    <tr>
                        <th>Sync Toggles</th>
                        <td>
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_customers]" value="1" <?php checked(! empty($erpnext['sync_customers']), true); ?> /> Sync customers</label><br />
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_orders]" value="1" <?php checked(! empty($erpnext['sync_orders']), true); ?> /> Sync orders</label><br />
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_products]" value="1" <?php checked(! empty($erpnext['sync_products']), true); ?> /> Sync products/catalog</label><br />
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_stock]" value="1" <?php checked(! empty($erpnext['sync_stock']), true); ?> /> Sync stock</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-stock-source">Stock Source of Truth</label></th>
                        <td>
                            <select id="snc-erp-stock-source" name="snc_settings[erpnext][stock_source]">
                                <option value="erpnext" <?php selected((string) ($erpnext['stock_source'] ?? 'erpnext'), 'erpnext'); ?>>ERPNext</option>
                                <option value="woocommerce" <?php selected((string) ($erpnext['stock_source'] ?? 'erpnext'), 'woocommerce'); ?>>WooCommerce</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-sync-interval">Sync Interval</label></th>
                        <td>
                            <select id="snc-erp-sync-interval" name="snc_settings[erpnext][sync_interval]">
                                <option value="snc_every_fifteen_minutes" <?php selected((string) ($erpnext['sync_interval'] ?? 'hourly'), 'snc_every_fifteen_minutes'); ?>>Every 15 minutes</option>
                                <option value="hourly" <?php selected((string) ($erpnext['sync_interval'] ?? 'hourly'), 'hourly'); ?>>Hourly</option>
                                <option value="twicedaily" <?php selected((string) ($erpnext['sync_interval'] ?? 'hourly'), 'twicedaily'); ?>>Twice daily</option>
                                <option value="daily" <?php selected((string) ($erpnext['sync_interval'] ?? 'hourly'), 'daily'); ?>>Daily</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <h2>Customer Field Mapping</h2>
                <?php $this->renderMappingTable('customer_mapping', $this->erpCustomerSourceFields(), is_array($erpnext['customer_mapping'] ?? null) ? $erpnext['customer_mapping'] : [], 'Map WordPress or WooCommerce customer fields into ERPNext customer fields.'); ?>
                <h2>Product Field Mapping</h2>
                <?php $this->renderMappingTable('product_mapping', $this->erpProductSourceFields(), is_array($erpnext['product_mapping'] ?? null) ? $erpnext['product_mapping'] : [], 'Map WooCommerce product fields into ERPNext item fields.'); ?>
                <?php submit_button('Save ERPNext Settings'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="snc_test_erpnext" />
                <?php wp_nonce_field('snc_test_erpnext'); ?>
                <?php submit_button('Test ERPNext Connection', 'secondary'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="snc_test_erp_contracts" />
                <?php wp_nonce_field('snc_test_erp_contracts'); ?>
                <?php submit_button('Run ERP Contract Diagnostics', 'secondary'); ?>
            </form>
            <h2>Catalog and Stock Tools</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
                <input type="hidden" name="action" value="snc_erp_import_products" />
                <?php wp_nonce_field('snc_erp_import_products'); ?>
                <label for="snc-erp-import-limit">Import latest items from ERPNext</label>
                <input id="snc-erp-import-limit" type="number" name="limit" min="1" max="100" value="20" />
                <?php submit_button('Import Products from ERPNext', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
                <input type="hidden" name="action" value="snc_erp_export_product" />
                <?php wp_nonce_field('snc_erp_export_product'); ?>
                <label for="snc-erp-export-product-id">WooCommerce product ID</label>
                <input id="snc-erp-export-product-id" type="number" name="product_id" min="1" />
                <?php submit_button('Export Product to ERPNext', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="snc_erp_verify_stock" />
                <?php wp_nonce_field('snc_erp_verify_stock'); ?>
                <label for="snc-erp-stock-product-id">WooCommerce product ID</label>
                <input id="snc-erp-stock-product-id" type="number" name="product_id" min="1" />
                <?php submit_button('Verify Stock Against ERPNext', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:16px;">
                <input type="hidden" name="action" value="snc_erp_run_product_sync" />
                <?php wp_nonce_field('snc_erp_run_product_sync'); ?>
                <?php submit_button('Run Product Sync Now', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
                <input type="hidden" name="action" value="snc_erp_run_stock_sync" />
                <?php wp_nonce_field('snc_erp_run_stock_sync'); ?>
                <?php submit_button('Run Stock Sync Now', 'secondary', 'submit', false); ?>
            </form>
            <p>ERP profile fields are injected into registration and user profile screens for customer metadata capture.</p>
        </div>
        <?php
    }

    public function renderLogs(): void
    {
        $filters = [
            'status' => isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : '',
            'event_key' => isset($_GET['event_key']) ? sanitize_text_field((string) $_GET['event_key']) : '',
            'search' => isset($_GET['search']) ? sanitize_text_field((string) $_GET['search']) : '',
            'limit' => 100,
        ];
        $logs = $this->logger->query($filters);
        $stats = $this->logger->stats();
        ?>
        <div class="wrap">
            <h1>Logs</h1>
            <p>Clean internal log storage for deliveries, API calls, integrations, retries, and errors.</p>
            <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px;max-width:1200px;margin-bottom:24px;">
                <?php $this->card('Total', (string) ($stats['total'] ?? 0)); ?>
                <?php $this->card('Sent', (string) (($stats['by_status']['sent'] ?? 0) + ($stats['by_status']['integration_sent'] ?? 0))); ?>
                <?php $this->card('Failed', (string) (($stats['by_status']['failed'] ?? 0) + ($stats['by_status']['integration_failed'] ?? 0))); ?>
                <?php $this->card('Retries', (string) ($stats['by_status']['retry_scheduled'] ?? 0)); ?>
                <?php $this->card('Dead Letter', (string) ($stats['by_status']['dead_letter'] ?? 0)); ?>
            </div>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:16px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
                <input type="hidden" name="page" value="snc-logs" />
                <p>
                    <label for="snc-log-status">Status</label><br />
                    <input id="snc-log-status" name="status" value="<?php echo esc_attr($filters['status']); ?>" />
                </p>
                <p>
                    <label for="snc-log-event">Event Key</label><br />
                    <input id="snc-log-event" name="event_key" value="<?php echo esc_attr($filters['event_key']); ?>" />
                </p>
                <p>
                    <label for="snc-log-search">Search</label><br />
                    <input id="snc-log-search" name="search" value="<?php echo esc_attr($filters['search']); ?>" />
                </p>
                <?php submit_button('Filter Logs', 'secondary', 'submit', false); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
                <input type="hidden" name="action" value="snc_clear_logs" />
                <?php wp_nonce_field('snc_clear_logs'); ?>
                <?php submit_button('Clear Logs', 'delete', 'submit', false); ?>
            </form>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>Status</th>
                        <th>Entity</th>
                        <th>Flow</th>
                        <th>Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $log['created_at']); ?></td>
                            <td><?php echo esc_html((string) $log['event_key']); ?></td>
                            <td><?php echo esc_html((string) $log['status']); ?></td>
                            <td><?php echo esc_html((string) $log['entity_type'] . ':' . (string) $log['entity_id']); ?></td>
                            <td><?php echo esc_html((string) $log['flow_id']); ?></td>
                            <td><?php echo esc_html(is_array($log['message_json']) ? wp_json_encode($log['message_json']) : (string) ($log['message'] ?? '')); ?></td>
                            <td>
                                <?php if ((int) $log['flow_id'] > 0 && ! empty($log['payload'])) : ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'snc_replay_log', 'id' => $log['id']], admin_url('admin-post.php')), 'snc_replay_log')); ?>">Replay</a>
                                <?php else : ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)) : ?>
                        <tr><td colspan="7">No log entries yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderTools(): void
    {
        $flows = $this->flows->all();
        ?>
        <div class="wrap">
            <h1>Tools</h1>
            <p>Use the REST endpoints to test payloads:</p>
            <ul>
                <li><code>/wp-json/sinappsus-n8n/v1/health</code></li>
                <li><code>/wp-json/sinappsus-n8n/v1/events</code></li>
                <li><code>/wp-json/sinappsus-n8n/v1/flows</code></li>
            </ul>
            <h2>Manual Test Send</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="snc_send_test_flow" />
                <?php wp_nonce_field('snc_send_test_flow'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="snc-test-flow">Flow</label></th>
                        <td>
                            <select id="snc-test-flow" name="flow_id" required>
                                <option value="">Select flow</option>
                                <?php foreach ($flows as $flow) : ?>
                                    <option value="<?php echo esc_attr((string) $flow['id']); ?>"><?php echo esc_html((string) $flow['name'] . ' [' . (string) $flow['trigger_key'] . ']'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Send Test Payload', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function testNotifuse(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_test_notifuse');

        $result = $this->notifuseClient->testConnection();
        wp_safe_redirect(add_query_arg([
            'page' => 'snc-notifuse',
            'notifuse_test' => $result['success'] ? 'success' : 'error',
            'message' => rawurlencode($result['message']),
        ], admin_url('admin.php')));
        exit;
    }

    public function testErpnext(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_test_erpnext');

        $result = $this->erpnextClient->testConnection();
        wp_safe_redirect(add_query_arg([
            'page' => 'snc-erpnext',
            'erpnext_test' => $result['success'] ? 'success' : 'error',
            'message' => rawurlencode($result['message']),
        ], admin_url('admin.php')));
        exit;
    }

    public function testErpContracts(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_test_erp_contracts');

        $result = $this->erpnextClient->contractDiagnostics();
        wp_safe_redirect(add_query_arg([
            'page' => 'snc-erpnext',
            'erp_contract_test' => $result['success'] ? 'success' : 'error',
            'message' => rawurlencode($result['message']),
        ], admin_url('admin.php')));
        exit;
    }

    public function replayLog(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_replay_log');

        $logId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $entry = $this->logger->find($logId);

        if (is_array($entry) && ! empty($entry['payload']) && (int) $entry['flow_id'] > 0) {
            $payload = json_decode((string) $entry['payload'], true);
            if (is_array($payload)) {
                do_action('sinappsus_n8n_process_delivery', (int) $entry['flow_id'], $payload);
            }
        }

        wp_safe_redirect(add_query_arg(['page' => 'snc-logs'], admin_url('admin.php')));
        exit;
    }

    public function sendTestFlow(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_send_test_flow');

        $flowId = isset($_POST['flow_id']) ? (int) $_POST['flow_id'] : 0;
        $flow = $this->flows->find($flowId);

        if ($flow) {
            $payload = [
                'event_id' => wp_generate_uuid4(),
                'event_name' => 'sinappsus.manual.test',
                'source' => 'wordpress',
                'timestamp' => gmdate('c'),
                'site' => [
                    'name' => get_bloginfo('name'),
                    'url' => home_url('/'),
                ],
                'entity' => [
                    'type' => 'test',
                    'id' => 0,
                    'snapshot' => ['message' => 'Manual test payload'],
                ],
                'changes' => [],
                'delivery' => [
                    'attempt' => 1,
                    'max_attempts' => 1,
                    'queued_at' => gmdate('c'),
                ],
            ];

            do_action('sinappsus_n8n_process_delivery', $flowId, $payload);
        }

        wp_safe_redirect(add_query_arg(['page' => 'snc-tools'], admin_url('admin.php')));
        exit;
    }

    public function clearLogs(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('snc_clear_logs');
        $this->logger->clear();
        wp_safe_redirect(add_query_arg(['page' => 'snc-logs'], admin_url('admin.php')));
        exit;
    }

    private function card(string $title, string $value): void
    {
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;padding:16px;">
            <p style="margin:0 0 8px;font-size:12px;text-transform:uppercase;color:#50575e;"><?php echo esc_html($title); ?></p>
            <p style="margin:0;font-size:28px;font-weight:600;"><?php echo esc_html($value); ?></p>
        </div>
        <?php
    }
}
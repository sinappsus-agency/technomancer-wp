<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Admin;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Events\EventRegistry;
use Sinappsus\N8nConnector\Events\SamplePayloadFactory;
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
        $notifusePublicLists = isset($settings['notifuse']['public_form_list_ids']) && is_array($settings['notifuse']['public_form_list_ids'])
            ? array_values(array_filter(array_map('sanitize_text_field', $settings['notifuse']['public_form_list_ids'])))
            : [];

        return [
            'api_token' => sanitize_text_field((string) ($settings['api_token'] ?? '')),
            'signing_secret' => sanitize_text_field((string) ($settings['signing_secret'] ?? '')),
            'trusted_origins' => $trustedOrigins,
            'notifuse' => [
                'base_url' => esc_url_raw((string) ($settings['notifuse']['base_url'] ?? '')),
                'api_key' => sanitize_text_field((string) ($settings['notifuse']['api_key'] ?? '')),
                'workspace_id' => strtolower(sanitize_text_field((string) ($settings['notifuse']['workspace_id'] ?? ''))),
                'default_list_id' => sanitize_text_field((string) ($settings['notifuse']['default_list_id'] ?? '')),
                'public_form_list_ids' => $notifusePublicLists,
                'signup_on_registration' => empty($settings['notifuse']['signup_on_registration']) ? 0 : 1,
                'signup_on_checkout' => empty($settings['notifuse']['signup_on_checkout']) ? 0 : 1,
                'allow_unsubscribe' => empty($settings['notifuse']['allow_unsubscribe']) ? 0 : 1,
                'require_consent' => empty($settings['notifuse']['require_consent']) ? 0 : 1,
                'consent_label' => sanitize_text_field((string) ($settings['notifuse']['consent_label'] ?? 'I agree to receive updates by email.')),
                'enable_custom_events' => empty($settings['notifuse']['enable_custom_events']) ? 0 : 1,
                'enable_transactional_emails' => empty($settings['notifuse']['enable_transactional_emails']) ? 0 : 1,
                'welcome_template_id' => sanitize_text_field((string) ($settings['notifuse']['welcome_template_id'] ?? '')),
                'order_confirmation_template_id' => sanitize_text_field((string) ($settings['notifuse']['order_confirmation_template_id'] ?? '')),
                'order_paid_template_id' => sanitize_text_field((string) ($settings['notifuse']['order_paid_template_id'] ?? '')),
                'refund_template_id' => sanitize_text_field((string) ($settings['notifuse']['refund_template_id'] ?? '')),
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
            'user_login' => 'WordPress Username',
            'display_name' => 'WordPress Display Name',
            'user_email' => 'WordPress User Email',
            'billing_first_name' => 'Billing First Name',
            'billing_last_name' => 'Billing Last Name',
            'billing_full_name' => 'Billing Full Name',
            'billing_email' => 'Billing Email',
            'billing_phone' => 'Billing Phone',
            'billing_company' => 'Billing Company',
            'billing_address_1' => 'Billing Address Line 1',
            'billing_address_2' => 'Billing Address Line 2',
            'billing_city' => 'Billing City',
            'billing_state' => 'Billing State',
            'billing_postcode' => 'Billing Postcode',
            'billing_country' => 'Billing Country',
            'shipping_first_name' => 'Shipping First Name',
            'shipping_last_name' => 'Shipping Last Name',
            'shipping_full_name' => 'Shipping Full Name',
            'shipping_company' => 'Shipping Company',
            'shipping_address_1' => 'Shipping Address Line 1',
            'shipping_address_2' => 'Shipping Address Line 2',
            'shipping_city' => 'Shipping City',
            'shipping_state' => 'Shipping State',
            'shipping_postcode' => 'Shipping Postcode',
            'shipping_country' => 'Shipping Country',
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
            'slug' => 'Slug',
            'description' => 'Description',
            'short_description' => 'Short Description',
            'price' => 'Current Price',
            'regular_price' => 'Regular Price',
            'sale_price' => 'Sale Price',
            'stock_quantity' => 'Stock Quantity',
            'stock_status' => 'Stock Status',
            'manage_stock' => 'Manage Stock',
            'catalog_visibility' => 'Catalog Visibility',
            'status' => 'Product Status',
            'weight' => 'Weight',
            'length' => 'Length',
            'width' => 'Width',
            'height' => 'Height',
            'image_id' => 'Featured Image ID',
            'image_url' => 'Featured Image URL',
            'gallery_image_ids' => 'Gallery Image IDs',
            'gallery_image_urls' => 'Gallery Image URLs',
            'category_ids' => 'Category IDs',
            'category_names' => 'Category Names',
            'tag_ids' => 'Tag IDs',
            'tag_names' => 'Tag Names',
            'permalink' => 'Product URL',
            'erp_item_group' => 'ERP Item Group',
            'erp_warehouse' => 'ERP Warehouse',
            'wp_product_id' => 'WordPress Product ID',
        ];
    }

    private function prefixedDestinationOptions(array $options, string $prefix, string $labelPrefix): array
    {
        $prefixed = [];
        foreach ($options as $fieldName => $label) {
            $prefixed[$prefix . '.' . $fieldName] = $labelPrefix . ': ' . $this->humanizeDestinationLabel($fieldName, (string) $label);
        }

        return $prefixed;
    }

    private function humanizeDestinationLabel(string $fieldName, string $label): string
    {
        $overrides = [
            'email_id' => 'Email (' . $fieldName . ')',
            'mobile_no' => 'Mobile (' . $fieldName . ')',
            'phone' => 'Phone (' . $fieldName . ')',
            'image' => 'Product Image (' . $fieldName . ' · Attach Image)',
            'address_line1' => 'Address Line 1 (' . $fieldName . ')',
            'address_line2' => 'Address Line 2 (' . $fieldName . ')',
        ];

        if (isset($overrides[$fieldName])) {
            return $overrides[$fieldName];
        }

        return $label;
    }

    private function erpCustomerDestinationOptions(): array
    {
        $customerOptions = $this->prefixedDestinationOptions($this->erpnextClient->getDocTypeFieldOptions('Customer'), 'customer', 'Customer');
        $contactOptions = $this->prefixedDestinationOptions($this->erpnextClient->getDocTypeFieldOptions('Contact'), 'contact', 'Contact');
        $addressOptions = $this->prefixedDestinationOptions($this->erpnextClient->getDocTypeFieldOptions('Address'), 'address', 'Address');

        $syntheticOptions = [
            'contact.email_id' => 'Contact: Primary Email (email_id)',
            'contact.phone' => 'Contact: Primary Phone (phone)',
            'contact.mobile_no' => 'Contact: Primary Mobile (mobile_no)',
        ];

        return $customerOptions + $contactOptions + $syntheticOptions + $addressOptions;
    }

    private function erpProductDestinationOptions(): array
    {
        return $this->prefixedDestinationOptions($this->erpnextClient->getDocTypeFieldOptions('Item'), 'item', 'Item');
    }

    private function renderMappingTable(string $prefix, array $sourceFields, array $savedMapping, string $description, array $destinationOptions = []): void
    {
        ?>
        <table class="widefat striped" style="max-width:900px;">
            <thead>
                <tr>
                    <th>WordPress Or WooCommerce Source Field</th>
                    <th>ERPNext Destination Field Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sourceFields as $sourceKey => $sourceLabel) : ?>
                    <tr>
                        <td><?php echo esc_html($sourceLabel); ?></td>
                        <td>
                            <?php $currentValue = (string) ($savedMapping[$sourceKey] ?? ''); ?>
                            <?php if (! empty($destinationOptions)) : ?>
                                <select class="regular-text" name="snc_settings[erpnext][<?php echo esc_attr($prefix); ?>][<?php echo esc_attr($sourceKey); ?>]">
                                    <option value="">Do not map</option>
                                    <?php if ($currentValue !== '' && ! isset($destinationOptions[$currentValue])) : ?>
                                        <option value="<?php echo esc_attr($currentValue); ?>" selected="selected"><?php echo esc_html('Current saved field (' . $currentValue . ')'); ?></option>
                                    <?php endif; ?>
                                    <?php foreach ($destinationOptions as $optionValue => $optionLabel) : ?>
                                        <option value="<?php echo esc_attr($optionValue); ?>" <?php selected($currentValue, (string) $optionValue); ?>><?php echo esc_html((string) $optionLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input class="regular-text" name="snc_settings[erpnext][<?php echo esc_attr($prefix); ?>][<?php echo esc_attr($sourceKey); ?>]" value="<?php echo esc_attr($currentValue); ?>" placeholder="ERPNext field name" />
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description"><?php echo esc_html($description); ?> Leave the ERPNext field empty if you do not want that source value copied over.<?php echo empty($destinationOptions) ? ' Save valid ERPNext credentials to load destination fields automatically.' : ''; ?></p>
        <?php
    }

    private function renderErpSelectOrInput(string $id, string $name, string $value, array $options, string $description): void
    {
        if (! empty($options)) {
            ?>
            <select id="<?php echo esc_attr($id); ?>" class="regular-text" name="<?php echo esc_attr($name); ?>">
                <option value="">Select from ERPNext</option>
                <?php if ($value !== '' && ! isset($options[$value])) : ?>
                    <option value="<?php echo esc_attr($value); ?>" selected="selected"><?php echo esc_html('Current saved value (' . $value . ')'); ?></option>
                <?php endif; ?>
                <?php foreach ($options as $optionValue => $optionLabel) : ?>
                    <option value="<?php echo esc_attr((string) $optionValue); ?>" <?php selected($value, (string) $optionValue); ?>><?php echo esc_html((string) $optionLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php echo esc_html($description); ?> These choices are loaded live from ERPNext.</p>
            <?php

            return;
        }

        ?>
        <input id="<?php echo esc_attr($id); ?>" class="regular-text" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
        <p class="description"><?php echo esc_html($description); ?> Save valid ERPNext credentials and reload this page to get a dropdown instead of a free-text field.</p>
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
            'settings' => [
                'max_attempts' => max(1, min(10, (int) ($_POST['max_attempts'] ?? 3))),
                'preview_entity_id' => max(0, (int) ($_POST['preview_entity_id'] ?? 0)),
            ],
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
            <p>Build WordPress and WooCommerce automations that send structured events to n8n, optionally sync data with ERPNext, and optionally push marketing and transactional activity into Notifuse.</p>
            <?php $this->renderHelpBox('How This Plugin Is Organized', [
                'Flows: each flow connects one WordPress or WooCommerce event to one n8n webhook.',
                'Event Catalog: browse available trigger names before building flows.',
                'API Access: credentials that allow approved n8n workflows to call back into WordPress.',
                'Notifuse and ERPNext: optional integrations. Configure them only if you plan to use them.',
                'Logs and Tools: inspect deliveries, replay failures, and send test payloads.',
            ]); ?>
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
            <p class="description">If you are setting this up for the first time, start with Event Catalog, then create a flow, then use Tools to send a test payload.</p>
        </div>
        <?php
    }

    public function renderFlows(): void
    {
        $flows = $this->flows->all();
        $editingId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $editingFlow = $editingId > 0 ? $this->flows->find($editingId) : null;
        $definitions = EventRegistry::definitions();
        $previewFactory = new SamplePayloadFactory();
        $previewEventKey = (string) ($editingFlow['trigger_key'] ?? '');
        $previewEntityId = (int) ($editingFlow['settings']['preview_entity_id'] ?? 0);
        $previewPayload = $previewEventKey !== '' ? $previewFactory->build($previewEventKey, $previewEntityId) : null;
        ?>
        <div class="wrap">
            <h1>Flows</h1>
            <?php $this->renderHelpBox('What A Flow Means Here', [
                'A flow is one outbound route from WordPress into one n8n webhook.',
                'Trigger chooses when the flow fires.',
                'Webhook URL is the exact n8n webhook that receives the payload.',
                'Payload mode controls how much context is sent.',
                'Preview Entity ID is optional and is only used to build better previews and test sends.',
            ]); ?>
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
                                <td>
                                    <input id="snc-name" class="regular-text" name="name" value="<?php echo esc_attr((string) ($editingFlow['name'] ?? '')); ?>" required />
                                    <p class="description">Internal label for your team, for example: Customer welcome webhook or ERP order handoff.</p>
                                </td>
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
                                    <p class="description">Choose the WordPress or WooCommerce event that should send data to n8n.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="snc-webhook-url">Webhook URL</label></th>
                                <td>
                                    <input id="snc-webhook-url" class="regular-text" name="webhook_url" type="url" value="<?php echo esc_attr((string) ($editingFlow['webhook_url'] ?? '')); ?>" required />
                                    <p class="description">Paste the exact n8n webhook endpoint that should receive this event.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="snc-secret-key">Signing Secret</label></th>
                                <td>
                                    <input id="snc-secret-key" class="regular-text" name="secret_key" value="<?php echo esc_attr((string) ($editingFlow['secret_key'] ?? '')); ?>" />
                                    <p class="description">Optional shared secret used to sign outbound payloads so n8n can verify they came from WordPress.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="snc-payload-mode">Payload Mode</label></th>
                                <td>
                                    <select id="snc-payload-mode" name="payload_mode">
                                        <option value="minimal" <?php selected((string) ($editingFlow['payload_mode'] ?? 'standard'), 'minimal'); ?>>Minimal</option>
                                        <option value="standard" <?php selected((string) ($editingFlow['payload_mode'] ?? 'standard'), 'standard'); ?>>Standard</option>
                                        <option value="full" <?php selected((string) ($editingFlow['payload_mode'] ?? 'standard'), 'full'); ?>>Full</option>
                                    </select>
                                    <p class="description">Minimal sends only identity and changes. Standard sends the normal event snapshot. Full adds extra WordPress and flow context.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="snc-max-attempts">Max Attempts</label></th>
                                <td>
                                    <input id="snc-max-attempts" class="small-text" name="max_attempts" type="number" min="1" max="10" value="<?php echo esc_attr((string) ($editingFlow['settings']['max_attempts'] ?? 3)); ?>" />
                                    <p class="description">How many times WordPress should retry delivery before marking the payload as dead letter.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="snc-preview-entity-id">Preview Entity ID</label></th>
                                <td>
                                    <input id="snc-preview-entity-id" class="small-text" name="preview_entity_id" type="number" min="0" value="<?php echo esc_attr((string) ($editingFlow['settings']['preview_entity_id'] ?? 0)); ?>" />
                                    <p class="description">Optional existing post, product, order, comment, or user ID. Leave blank if you only want a generic sample payload.</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Enabled</th>
                                <td>
                                    <label><input type="checkbox" name="is_enabled" value="1" <?php checked(! empty($editingFlow['is_enabled']), true); ?> /> Enable this flow</label>
                                    <p class="description">Disabled flows stay saved but do not send any payloads.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button($editingFlow ? 'Update Flow' : 'Create Flow'); ?>
                    </form>
                    <h2>Payload Preview</h2>
                    <p class="description">This is the approximate JSON structure n8n will receive for the current flow configuration. Save the flow first if you want the preview to follow a specific trigger.</p>
                    <textarea readonly class="large-text code" rows="18"><?php echo esc_textarea($previewPayload === null ? 'Save a flow with a trigger to generate a sample payload preview.' : (string) wp_json_encode($previewPayload, JSON_PRETTY_PRINT)); ?></textarea>
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
            <?php $this->renderHelpBox('How To Use This Screen', [
                'Event is the trigger key you will select inside a flow.',
                'Hook is the native WordPress or WooCommerce hook behind that event.',
                'Entity shows the object type that the payload is centered on.',
                'Notes explain what kind of snapshot or change metadata is included.',
            ]); ?>
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
        $apiBase = untrailingslashit(rest_url('sinappsus-n8n/v1'));
        $token = (string) ($settings['api_token'] ?? '');
        $secret = (string) ($settings['signing_secret'] ?? '');
        $trustedOrigins = isset($settings['trusted_origins']) && is_array($settings['trusted_origins']) ? $settings['trusted_origins'] : [];
        $maskedToken = $token !== '' ? $token : 'YOUR_API_TOKEN';
        $maskedSecret = $secret !== '' ? $secret : 'YOUR_SIGNING_SECRET';
        $originExample = ! empty($trustedOrigins) ? (string) $trustedOrigins[0] : 'https://your-n8n-domain.example';
        ?>
        <div class="wrap">
            <h1>API Access</h1>
            <?php $this->renderHelpBox('What These Credentials Are For', [
                'These values are used when n8n needs to call back into WordPress through this plugin API.',
                'API Token is the bearer token n8n sends with requests.',
                'Signing Secret is used for HMAC verification on inbound requests.',
                'Trusted Origins is a second safety layer and should contain the domains your n8n instance actually uses.',
            ]); ?>
            <?php $this->renderHelpBox('Start Here For n8n', [
                'Base callback URL: ' . $apiBase,
                'Use the HTTP Request node in n8n for all callback calls into WordPress.',
                'Every callback request should send Authorization: Bearer ' . $maskedToken,
                'If a signing secret is set, calculate X-SINAPPSUS-Signature as HMAC-SHA256 of the raw request body using that secret.',
                empty($trustedOrigins) ? 'Trusted Origins is currently optional because none are configured.' : 'If your n8n instance sends an Origin header, it must match one of the trusted origins listed below.',
            ]); ?>
            <form method="post" action="options.php">
                <?php settings_fields('snc_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="snc-api-token">API Token</label></th>
                        <td>
                            <input id="snc-api-token" class="regular-text" name="snc_settings[api_token]" value="<?php echo esc_attr((string) ($settings['api_token'] ?? '')); ?>" />
                            <p class="description">Paste this into n8n as the bearer token for plugin API calls.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-signing-secret">Signing Secret</label></th>
                        <td>
                            <input id="snc-signing-secret" class="regular-text code" name="snc_settings[signing_secret]" value="<?php echo esc_attr((string) ($settings['signing_secret'] ?? '')); ?>" />
                            <p class="description">Use the same secret in n8n when sending signed requests back into WordPress.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-trusted-origins">Trusted Origins</label></th>
                        <td>
                            <textarea id="snc-trusted-origins" class="large-text code" rows="6" name="snc_settings[trusted_origins]"><?php echo esc_textarea(implode(PHP_EOL, $settings['trusted_origins'] ?? [])); ?></textarea>
                            <p class="description">One origin per line, for example https://automation.example.com. This does not replace the token or signature checks.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save API Settings'); ?>
            </form>

            <h2>Base Callback URL</h2>
            <p class="description">This is the base path n8n should call for plugin API operations.</p>
            <?php $this->renderCopyableBlock('Base callback URL', $apiBase, 2); ?>

            <h2>n8n HTTP Request Node Setup</h2>
            <?php $this->renderHelpBox('Recommended Node Configuration', [
                'Method: choose GET or POST to match the endpoint below.',
                'URL: start with the base callback URL and append the endpoint path.',
                'Authentication: none in n8n itself. Send the bearer token manually as a header.',
                'Send Headers: on. Add Authorization and, when used, X-SINAPPSUS-Signature and Origin.',
                'Send Body: on for POST requests only. Use JSON mode.',
                'If you enforce a signing secret, generate the HMAC from the exact raw JSON body before the request is sent.',
            ]); ?>
            <table class="widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th>n8n Field</th>
                        <th>What To Put There</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>URL</td>
                        <td><code><?php echo esc_html($apiBase); ?>/...</code></td>
                    </tr>
                    <tr>
                        <td>Method</td>
                        <td><code>GET</code> for reads and searches, <code>POST</code> for writes such as meta updates and order notes.</td>
                    </tr>
                    <tr>
                        <td>Send Headers</td>
                        <td>Enable it and add the headers shown below.</td>
                    </tr>
                    <tr>
                        <td>Authorization Header</td>
                        <td><code>Bearer <?php echo esc_html($maskedToken); ?></code></td>
                    </tr>
                    <tr>
                        <td>Content-Type Header</td>
                        <td><code>application/json</code> for POST requests.</td>
                    </tr>
                    <tr>
                        <td>X-SINAPPSUS-Signature Header</td>
                        <td><code>HMAC_SHA256(raw_body, <?php echo esc_html($maskedSecret); ?>)</code> when signing is enabled.</td>
                    </tr>
                    <tr>
                        <td>Origin Header</td>
                        <td><code><?php echo esc_html($originExample); ?></code> when you want the request to match a configured trusted origin.</td>
                    </tr>
                    <tr>
                        <td>Body Content Type</td>
                        <td><code>JSON</code></td>
                    </tr>
                </tbody>
            </table>

            <h3>Copy-Ready Header Values</h3>
            <?php $this->renderCopyableBlock('Authorization header value', 'Bearer ' . $maskedToken, 2); ?>
            <?php $this->renderCopyableBlock('Origin header example', $originExample, 2); ?>
            <?php $this->renderCopyableBlock('Signature pseudo logic', "signature = HMAC_SHA256(raw_request_body, {$maskedSecret})\nheader['X-SINAPPSUS-Signature'] = signature", 4); ?>

            <h2>Headers Required From n8n</h2>
            <table class="widefat striped" style="max-width:1100px;">
                <thead>
                    <tr>
                        <th>Header</th>
                        <th>Required</th>
                        <th>Value</th>
                        <th>Why It Exists</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>Authorization</code></td>
                        <td>Yes</td>
                        <td><code>Bearer <?php echo esc_html($maskedToken); ?></code></td>
                        <td>Authenticates the request as an approved caller.</td>
                    </tr>
                    <tr>
                        <td><code>X-SINAPPSUS-Signature</code></td>
                        <td><?php echo $secret !== '' ? 'Yes' : 'Only if you configure a signing secret'; ?></td>
                        <td><code>HMAC_SHA256(raw_body, signing_secret)</code></td>
                        <td>Prevents tampering with callback payloads.</td>
                    </tr>
                    <tr>
                        <td><code>Origin</code></td>
                        <td><?php echo ! empty($trustedOrigins) ? 'Recommended when your n8n instance sends it' : 'Optional'; ?></td>
                        <td><code>https://your-n8n-domain.example</code></td>
                        <td>Must match a trusted origin if origin restrictions are configured.</td>
                    </tr>
                    <tr>
                        <td><code>Content-Type</code></td>
                        <td>For POST requests</td>
                        <td><code>application/json</code></td>
                        <td>Ensures WordPress parses the JSON request body correctly.</td>
                    </tr>
                </tbody>
            </table>

            <h2>Endpoint Reference</h2>
            <p class="description">Admin-only endpoints below are intended for wp-admin users. Callback endpoints are the ones n8n should call with the bearer token and signature.</p>
            <?php $this->renderApiEndpointTable($apiBase); ?>

            <h2>Typical n8n Use Cases</h2>
            <?php $this->renderHelpBox('Example Callback Patterns', [
                'Get a user record before pushing the customer into another system.',
                'Search for a post or order from a lookup term coming from an upstream workflow.',
                'Write metadata back to a WordPress user, post, or WooCommerce order after an external process completes.',
                'Add an internal or customer-visible order note from n8n after fulfillment, ERP, or support activity.',
            ]); ?>

            <h3>1. Read A User From n8n</h3>
            <p class="description">Use this when a webhook flow contains a WordPress user ID and you want the latest profile data before continuing in n8n.</p>
            <?php $this->renderCopyableBlock('Read a user request example', "GET {$apiBase}/entity/user/123\nAuthorization: Bearer {$maskedToken}\nX-SINAPPSUS-Signature: <signature of empty body if your secret is enabled>", 5); ?>

            <h4>HTTP Request Node Example</h4>
            <?php $this->renderCopyableBlock('Read a user node configuration', "Method: GET\nURL: {$apiBase}/entity/user/123\nHeaders:\n  Authorization: Bearer {$maskedToken}\n  X-SINAPPSUS-Signature: <signature if enabled>\n  Origin: {$originExample}", 8); ?>

            <h3>2. Search For An Order Or Post</h3>
            <p class="description">Use this when n8n only has a partial term such as an email, title fragment, or order reference and needs WordPress to perform the lookup.</p>
            <?php $this->renderCopyableBlock('Search request example', "GET {$apiBase}/search?type=order&term=smith&limit=10\nAuthorization: Bearer {$maskedToken}\nX-SINAPPSUS-Signature: <signature of empty body if your secret is enabled>", 5); ?>

            <h3>3. Update Meta After An External Step</h3>
            <p class="description">Use this to store external IDs, processing flags, CRM status, or sync markers back onto a WordPress entity.</p>
            <?php $this->renderCopyableBlock('Update meta request example', "POST {$apiBase}/action/meta\nContent-Type: application/json\nAuthorization: Bearer {$maskedToken}\nX-SINAPPSUS-Signature: <signature of raw JSON body>\n\n{\n  \"entity_type\": \"order\",\n  \"entity_id\": 1234,\n  \"meta_key\": \"external_invoice_id\",\n  \"meta_value\": \"INV-9001\"\n}", 12); ?>

            <h4>Meta Update JSON Body Preset</h4>
            <?php $this->renderCopyableBlock('Meta update body preset', "{\n  \"entity_type\": \"order\",\n  \"entity_id\": 1234,\n  \"meta_key\": \"external_invoice_id\",\n  \"meta_value\": \"INV-9001\"\n}", 8); ?>

            <h3>4. Add An Order Note</h3>
            <p class="description">Use this when n8n wants to record fulfillment, ERP, or support activity on a WooCommerce order.</p>
            <?php $this->renderCopyableBlock('Order note request example', "POST {$apiBase}/action/order-note\nContent-Type: application/json\nAuthorization: Bearer {$maskedToken}\nX-SINAPPSUS-Signature: <signature of raw JSON body>\n\n{\n  \"order_id\": 1234,\n  \"note\": \"ERP invoice created successfully.\",\n  \"customer_note\": false\n}", 12); ?>

            <h4>Order Note JSON Body Preset</h4>
            <?php $this->renderCopyableBlock('Order note body preset', "{\n  \"order_id\": 1234,\n  \"note\": \"ERP invoice created successfully.\",\n  \"customer_note\": false\n}", 7); ?>

            <h2>What n8n Should Not Use</h2>
            <p class="description">These endpoints are present for site administrators inside WordPress, not for bearer-token callback use from n8n.</p>
            <ul>
                <li><code><?php echo esc_html($apiBase . '/events'); ?></code> is admin-only and meant for browsing available trigger definitions.</li>
                <li><code><?php echo esc_html($apiBase . '/flows'); ?></code> is admin-only and meant for inspecting saved flows from wp-admin.</li>
                <li><code><?php echo esc_html($apiBase . '/logs'); ?></code> is admin-only and meant for operators troubleshooting deliveries.</li>
            </ul>

            <h2>Signing Reference</h2>
            <p class="description">When a signing secret is configured, hash the exact raw request body with HMAC-SHA256 and send the result in <code>X-SINAPPSUS-Signature</code>. For GET requests the raw body is empty.</p>
            <?php $this->renderCopyableBlock('Signing pseudo logic', "Pseudo logic\nsignature = HMAC_SHA256(raw_request_body, SIGNING_SECRET)\nheader['X-SINAPPSUS-Signature'] = signature", 5); ?>
            <?php $this->renderCopyScript(); ?>
        </div>
        <?php
    }

    public function renderNotifuse(): void
    {
        $settings = Settings::all();
        $notifuse = $settings['notifuse'] ?? [];
        $lists = $this->notifuseClient->getLists();
        $publicFormListIds = is_array($notifuse['public_form_list_ids'] ?? null) ? $notifuse['public_form_list_ids'] : [];
        ?>
        <div class="wrap">
            <h1>Notifuse</h1>
            <?php $this->renderHelpBox('What Belongs On This Page', [
                'Use this page only if you want WordPress to push contacts, events, or transactional triggers into an existing Notifuse workspace.',
                'This screen reads lists that already exist in Notifuse. It does not create new lists in Notifuse for you.',
                'Fallback Auto-Subscribe List ID should be an existing remote Notifuse list ID.',
                'Frontend list selection lets you choose which existing Notifuse lists visitors are allowed to subscribe to on forms.',
            ]); ?>
            <?php if (isset($_GET['notifuse_test'])) : ?>
                <div class="notice <?php echo $_GET['notifuse_test'] === 'success' ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html((string) ($_GET['message'] ?? '')); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('snc_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="snc-notifuse-base-url">Base URL</label></th>
                        <td>
                            <input id="snc-notifuse-base-url" class="regular-text" name="snc_settings[notifuse][base_url]" value="<?php echo esc_attr((string) ($notifuse['base_url'] ?? '')); ?>" />
                            <p class="description">Base URL of your existing Notifuse instance, for example https://v3.notifuse.com or your self-hosted domain.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-api-key">API Key</label></th>
                        <td>
                            <input id="snc-notifuse-api-key" class="regular-text" name="snc_settings[notifuse][api_key]" value="<?php echo esc_attr((string) ($notifuse['api_key'] ?? '')); ?>" />
                            <p class="description">API key with permission to read lists, upsert contacts, track events, and send transactional notifications.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-workspace-id">Workspace ID</label></th>
                        <td>
                            <input id="snc-notifuse-workspace-id" class="regular-text" name="snc_settings[notifuse][workspace_id]" value="<?php echo esc_attr((string) ($notifuse['workspace_id'] ?? '')); ?>" />
                            <p class="description">Required for Notifuse custom events and transactional sends. Use the workspace that owns the templates and lists you want this plugin to touch.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-list-id">Fallback Auto-Subscribe List ID</label></th>
                        <td>
                            <input id="snc-notifuse-list-id" class="regular-text" name="snc_settings[notifuse][default_list_id]" value="<?php echo esc_attr((string) ($notifuse['default_list_id'] ?? '')); ?>" />
                            <p class="description">Enter an existing Notifuse list ID. The plugin will add contacts to that remote list when no more specific list selection is provided.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-public-list-ids">Frontend Selectable Existing Lists</label></th>
                        <td>
                            <select id="snc-notifuse-public-list-ids" name="snc_settings[notifuse][public_form_list_ids][]" multiple size="6" style="min-width:320px;">
                                <?php foreach ($lists as $list) : ?>
                                    <?php $listId = (string) ($list['id'] ?? $list['uuid'] ?? ''); ?>
                                    <?php $label = (string) ($list['name'] ?? $listId); ?>
                                    <option value="<?php echo esc_attr($listId); ?>" <?php selected(in_array($listId, $publicFormListIds, true), true); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Choose which existing Notifuse lists site visitors may pick from in shortcode and Elementor subscribe forms.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Automation Behavior</th>
                        <td>
                            <label><input type="checkbox" name="snc_settings[notifuse][signup_on_registration]" value="1" <?php checked(! empty($notifuse['signup_on_registration']), true); ?> /> Auto-subscribe newly registered WordPress users</label><br />
                            <label><input type="checkbox" name="snc_settings[notifuse][signup_on_checkout]" value="1" <?php checked(! empty($notifuse['signup_on_checkout']), true); ?> /> Auto-subscribe WooCommerce customers after checkout</label>
                            <br /><label><input type="checkbox" name="snc_settings[notifuse][allow_unsubscribe]" value="1" <?php checked(! empty($notifuse['allow_unsubscribe']), true); ?> /> Enable unsubscribe shortcodes and Elementor widget</label>
                            <br /><label><input type="checkbox" name="snc_settings[notifuse][require_consent]" value="1" <?php checked(! empty($notifuse['require_consent']), true); ?> /> Require an explicit consent checkbox on subscribe forms</label>
                            <br /><label><input type="checkbox" name="snc_settings[notifuse][enable_custom_events]" value="1" <?php checked(! empty($notifuse['enable_custom_events']), true); ?> /> Send custom events such as signup, order paid, and refund activity into Notifuse</label>
                            <br /><label><input type="checkbox" name="snc_settings[notifuse][enable_transactional_emails]" value="1" <?php checked(! empty($notifuse['enable_transactional_emails']), true); ?> /> Trigger transactional notifications from WordPress and WooCommerce events</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-consent-label">Consent Label</label></th>
                        <td>
                            <input id="snc-notifuse-consent-label" class="regular-text" name="snc_settings[notifuse][consent_label]" value="<?php echo esc_attr((string) ($notifuse['consent_label'] ?? 'I agree to receive updates by email.')); ?>" />
                            <p class="description">The exact sentence visitors see next to the opt-in checkbox on subscribe forms.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-welcome-template">Welcome Transactional Template ID</label></th>
                        <td>
                            <input id="snc-notifuse-welcome-template" class="regular-text" name="snc_settings[notifuse][welcome_template_id]" value="<?php echo esc_attr((string) ($notifuse['welcome_template_id'] ?? '')); ?>" />
                            <p class="description">Existing Notifuse notification ID to send when a new WordPress user is created.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-order-confirmation-template">Order Confirmation Transactional Template ID</label></th>
                        <td>
                            <input id="snc-notifuse-order-confirmation-template" class="regular-text" name="snc_settings[notifuse][order_confirmation_template_id]" value="<?php echo esc_attr((string) ($notifuse['order_confirmation_template_id'] ?? '')); ?>" />
                            <p class="description">Existing Notifuse notification ID to send when checkout completes.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-order-paid-template">Order Paid Transactional Template ID</label></th>
                        <td>
                            <input id="snc-notifuse-order-paid-template" class="regular-text" name="snc_settings[notifuse][order_paid_template_id]" value="<?php echo esc_attr((string) ($notifuse['order_paid_template_id'] ?? '')); ?>" />
                            <p class="description">Existing Notifuse notification ID to send when WooCommerce marks an order as paid.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-notifuse-refund-template">Refund Transactional Template ID</label></th>
                        <td>
                            <input id="snc-notifuse-refund-template" class="regular-text" name="snc_settings[notifuse][refund_template_id]" value="<?php echo esc_attr((string) ($notifuse['refund_template_id'] ?? '')); ?>" />
                            <p class="description">Existing Notifuse notification ID to send when an order refund is created.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Notifuse Settings'); ?>
            </form>
            <h2>Available Lists</h2>
            <p class="description">Read-only view of lists returned by your current Notifuse credentials. If this table is empty, the plugin cannot yet see your remote workspace lists.</p>
            <table class="widefat striped" style="max-width:720px;">
                <thead><tr><th>List ID</th><th>Name</th><th>Visibility</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($lists as $list) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($list['id'] ?? $list['uuid'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($list['name'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($list['visibility'] ?? (! empty($list['is_public']) ? 'public' : 'private'))); ?></td>
                        <td><?php echo esc_html((string) ($list['status'] ?? 'active')); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lists)) : ?>
                    <tr><td colspan="4">No lists loaded yet. Save credentials and test the connection first.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="snc_test_notifuse" />
                <?php wp_nonce_field('snc_test_notifuse'); ?>
                <?php submit_button('Test Notifuse Connection', 'secondary'); ?>
            </form>
            <p>Elementor widgets available when Elementor is active: subscribe and unsubscribe. Shortcodes: <code>[snc_notifuse_subscribe]</code> and <code>[snc_notifuse_unsubscribe]</code>. Both shortcodes also support <code>redirect_url</code> for redirecting after a successful submission, for example <code>[snc_notifuse_subscribe list_ids="example-list" redirect_url="/free-download/"]</code>. Subscribe forms can optionally show the frontend-selectable lists and the consent checkbox configured here.</p>
        </div>
        <?php
    }

    public function renderErpnext(): void
    {
        $settings = Settings::all();
        $erpnext = $settings['erpnext'] ?? [];
        $companyOptions = $this->erpnextClient->getReferenceOptions('Company');
        $warehouseOptions = $this->erpnextClient->getReferenceOptions('Warehouse');
        $itemGroupOptions = $this->erpnextClient->getReferenceOptions('Item Group');
        $priceListOptions = $this->erpnextClient->getReferenceOptions('Price List');
        $customerGroupOptions = $this->erpnextClient->getReferenceOptions('Customer Group');
        $territoryOptions = $this->erpnextClient->getReferenceOptions('Territory');
        $customerFieldOptions = $this->erpCustomerDestinationOptions();
        $productFieldOptions = $this->erpProductDestinationOptions();
        ?>
        <div class="wrap">
            <h1>ERPNext</h1>
            <?php $this->renderHelpBox('What This Page Controls', [
                'Use this page only if WordPress should sync customers, orders, products, or stock with an existing ERPNext instance.',
                'The connection settings below do not create ERPNext doctypes. They point the plugin at your existing ERPNext site and API credentials.',
                'Sync toggles decide which kinds of WordPress data the plugin is allowed to send or pull.',
                'Mapping tables let you copy WordPress fields into ERPNext fields when names do not match, and the dropdowns below will load those field names directly from ERPNext when your connection works.',
            ]); ?>
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
                        <td>
                            <input id="snc-erp-host-url" class="regular-text" name="snc_settings[erpnext][host_url]" value="<?php echo esc_attr((string) ($erpnext['host_url'] ?? '')); ?>" />
                            <p class="description">Base URL of your ERPNext site, for example https://erp.example.com.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-api-key">API Key</label></th>
                        <td>
                            <input id="snc-erp-api-key" class="regular-text" name="snc_settings[erpnext][api_key]" value="<?php echo esc_attr((string) ($erpnext['api_key'] ?? '')); ?>" />
                            <p class="description">API credentials used for ERPNext REST calls.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-api-secret">API Secret</label></th>
                        <td>
                            <input id="snc-erp-api-secret" class="regular-text" name="snc_settings[erpnext][api_secret]" value="<?php echo esc_attr((string) ($erpnext['api_secret'] ?? '')); ?>" />
                            <p class="description">Keep this matched to the API key above.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-company">Company</label></th>
                        <td>
                            <?php $this->renderErpSelectOrInput('snc-erp-company', 'snc_settings[erpnext][company]', (string) ($erpnext['company'] ?? ''), $companyOptions, 'Default ERPNext company name used for Sales Order creation.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-warehouse">Warehouse</label></th>
                        <td>
                            <?php $this->renderErpSelectOrInput('snc-erp-warehouse', 'snc_settings[erpnext][warehouse]', (string) ($erpnext['warehouse'] ?? ''), $warehouseOptions, 'Default warehouse used when importing stock or exporting products.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-item-group">Item Group</label></th>
                        <td>
                            <?php $this->renderErpSelectOrInput('snc-erp-item-group', 'snc_settings[erpnext][item_group]', (string) ($erpnext['item_group'] ?? ''), $itemGroupOptions, 'Fallback ERPNext item group for exported WooCommerce products.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-price-list">Price List</label></th>
                        <td>
                            <?php $this->renderErpSelectOrInput('snc-erp-price-list', 'snc_settings[erpnext][price_list]', (string) ($erpnext['price_list'] ?? ''), $priceListOptions, 'Optional ERPNext price list reference if your process depends on one. Imported products will use this price list when available.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-customer-group">Customer Group</label></th>
                        <td>
                            <?php $this->renderErpSelectOrInput('snc-erp-customer-group', 'snc_settings[erpnext][customer_group]', (string) ($erpnext['customer_group'] ?? 'Commercial'), $customerGroupOptions, 'Fallback ERPNext customer group when WordPress users do not already store one.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-territory">Territory</label></th>
                        <td>
                            <?php $this->renderErpSelectOrInput('snc-erp-territory', 'snc_settings[erpnext][territory]', (string) ($erpnext['territory'] ?? 'All Territories'), $territoryOptions, 'Fallback ERPNext territory for synced customers.'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Sync Permissions</th>
                        <td>
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_customers]" value="1" <?php checked(! empty($erpnext['sync_customers']), true); ?> /> Allow WordPress and WooCommerce customers to sync into ERPNext</label><br />
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_orders]" value="1" <?php checked(! empty($erpnext['sync_orders']), true); ?> /> Allow WooCommerce orders to sync into ERPNext</label><br />
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_products]" value="1" <?php checked(! empty($erpnext['sync_products']), true); ?> /> Allow product catalog import and export</label><br />
                            <label><input type="checkbox" name="snc_settings[erpnext][sync_stock]" value="1" <?php checked(! empty($erpnext['sync_stock']), true); ?> /> Allow stock synchronization jobs</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="snc-erp-stock-source">Stock Source of Truth</label></th>
                        <td>
                            <select id="snc-erp-stock-source" name="snc_settings[erpnext][stock_source]">
                                <option value="erpnext" <?php selected((string) ($erpnext['stock_source'] ?? 'erpnext'), 'erpnext'); ?>>ERPNext</option>
                                <option value="woocommerce" <?php selected((string) ($erpnext['stock_source'] ?? 'erpnext'), 'woocommerce'); ?>>WooCommerce</option>
                            </select>
                            <p class="description">Choose which system should be treated as the stock authority during sync operations.</p>
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
                            <p class="description">How often the scheduled product and stock sync jobs should run.</p>
                        </td>
                    </tr>
                </table>
                <h2>Customer Field Mapping</h2>
                <?php $this->renderMappingTable('customer_mapping', $this->erpCustomerSourceFields(), is_array($erpnext['customer_mapping'] ?? null) ? $erpnext['customer_mapping'] : [], 'Map WordPress or WooCommerce customer fields into ERPNext Customer, Contact, or Address fields.', $customerFieldOptions); ?>
                <h2>Product Field Mapping</h2>
                <?php $this->renderMappingTable('product_mapping', $this->erpProductSourceFields(), is_array($erpnext['product_mapping'] ?? null) ? $erpnext['product_mapping'] : [], 'Map WooCommerce product fields into ERPNext item fields.', $productFieldOptions); ?>
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
            <p class="description">These tools act on your live WooCommerce and ERPNext data. They do not change the saved configuration above.</p>
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
            <p>ERP profile fields are also added to WordPress registration and user profile screens so staff can store ERP-specific customer metadata without leaving WordPress.</p>
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
            <p>Review what the plugin actually attempted: outbound flow deliveries, inbound API actions, integration calls, retries, and failures.</p>
            <?php $this->renderHelpBox('How To Read The Log Statuses', [
                'sent or integration_sent: the request completed successfully.',
                'failed or integration_failed: the request was attempted but did not succeed.',
                'retry_scheduled: the plugin queued another delivery attempt.',
                'dead_letter: retry attempts were exhausted.',
                'integration_skipped: ERPNext sync was intentionally skipped because the payload had not changed.',
            ]); ?>
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
            <p class="description">Replay is only available for entries that still have both a stored payload and an associated flow.</p>
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
            <p>Use this page for manual testing and quick operator tasks. It is meant for validation, not day-to-day configuration.</p>
            <?php $this->renderHelpBox('What This Page Is Good For', [
                'Send a manual test payload through an existing flow.',
                'Check which REST routes the plugin exposes for n8n.',
                'Validate a webhook path after saving or changing a flow.',
            ]); ?>
            <p>Plugin REST endpoints:</p>
            <ul>
                <li><code>/wp-json/sinappsus-n8n/v1/health</code></li>
                <li><code>/wp-json/sinappsus-n8n/v1/events</code></li>
                <li><code>/wp-json/sinappsus-n8n/v1/flows</code></li>
            </ul>
            <h2>Manual Test Send</h2>
            <p class="description">This sends a sample payload through the selected flow using its saved trigger, payload mode, and optional preview entity ID.</p>
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
            $sampleFactory = new SamplePayloadFactory();
            $payload = $sampleFactory->build(
                (string) ($flow['trigger_key'] ?? 'sinappsus.manual.test'),
                (int) ($flow['settings']['preview_entity_id'] ?? 0)
            );
            $payload['delivery'] = [
                'attempt' => 1,
                'max_attempts' => max(1, (int) ($flow['settings']['max_attempts'] ?? 1)),
                'queued_at' => gmdate('c'),
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

    private function renderHelpBox(string $title, array $items): void
    {
        ?>
        <div style="background:#fff;border:1px solid #dcdcde;border-left:4px solid #2271b1;padding:16px;max-width:1100px;margin:16px 0 24px;">
            <p style="margin:0 0 10px;font-size:14px;font-weight:600;"><?php echo esc_html($title); ?></p>
            <ul style="margin:0 0 0 18px;list-style:disc;">
                <?php foreach ($items as $item) : ?>
                    <li><?php echo esc_html((string) $item); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    private function renderApiEndpointTable(string $apiBase): void
    {
        $rows = [
            ['GET', $apiBase . '/health', 'No', 'Simple health check. Useful for confirming the plugin route exists and WooCommerce status.'],
            ['GET', $apiBase . '/entity/{type}/{id}', 'Yes', 'Read a single user, post, page, attachment, or WooCommerce order.'],
            ['GET', $apiBase . '/search?type=user|post|order&term=...&limit=...', 'Yes', 'Search WordPress or WooCommerce records from n8n.'],
            ['POST', $apiBase . '/action/meta', 'Yes', 'Write one meta value to a user, post, or WooCommerce order.'],
            ['POST', $apiBase . '/action/order-note', 'Yes', 'Append an order note to a WooCommerce order.'],
            ['GET', $apiBase . '/events', 'Admin only', 'Browse the event catalog inside wp-admin.'],
            ['GET', $apiBase . '/flows', 'Admin only', 'Inspect the saved flow list inside wp-admin.'],
            ['GET', $apiBase . '/logs', 'Admin only', 'Inspect plugin logs and log stats inside wp-admin.'],
        ];
        ?>
        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th>Method</th>
                    <th>Path</th>
                    <th>n8n Callback Use</th>
                    <th>Purpose</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row[0]); ?></td>
                        <td><code><?php echo esc_html($row[1]); ?></code></td>
                        <td><?php echo esc_html($row[2]); ?></td>
                        <td><?php echo esc_html($row[3]); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderCodeBlock(string $content): void
    {
        ?>
        <textarea readonly class="large-text code" rows="10" style="max-width:1100px;"><?php echo esc_textarea($content); ?></textarea>
        <?php
    }

    private function renderCopyableBlock(string $label, string $content, int $rows = 6): void
    {
        static $copyableIndex = 0;
        $copyableIndex++;
        $textareaId = 'snc-copyable-' . $copyableIndex;
        ?>
        <div style="max-width:1100px;margin:0 0 16px;">
            <p style="margin:0 0 6px;font-weight:600;"><?php echo esc_html($label); ?></p>
            <div style="display:flex;gap:8px;align-items:flex-start;">
                <textarea id="<?php echo esc_attr($textareaId); ?>" readonly class="large-text code" rows="<?php echo esc_attr((string) $rows); ?>" style="max-width:100%;"><?php echo esc_textarea($content); ?></textarea>
                <button type="button" class="button button-secondary snc-copy-button" data-target="<?php echo esc_attr($textareaId); ?>">Copy</button>
            </div>
        </div>
        <?php
    }

    private function renderCopyScript(): void
    {
        ?>
        <script>
        (function () {
            function copyText(targetId, button) {
                var element = document.getElementById(targetId);
                if (!element) {
                    return;
                }

                element.focus();
                element.select();
                element.setSelectionRange(0, element.value.length);

                try {
                    document.execCommand('copy');
                    var original = button.textContent;
                    button.textContent = 'Copied';
                    window.setTimeout(function () {
                        button.textContent = original;
                    }, 1200);
                } catch (error) {
                    button.textContent = 'Copy failed';
                }
            }

            document.querySelectorAll('.snc-copy-button').forEach(function (button) {
                button.addEventListener('click', function () {
                    copyText(button.getAttribute('data-target'), button);
                });
            });
        })();
        </script>
        <?php
    }
}
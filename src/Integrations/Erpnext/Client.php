<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Erpnext;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Flows\Logger;

final class Client
{
    private Logger $logger;

    private array $doctypeFieldCache = [];

    private array $recordOptionsCache = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function getDocTypeFieldOptions(string $doctype): array
    {
        $cacheKey = strtolower(trim($doctype));
        if ($cacheKey === '') {
            return [];
        }

        if (isset($this->doctypeFieldCache[$cacheKey])) {
            return $this->doctypeFieldCache[$cacheKey];
        }

        $response = $this->request('GET', '/api/resource/DocType/' . rawurlencode($doctype));
        if (! $response['success']) {
            $this->doctypeFieldCache[$cacheKey] = [];

            return [];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        $fields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : [];
        $options = [
            'name' => 'Document Name (name)',
        ];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $fieldName = sanitize_text_field((string) ($field['fieldname'] ?? ''));
            $fieldType = sanitize_text_field((string) ($field['fieldtype'] ?? ''));
            if ($fieldName === '' || in_array($fieldType, ['Section Break', 'Column Break', 'Tab Break', 'Fold', 'HTML', 'Button', 'Table', 'Table MultiSelect'], true)) {
                continue;
            }

            $label = sanitize_text_field((string) ($field['label'] ?? $fieldName));
            $options[$fieldName] = $label . ' (' . $fieldName . ($fieldType !== '' ? ' · ' . $fieldType : '') . ')';
        }

        $this->doctypeFieldCache[$cacheKey] = $options;

        return $options;
    }

    public function getReferenceOptions(string $doctype, string $labelField = 'name', int $limit = 200): array
    {
        $cacheKey = strtolower(trim($doctype)) . ':' . strtolower(trim($labelField)) . ':' . $limit;
        if (isset($this->recordOptionsCache[$cacheKey])) {
            return $this->recordOptionsCache[$cacheKey];
        }

        $fields = $labelField === 'name' ? ['name'] : ['name', $labelField];
        $response = $this->request(
            'GET',
            '/api/resource/' . rawurlencode($doctype)
            . '?fields=' . rawurlencode((string) wp_json_encode($fields))
            . '&limit_page_length=' . max(1, min(500, $limit))
            . '&order_by=' . rawurlencode('name asc')
        );

        if (! $response['success']) {
            $this->recordOptionsCache[$cacheKey] = [];

            return [];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $rows = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        $options = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $label = sanitize_text_field((string) ($row[$labelField] ?? $name));
            $options[$name] = $label === '' || $label === $name ? $name : $label . ' (' . $name . ')';
        }

        natcasesort($options);
        $this->recordOptionsCache[$cacheKey] = $options;

        return $options;
    }

    public function testConnection(): array
    {
        $settings = Settings::get('erpnext', []);
        $hostUrl = untrailingslashit((string) ($settings['host_url'] ?? ''));
        $apiKey = (string) ($settings['api_key'] ?? '');
        $apiSecret = (string) ($settings['api_secret'] ?? '');

        if ($hostUrl === '' || $apiKey === '' || $apiSecret === '') {
            return ['success' => false, 'message' => 'ERPNext host URL, API key, and API secret are required.'];
        }

        $response = wp_remote_get($hostUrl . '/api/method/frappe.auth.get_logged_user', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'token ' . $apiKey . ':' . $apiSecret,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return [
            'success' => $code >= 200 && $code < 300,
            'message' => $code >= 200 && $code < 300 ? 'ERPNext connection succeeded.' : 'ERPNext connection failed with HTTP ' . $code . '.',
        ];
    }

    public function contractDiagnostics(): array
    {
        $checks = [
            'customer_doctype' => $this->request('GET', '/api/resource/DocType/' . rawurlencode('Customer')),
            'item_doctype' => $this->request('GET', '/api/resource/DocType/' . rawurlencode('Item')),
            'sales_order_doctype' => $this->request('GET', '/api/resource/DocType/' . rawurlencode('Sales Order')),
            'bin_doctype' => $this->request('GET', '/api/resource/DocType/' . rawurlencode('Bin')),
        ];

        $messages = [];
        $success = true;

        foreach ($checks as $label => $result) {
            $checkSuccess = ! empty($result['success']);
            $messages[] = $label . ': ' . ($checkSuccess ? 'ok' : 'failed');
            if (! $checkSuccess) {
                $success = false;
            }
        }

        return [
            'success' => $success,
            'message' => implode(' | ', $messages),
            'checks' => $checks,
        ];
    }

    public function syncCustomerFromOrder(int $orderId): void
    {
        $settings = Settings::get('erpnext', []);
        if (empty($settings['sync_customers']) || ! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (! $order) {
            return;
        }

        $customerId = $order->get_customer_id();
        $customerGroup = $customerId > 0 ? (string) get_user_meta($customerId, 'snc_erp_customer_group', true) : '';
        $territory = $customerId > 0 ? (string) get_user_meta($customerId, 'snc_erp_territory', true) : '';
        $customerName = $customerId > 0 ? (string) get_user_meta($customerId, 'snc_erp_customer_name', true) : '';
        $user = $customerId > 0 ? get_user_by('id', $customerId) : false;

        $source = [
            'erp_customer_name' => $customerName !== '' ? $customerName : trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'user_login' => $user instanceof \WP_User ? (string) $user->user_login : '',
            'display_name' => $user instanceof \WP_User ? (string) $user->display_name : '',
            'user_email' => $user instanceof \WP_User ? (string) $user->user_email : '',
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_full_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
            'billing_company' => $order->get_billing_company(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_address_2' => $order->get_billing_address_2(),
            'billing_city' => $order->get_billing_city(),
            'billing_state' => $order->get_billing_state(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_country' => $order->get_billing_country(),
            'shipping_first_name' => $order->get_shipping_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name(),
            'shipping_full_name' => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
            'shipping_company' => $order->get_shipping_company(),
            'shipping_address_1' => $order->get_shipping_address_1(),
            'shipping_address_2' => $order->get_shipping_address_2(),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_state' => $order->get_shipping_state(),
            'shipping_postcode' => $order->get_shipping_postcode(),
            'shipping_country' => $order->get_shipping_country(),
            'erp_customer_group' => $customerGroup !== '' ? $customerGroup : (string) ($settings['customer_group'] ?? 'Commercial'),
            'erp_territory' => $territory !== '' ? $territory : (string) ($settings['territory'] ?? 'All Territories'),
            'wp_user_id' => $customerId,
        ];

        $payload = [
            'customer_name' => $customerName !== '' ? $customerName : trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'customer_type' => 'Individual',
            'customer_group' => $customerGroup !== '' ? $customerGroup : (string) ($settings['customer_group'] ?? 'Commercial'),
            'territory' => $territory !== '' ? $territory : (string) ($settings['territory'] ?? 'All Territories'),
            'email_id' => $order->get_billing_email(),
            'mobile_no' => $order->get_billing_phone(),
            'custom_wp_user_id' => $customerId,
        ];

        $mapping = is_array($settings['customer_mapping'] ?? null) ? $settings['customer_mapping'] : [];
        $mappedPayloads = $this->partitionCustomerMappingPayloads($source, $mapping);
        $payload = array_merge($payload, $mappedPayloads['customer']);

        $customerResponse = $this->upsertDoc(
            'Customer',
            $payload,
            'erpnext.customer.sync',
            [
                'custom_wp_user_id' => $customerId > 0 ? (string) $customerId : '',
                'email_id' => $order->get_billing_email(),
                'customer_name' => (string) $payload['customer_name'],
            ],
            $customerId > 0 ? [
                'entity_type' => 'user',
                'entity_id' => $customerId,
                'hash_key' => 'snc_erp_customer_sync_hash',
                'docname_key' => 'snc_erp_customer_docname',
            ] : null
        , true);

        if (empty($customerResponse['success'])) {
            return;
        }

        $customerDocName = $this->extractDocName($customerResponse, (string) ($payload['customer_name'] ?? ''));
        if ($customerDocName === null || $customerDocName === '') {
            return;
        }

        if (! empty($mappedPayloads['contact']) || $source['billing_email'] !== '' || $source['billing_phone'] !== '') {
            $this->syncCustomerContact($customerDocName, $mappedPayloads['contact'], $source, $customerId);
        }

        if ($this->hasAddressData($mappedPayloads['address'], $source)) {
            $this->syncCustomerAddress($customerDocName, $mappedPayloads['address'], $source, $customerId);
        }
    }

    public function syncOrder(int $orderId, string $context): void
    {
        $settings = Settings::get('erpnext', []);
        if (empty($settings['sync_orders']) || ! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (! $order) {
            return;
        }

        $items = [];
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $itemData = $item->get_data();
            $items[] = [
                'item_code' => (string) $item->get_product_id(),
                'item_name' => $item->get_name(),
                'qty' => $item->get_quantity(),
                'rate' => (string) ($itemData['total'] ?? ''),
            ];
        }

        $payload = [
            'doctype' => 'Sales Order',
            'company' => (string) ($settings['company'] ?? ''),
            'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'transaction_date' => gmdate('Y-m-d'),
            'items' => $items,
            'custom_wp_order_id' => $order->get_id(),
            'custom_sync_context' => $context,
        ];

        $this->upsertDoc(
            'Sales Order',
            $payload,
            'erpnext.order.sync',
            ['custom_wp_order_id' => (string) $order->get_id()],
            [
                'entity_type' => 'post',
                'entity_id' => $order->get_id(),
                'hash_key' => '_snc_erp_sales_order_sync_hash',
                'docname_key' => '_snc_erp_sales_order_docname',
            ]
        );
    }

    public function fetchItems(int $limit = 20): array
    {
        $desiredFields = [
            'name',
            'item_code',
            'item_name',
            'description',
            'item_group',
            'stock_uom',
            'default_warehouse',
            'disabled',
            'is_stock_item',
            'image',
        ];

        $availableFields = array_keys($this->getDocTypeFieldOptions('Item'));
        $fields = ! empty($availableFields)
            ? array_values(array_intersect($desiredFields, $availableFields))
            : ['name', 'item_code', 'item_name'];
        if (empty($fields)) {
            $fields = ['name', 'item_code', 'item_name'];
        }

        $response = $this->request(
            'GET',
            '/api/resource/Item?fields=' . rawurlencode((string) wp_json_encode($fields)) . '&limit_page_length=' . $limit
        );
        if (! $response['success']) {
            $response = $this->request('GET', '/api/resource/Item?limit_page_length=' . $limit);
            if (! $response['success']) {
                return [];
            }
        }

        $body = is_array($response['body']) ? $response['body'] : [];

        return isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
    }

    public function importItemsToWooCommerce(int $limit = 20): array
    {
        if (! function_exists('wc_get_product_id_by_sku')) {
            return ['success' => false, 'message' => 'WooCommerce is not active.'];
        }

        $settings = Settings::get('erpnext', []);
        $items = $this->fetchItems($limit);
        $itemCodes = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemCode = sanitize_text_field((string) ($item['item_code'] ?? $item['name'] ?? ''));
            if ($itemCode !== '') {
                $itemCodes[] = $itemCode;
            }
        }

        $priceMap = $this->fetchItemPriceMap($itemCodes, (string) ($settings['price_list'] ?? ''));
        $imported = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemCode = sanitize_text_field((string) ($item['item_code'] ?? $item['name'] ?? ''));
            if ($itemCode === '') {
                continue;
            }

            $productId = wc_get_product_id_by_sku($itemCode);
            $description = wp_kses_post((string) ($item['description'] ?? ''));
            $postData = [
                'post_title' => sanitize_text_field((string) ($item['item_name'] ?? $itemCode)),
                'post_content' => $description,
                'post_type' => 'product',
                'post_status' => 'publish',
            ];

            if ($productId > 0) {
                $postData['ID'] = $productId;
                wp_update_post($postData);
            } else {
                $productId = wp_insert_post($postData);
                if ($productId > 0) {
                    update_post_meta($productId, '_sku', $itemCode);
                }
            }

            if ($productId > 0) {
                $price = isset($priceMap[$itemCode]['price']) ? (string) $priceMap[$itemCode]['price'] : '';
                if ($price !== '') {
                    update_post_meta($productId, '_price', $price);
                    update_post_meta($productId, '_regular_price', $price);
                }

                $imageUrl = $this->buildErpAssetUrl((string) ($item['image'] ?? ''));
                update_post_meta($productId, '_snc_erp_item_code', $itemCode);
                update_post_meta($productId, '_snc_erp_item_docname', sanitize_text_field((string) ($item['name'] ?? $itemCode)));
                update_post_meta($productId, '_snc_erp_item_group', sanitize_text_field((string) ($item['item_group'] ?? (string) ($settings['item_group'] ?? ''))));
                update_post_meta($productId, '_snc_erp_warehouse', sanitize_text_field((string) ($item['default_warehouse'] ?? (string) ($settings['warehouse'] ?? ''))));
                update_post_meta($productId, '_snc_erp_stock_uom', sanitize_text_field((string) ($item['stock_uom'] ?? '')));
                update_post_meta($productId, '_snc_erp_image', esc_url_raw($imageUrl));
                if ($imageUrl !== '') {
                    $attachmentId = $this->ensureImportedProductImage($productId, $imageUrl, (string) ($item['item_name'] ?? $itemCode));
                    if ($attachmentId > 0) {
                        set_post_thumbnail($productId, $attachmentId);
                    }
                }
                $imported++;
            }
        }

        return ['success' => true, 'message' => 'Imported ' . $imported . ' products from ERPNext.'];
    }

    public function exportProductToErpnext(int $productId): array
    {
        $settings = Settings::get('erpnext', []);
        $product = function_exists('wc_get_product') ? wc_get_product($productId) : null;

        if (! $product) {
            return ['success' => false, 'message' => 'Product not found.'];
        }

        $source = [
            'erp_item_code' => (string) get_post_meta($productId, '_snc_erp_item_code', true) ?: ($product->get_sku() ?: (string) $product->get_id()),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'slug' => $product->get_slug(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'manage_stock' => $product->get_manage_stock() ? '1' : '0',
            'catalog_visibility' => $product->get_catalog_visibility(),
            'status' => $product->get_status(),
            'weight' => $product->get_weight(),
            'length' => $product->get_length(),
            'width' => $product->get_width(),
            'height' => $product->get_height(),
            'image_id' => (string) $product->get_image_id(),
            'image_url' => $this->getAttachmentUrl($product->get_image_id()),
            'gallery_image_ids' => implode(',', array_map('strval', $product->get_gallery_image_ids())),
            'gallery_image_urls' => implode(',', array_filter(array_map([$this, 'getAttachmentUrl'], $product->get_gallery_image_ids()))),
            'category_ids' => implode(',', $product->get_category_ids()),
            'category_names' => implode(', ', $this->getTermNames($productId, 'product_cat')),
            'tag_ids' => implode(',', $product->get_tag_ids()),
            'tag_names' => implode(', ', $this->getTermNames($productId, 'product_tag')),
            'permalink' => get_permalink($productId) ?: '',
            'erp_item_group' => (string) get_post_meta($productId, '_snc_erp_item_group', true) ?: (string) ($settings['item_group'] ?? ''),
            'erp_warehouse' => (string) get_post_meta($productId, '_snc_erp_warehouse', true) ?: (string) ($settings['warehouse'] ?? ''),
            'wp_product_id' => $product->get_id(),
        ];

        $payload = [
            'doctype' => 'Item',
            'item_code' => $source['erp_item_code'],
            'item_name' => $product->get_name(),
            'description' => $product->get_description(),
            'standard_rate' => $product->get_regular_price(),
            'stock_uom' => 'Nos',
            'is_stock_item' => 1,
            'item_group' => $source['erp_item_group'],
            'default_warehouse' => $source['erp_warehouse'],
            'custom_wp_product_id' => $product->get_id(),
        ];

        $payload = array_merge($payload, $this->applyConfiguredMapping($source, is_array($settings['product_mapping'] ?? null) ? $settings['product_mapping'] : []));

        return $this->upsertDoc(
            'Item',
            $payload,
            'erpnext.product.export',
            [
                'custom_wp_product_id' => (string) $product->get_id(),
                'item_code' => (string) $source['erp_item_code'],
            ],
            [
                'entity_type' => 'post',
                'entity_id' => $product->get_id(),
                'hash_key' => '_snc_erp_item_sync_hash',
                'docname_key' => '_snc_erp_item_docname',
            ],
            true
        ) ?? ['success' => false, 'message' => 'ERPNext export failed.'];
    }

    public function verifyStockLevel(int $productId): array
    {
        $product = function_exists('wc_get_product') ? wc_get_product($productId) : null;

        if (! $product) {
            return ['success' => false, 'message' => 'Product not found.'];
        }

        $sku = $product->get_sku() ?: (string) $product->get_id();
        $erpItemCode = (string) get_post_meta($productId, '_snc_erp_item_code', true);
        $settings = Settings::get('erpnext', []);
        $warehouse = (string) get_post_meta($productId, '_snc_erp_warehouse', true) ?: (string) ($settings['warehouse'] ?? '');
        $erpStock = $this->fetchStockQuantity($erpItemCode !== '' ? $erpItemCode : $sku, $warehouse);
        $wooStock = (string) $product->get_stock_quantity();

        return [
            'success' => true,
            'message' => 'WooCommerce stock: ' . $wooStock . ' | ERPNext stock: ' . $erpStock,
        ];
    }

    public function syncStockSnapshot(int $limit = 20): array
    {
        if (! function_exists('wc_get_product_id_by_sku')) {
            return ['success' => false, 'message' => 'WooCommerce is not active.'];
        }

        $settings = Settings::get('erpnext', []);
        $defaultWarehouse = (string) ($settings['warehouse'] ?? '');
        $items = $this->fetchItems($limit);
        $updated = 0;

        foreach ($items as $item) {
            $itemCode = sanitize_text_field((string) ($item['name'] ?? $item['item_code'] ?? ''));
            if ($itemCode === '') {
                continue;
            }

            $productId = wc_get_product_id_by_sku($itemCode);
            if ($productId <= 0) {
                continue;
            }

            $warehouse = (string) get_post_meta($productId, '_snc_erp_warehouse', true) ?: $defaultWarehouse;
            $stockQty = $this->fetchStockQuantity($itemCode, $warehouse);
            if ($stockQty === null) {
                continue;
            }

            wc_update_product_stock($productId, (float) $stockQty);
            $updated++;
        }

        return ['success' => true, 'message' => 'Updated stock for ' . $updated . ' products.'];
    }

    private function applyConfiguredMapping(array $sourceData, array $mapping): array
    {
        $payload = [];

        foreach ($mapping as $sourceKey => $targetKey) {
            $source = (string) $sourceKey;
            $target = sanitize_text_field((string) $targetKey);

            if ($target === '' || ! array_key_exists($source, $sourceData)) {
                continue;
            }

             if (strpos($target, '.') !== false) {
                [, $target] = array_pad(explode('.', $target, 2), 2, '');
            }

            if ($target === '') {
                continue;
            }

            $payload[$target] = $sourceData[$source];
        }

        return $payload;
    }

    private function partitionCustomerMappingPayloads(array $sourceData, array $mapping): array
    {
        $payloads = [
            'customer' => [],
            'contact' => [],
            'address' => [],
        ];

        foreach ($mapping as $sourceKey => $targetKey) {
            $source = (string) $sourceKey;
            $target = sanitize_text_field((string) $targetKey);

            if ($target === '' || ! array_key_exists($source, $sourceData)) {
                continue;
            }

            $bucket = 'customer';
            $fieldName = $target;
            if (strpos($target, '.') !== false) {
                [$bucket, $fieldName] = array_pad(explode('.', $target, 2), 2, '');
            }

            if (! isset($payloads[$bucket]) || $fieldName === '') {
                $bucket = 'customer';
                $fieldName = $target;
            }

            $payloads[$bucket][$fieldName] = $sourceData[$source];
        }

        return $payloads;
    }

    private function syncCustomerContact(string $customerDocName, array $mappedContactPayload, array $sourceData, int $customerId): void
    {
        $firstName = sanitize_text_field((string) ($mappedContactPayload['first_name'] ?? $sourceData['billing_first_name'] ?? ''));
        $lastName = sanitize_text_field((string) ($mappedContactPayload['last_name'] ?? $sourceData['billing_last_name'] ?? ''));
        $email = sanitize_email((string) ($mappedContactPayload['email_id'] ?? $sourceData['billing_email'] ?? ''));
        $phone = sanitize_text_field((string) ($mappedContactPayload['phone'] ?? ''));
        $mobile = sanitize_text_field((string) ($mappedContactPayload['mobile_no'] ?? $sourceData['billing_phone'] ?? ''));

        unset($mappedContactPayload['email_id'], $mappedContactPayload['phone'], $mappedContactPayload['mobile_no']);

        $payload = array_merge($mappedContactPayload, [
            'doctype' => 'Contact',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'links' => [[
                'link_doctype' => 'Customer',
                'link_name' => $customerDocName,
            ]],
        ]);

        if ($email !== '') {
            $payload['email_ids'] = [[
                'email_id' => $email,
                'is_primary' => 1,
            ]];
        }

        if ($phone !== '' || $mobile !== '') {
            $payload['phone_nos'] = [[
                'phone' => $mobile !== '' ? $mobile : $phone,
                'is_primary_mobile_no' => $mobile !== '' ? 1 : 0,
                'is_primary_phone' => $phone !== '' ? 1 : 0,
            ]];
        }

        $identity = $email !== '' ? ['name' => $email] : [];
        $this->upsertDoc(
            'Contact',
            $payload,
            'erpnext.contact.sync',
            $identity,
            $customerId > 0 ? [
                'entity_type' => 'user',
                'entity_id' => $customerId,
                'hash_key' => 'snc_erp_contact_sync_hash',
                'docname_key' => 'snc_erp_contact_docname',
            ] : null
        );
    }

    private function syncCustomerAddress(string $customerDocName, array $mappedAddressPayload, array $sourceData, int $customerId): void
    {
        $line1 = sanitize_text_field((string) ($mappedAddressPayload['address_line1'] ?? $sourceData['billing_address_1'] ?? ''));
        $line2 = sanitize_text_field((string) ($mappedAddressPayload['address_line2'] ?? $sourceData['billing_address_2'] ?? ''));
        $city = sanitize_text_field((string) ($mappedAddressPayload['city'] ?? $sourceData['billing_city'] ?? ''));
        $state = sanitize_text_field((string) ($mappedAddressPayload['state'] ?? $sourceData['billing_state'] ?? ''));
        $pincode = sanitize_text_field((string) ($mappedAddressPayload['pincode'] ?? $mappedAddressPayload['postal_code'] ?? $sourceData['billing_postcode'] ?? ''));
        $country = sanitize_text_field((string) ($mappedAddressPayload['country'] ?? $sourceData['billing_country'] ?? ''));

        $payload = array_merge($mappedAddressPayload, [
            'doctype' => 'Address',
            'address_title' => sanitize_text_field((string) ($mappedAddressPayload['address_title'] ?? $customerDocName)),
            'address_type' => sanitize_text_field((string) ($mappedAddressPayload['address_type'] ?? 'Billing')),
            'address_line1' => $line1,
            'address_line2' => $line2,
            'city' => $city,
            'state' => $state,
            'pincode' => $pincode,
            'country' => $country,
            'links' => [[
                'link_doctype' => 'Customer',
                'link_name' => $customerDocName,
            ]],
        ]);

        $this->upsertDoc(
            'Address',
            $payload,
            'erpnext.address.sync',
            [],
            $customerId > 0 ? [
                'entity_type' => 'user',
                'entity_id' => $customerId,
                'hash_key' => 'snc_erp_address_sync_hash',
                'docname_key' => 'snc_erp_address_docname',
            ] : null
        );
    }

    private function hasAddressData(array $mappedAddressPayload, array $sourceData): bool
    {
        $candidates = [
            $mappedAddressPayload['address_line1'] ?? '',
            $mappedAddressPayload['city'] ?? '',
            $mappedAddressPayload['country'] ?? '',
            $sourceData['billing_address_1'] ?? '',
            $sourceData['billing_city'] ?? '',
            $sourceData['billing_country'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (trim((string) $candidate) !== '') {
                return true;
            }
        }

        return false;
    }

    private function getAttachmentUrl($attachmentId): string
    {
        $attachmentId = (int) $attachmentId;
        if ($attachmentId <= 0) {
            return '';
        }

        $url = wp_get_attachment_url($attachmentId);

        return is_string($url) ? $url : '';
    }

    private function buildErpAssetUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return esc_url_raw($path);
        }

        $settings = Settings::get('erpnext', []);
        $hostUrl = untrailingslashit((string) ($settings['host_url'] ?? ''));
        if ($hostUrl === '') {
            return '';
        }

        return esc_url_raw($hostUrl . '/' . ltrim($path, '/'));
    }

    private function ensureImportedProductImage(int $productId, string $imageUrl, string $title): int
    {
        $imageUrl = esc_url_raw($imageUrl);
        if ($productId <= 0 || $imageUrl === '') {
            return 0;
        }

        $existingThumbnailId = (int) get_post_thumbnail_id($productId);
        $existingSource = (string) get_post_meta($productId, '_snc_erp_image_source', true);
        if ($existingThumbnailId > 0 && $existingSource !== '' && hash_equals($existingSource, $imageUrl)) {
            return $existingThumbnailId;
        }

        $existingAttachmentId = $this->findAttachmentBySourceUrl($imageUrl);
        if ($existingAttachmentId > 0) {
            update_post_meta($productId, '_snc_erp_image_source', $imageUrl);

            return $existingAttachmentId;
        }

        if (! function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachmentId = media_sideload_image($imageUrl, $productId, $title, 'id');
        if (is_wp_error($attachmentId)) {
            return 0;
        }

        $attachmentId = (int) $attachmentId;
        if ($attachmentId > 0) {
            update_post_meta($attachmentId, '_snc_erp_source_url', $imageUrl);
            update_post_meta($productId, '_snc_erp_image_source', $imageUrl);
        }

        return $attachmentId;
    }

    private function findAttachmentBySourceUrl(string $imageUrl): int
    {
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_query' => [[
                'key' => '_snc_erp_source_url',
                'value' => $imageUrl,
            ]],
        ]);

        if (empty($attachments)) {
            return 0;
        }

        return (int) $attachments[0];
    }

    private function getTermNames(int $postId, string $taxonomy): array
    {
        $terms = wp_get_post_terms($postId, $taxonomy, ['fields' => 'names']);
        if (is_wp_error($terms) || ! is_array($terms)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $terms)));
    }

    private function fetchStockQuantity(string $itemCode, string $warehouse): ?float
    {
        if ($itemCode === '') {
            return null;
        }

        $filters = [['item_code', '=', $itemCode]];
        if ($warehouse !== '') {
            $filters[] = ['warehouse', '=', $warehouse];
        }

        $response = $this->request(
            'GET',
            '/api/resource/Bin?fields=' . rawurlencode(wp_json_encode(['item_code', 'warehouse', 'actual_qty'])) . '&filters=' . rawurlencode(wp_json_encode($filters)) . '&limit_page_length=1'
        );

        if (! $response['success']) {
            return null;
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $rows = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

        if (empty($rows) || ! is_array($rows[0])) {
            return null;
        }

        return isset($rows[0]['actual_qty']) ? (float) $rows[0]['actual_qty'] : null;
    }

    private function fetchItemPriceMap(array $itemCodes, string $priceList = ''): array
    {
        $itemCodes = array_values(array_unique(array_filter(array_map(static function ($itemCode): string {
            return sanitize_text_field((string) $itemCode);
        }, $itemCodes))));

        if (empty($itemCodes)) {
            return [];
        }

        $filters = [
            ['item_code', 'in', $itemCodes],
            ['selling', '=', 1],
        ];
        if ($priceList !== '') {
            $filters[] = ['price_list', '=', sanitize_text_field($priceList)];
        }

        $fields = ['item_code', 'price_list', 'price_list_rate'];
        $response = $this->request(
            'GET',
            '/api/resource/' . rawurlencode('Item Price')
            . '?fields=' . rawurlencode((string) wp_json_encode($fields))
            . '&filters=' . rawurlencode((string) wp_json_encode($filters))
            . '&limit_page_length=' . max(20, count($itemCodes) * 5)
        );

        if (! $response['success']) {
            return [];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $rows = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
        $prices = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemCode = sanitize_text_field((string) ($row['item_code'] ?? ''));
            if ($itemCode === '' || isset($prices[$itemCode])) {
                continue;
            }

            $prices[$itemCode] = [
                'price' => isset($row['price_list_rate']) ? (float) $row['price_list_rate'] : 0.0,
                'price_list' => sanitize_text_field((string) ($row['price_list'] ?? '')),
            ];
        }

        return $prices;
    }

    private function upsertDoc(string $doctype, array $payload, string $eventKey, array $identityFields = [], ?array $syncState = null, bool $returnResponse = false): ?array
    {
        $hash = md5((string) wp_json_encode($payload));
        if ($syncState !== null && $this->isUnchangedSync($syncState, $hash)) {
            $response = [
                'success' => true,
                'message' => 'Skipped unchanged payload.',
                'code' => 208,
                'body' => ['skipped' => true],
            ];
            $this->logger->log([
                'event_key' => $eventKey,
                'entity_type' => 'erpnext',
                'status' => 'integration_skipped',
                'message' => $response['message'],
                'response_code' => $response['code'],
                'payload' => $payload,
            ]);

            return $returnResponse ? $response : null;
        }

        $existingName = null;
        if ($syncState !== null && ! empty($syncState['docname_key'])) {
            $existingName = $this->readSyncMeta((string) ($syncState['entity_type'] ?? ''), (int) ($syncState['entity_id'] ?? 0), (string) $syncState['docname_key']);
        }

        if ($existingName === null || $existingName === '') {
            $existingName = $this->findExistingDocName($doctype, $identityFields);
        }

        $method = $existingName === null ? 'POST' : 'PUT';
        $path = '/api/resource/' . rawurlencode($doctype) . ($existingName === null ? '' : '/' . rawurlencode($existingName));
        $response = $this->request($method, $path, $payload);
        $this->logger->log([
            'event_key' => $eventKey,
            'entity_type' => 'erpnext',
            'status' => $response['success'] ? ($existingName === null ? 'integration_sent' : 'integration_updated') : 'integration_failed',
            'message' => ['message' => $response['message'], 'method' => $method, 'remote_name' => $existingName],
            'response_code' => $response['code'],
            'payload' => $payload,
        ]);

        if ($response['success'] && $syncState !== null) {
            $this->persistSyncState($syncState, $hash, $this->extractDocName($response, $existingName));
        }

        return $returnResponse ? $response : null;
    }

    private function findExistingDocName(string $doctype, array $identityFields): ?string
    {
        foreach ($identityFields as $field => $value) {
            $fieldName = sanitize_text_field((string) $field);
            $fieldValue = sanitize_text_field((string) $value);

            if ($fieldName === '' || $fieldValue === '') {
                continue;
            }

            $response = $this->request(
                'GET',
                '/api/resource/' . rawurlencode($doctype) . '?fields=' . rawurlencode(wp_json_encode(['name', $fieldName])) . '&filters=' . rawurlencode(wp_json_encode([[$fieldName, '=', $fieldValue]])) . '&limit_page_length=1'
            );

            if (! $response['success']) {
                continue;
            }

            $body = is_array($response['body']) ? $response['body'] : [];
            $rows = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
            if (! empty($rows[0]['name'])) {
                return sanitize_text_field((string) $rows[0]['name']);
            }
        }

        return null;
    }

    private function isUnchangedSync(array $syncState, string $hash): bool
    {
        $storedHash = $this->readSyncMeta($syncState['entity_type'] ?? '', (int) ($syncState['entity_id'] ?? 0), (string) ($syncState['hash_key'] ?? ''));

        return $storedHash !== '' && hash_equals($storedHash, $hash);
    }

    private function persistSyncState(array $syncState, string $hash, ?string $docName): void
    {
        $entityType = (string) ($syncState['entity_type'] ?? '');
        $entityId = (int) ($syncState['entity_id'] ?? 0);
        $hashKey = (string) ($syncState['hash_key'] ?? '');
        $docnameKey = (string) ($syncState['docname_key'] ?? '');

        if ($entityId <= 0 || $hashKey === '') {
            return;
        }

        $this->writeSyncMeta($entityType, $entityId, $hashKey, $hash);

        if ($docnameKey !== '' && $docName !== null && $docName !== '') {
            $this->writeSyncMeta($entityType, $entityId, $docnameKey, $docName);
        }
    }

    private function readSyncMeta(string $entityType, int $entityId, string $metaKey): string
    {
        if ($entityId <= 0 || $metaKey === '') {
            return '';
        }

        if ($entityType === 'user') {
            return (string) get_user_meta($entityId, $metaKey, true);
        }

        if ($entityType === 'post') {
            return (string) get_post_meta($entityId, $metaKey, true);
        }

        return '';
    }

    private function writeSyncMeta(string $entityType, int $entityId, string $metaKey, string $value): void
    {
        if ($entityId <= 0 || $metaKey === '') {
            return;
        }

        if ($entityType === 'user') {
            update_user_meta($entityId, $metaKey, $value);

            return;
        }

        if ($entityType === 'post') {
            update_post_meta($entityId, $metaKey, $value);
        }
    }

    private function extractDocName(array $response, ?string $fallback): ?string
    {
        $body = is_array($response['body'] ?? null) ? $response['body'] : [];
        if (isset($body['data']['name'])) {
            return sanitize_text_field((string) $body['data']['name']);
        }

        return $fallback;
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $settings = Settings::get('erpnext', []);
        $hostUrl = untrailingslashit((string) ($settings['host_url'] ?? ''));
        $apiKey = (string) ($settings['api_key'] ?? '');
        $apiSecret = (string) ($settings['api_secret'] ?? '');

        if ($hostUrl === '' || $apiKey === '' || $apiSecret === '') {
            return ['success' => false, 'message' => 'ERPNext host URL, API key, and API secret are required.', 'code' => 0, 'body' => []];
        }

        $args = [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'token ' . $apiKey . ':' . $apiSecret,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'method' => strtoupper($method),
        ];

        if (! empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($hostUrl . $path, $args);
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message(), 'code' => 0, 'body' => []];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return [
            'success' => $code >= 200 && $code < 300,
            'message' => $code >= 200 && $code < 300 ? 'Request succeeded.' : 'Request failed with HTTP ' . $code . '.',
            'code' => $code,
            'body' => is_array($body) ? $body : [],
        ];
    }
}
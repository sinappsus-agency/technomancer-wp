<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Erpnext;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Flows\Logger;

final class Client
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
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

        $source = [
            'erp_customer_name' => $customerName !== '' ? $customerName : trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
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
        ];

        $payload = array_merge($payload, $this->applyConfiguredMapping($source, is_array($settings['customer_mapping'] ?? null) ? $settings['customer_mapping'] : []));

        $this->postDoc('Customer', $payload, 'erpnext.customer.sync');
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

        $this->postDoc('Sales Order', $payload, 'erpnext.order.sync');
    }

    public function fetchItems(int $limit = 20): array
    {
        $response = $this->request('GET', '/api/resource/Item?limit_page_length=' . $limit);
        if (! $response['success']) {
            return [];
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
        $imported = 0;
        foreach ($items as $item) {
            $itemCode = sanitize_text_field((string) ($item['name'] ?? $item['item_code'] ?? ''));
            if ($itemCode === '') {
                continue;
            }

            $productId = wc_get_product_id_by_sku($itemCode);
            $postData = [
                'post_title' => sanitize_text_field((string) ($item['item_name'] ?? $itemCode)),
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
                update_post_meta($productId, '_price', (string) ($item['standard_rate'] ?? '0'));
                update_post_meta($productId, '_regular_price', (string) ($item['standard_rate'] ?? '0'));
                update_post_meta($productId, '_snc_erp_item_code', $itemCode);
                update_post_meta($productId, '_snc_erp_item_group', sanitize_text_field((string) ($item['item_group'] ?? (string) ($settings['item_group'] ?? ''))));
                update_post_meta($productId, '_snc_erp_warehouse', sanitize_text_field((string) ($settings['warehouse'] ?? '')));
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
            'description' => $product->get_description(),
            'regular_price' => $product->get_regular_price(),
            'stock_quantity' => $product->get_stock_quantity(),
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

        return $this->postDoc('Item', $payload, 'erpnext.product.export', true);
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
            $target = (string) $targetKey;

            if ($target === '' || ! array_key_exists($source, $sourceData)) {
                continue;
            }

            $payload[$target] = $sourceData[$source];
        }

        return $payload;
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

    private function postDoc(string $doctype, array $payload, string $eventKey, bool $returnResponse = false): ?array
    {
        $response = $this->request('POST', '/api/resource/' . rawurlencode($doctype), $payload);
        $this->logger->log([
            'event_key' => $eventKey,
            'entity_type' => 'erpnext',
            'status' => $response['success'] ? 'integration_sent' : 'integration_failed',
            'message' => $response['message'],
            'response_code' => $response['code'],
            'payload' => $payload,
        ]);

        return $returnResponse ? $response : null;
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
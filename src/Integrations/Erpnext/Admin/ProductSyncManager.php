<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Integrations\Erpnext\Admin;

use TechnomancerWp\Connector\Flows\Logger;
use TechnomancerWp\Connector\Integrations\Erpnext\Client;

final class ProductSyncManager
{
    private Client $client;

    private Logger $logger;

    public function __construct(Client $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function importProducts(): void
    {
        $this->guard('tmwp_erp_import_products');
        $limit = isset($_POST['limit']) ? max(1, min(100, (int) $_POST['limit'])) : 20;
        $result = $this->client->importItemsToWooCommerce($limit);
        $this->redirect('snc-erpnext', 'erp_action', $result);
    }

    public function exportProduct(): void
    {
        $this->guard('tmwp_erp_export_product');
        $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $result = $this->client->exportProductToErpnext($productId);
        $this->redirect('snc-erpnext', 'erp_action', $result);
    }

    public function verifyStock(): void
    {
        $this->guard('tmwp_erp_verify_stock');
        $productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $result = $this->client->verifyStockLevel($productId);
        $this->redirect('snc-erpnext', 'erp_action', $result);
    }

    public function runProductSync(): void
    {
        $this->guard('tmwp_erp_run_product_sync');
        $result = $this->client->importItemsToWooCommerce(50);
        $this->redirect('snc-erpnext', 'erp_action', $result);
    }

    public function runStockSync(): void
    {
        $this->guard('tmwp_erp_run_stock_sync');
        $result = $this->client->syncStockSnapshot(50);
        $this->redirect('snc-erpnext', 'erp_action', $result);
    }

    private function guard(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer($action);
    }

    private function redirect(string $page, string $flag, array $result): void
    {
        wp_safe_redirect(add_query_arg([
            'page' => $page,
            $flag => $result['success'] ? 'success' : 'error',
            'message' => rawurlencode((string) ($result['message'] ?? 'Operation completed.')),
        ], admin_url('admin.php')));
        exit;
    }
}

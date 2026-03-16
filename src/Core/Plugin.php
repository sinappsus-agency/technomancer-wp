<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Core;

use Sinappsus\N8nConnector\Admin\AdminPages;
use Sinappsus\N8nConnector\Api\RestController;
use Sinappsus\N8nConnector\Events\EventManager;
use Sinappsus\N8nConnector\Flows\Dispatcher;
use Sinappsus\N8nConnector\Flows\FlowRepository;
use Sinappsus\N8nConnector\Flows\Logger;
use Sinappsus\N8nConnector\Integrations\Erpnext\Admin\ProfileFieldsManager as ErpnextProfileFieldsManager;
use Sinappsus\N8nConnector\Integrations\Erpnext\Admin\ProductFieldsManager;
use Sinappsus\N8nConnector\Integrations\Erpnext\Admin\ProductSyncManager;
use Sinappsus\N8nConnector\Integrations\Erpnext\Client as ErpnextClient;
use Sinappsus\N8nConnector\Integrations\Erpnext\Sync\SyncScheduler;
use Sinappsus\N8nConnector\Integrations\Notifuse\Elementor\WidgetRegistrar;
use Sinappsus\N8nConnector\Integrations\Notifuse\Client as NotifuseClient;
use Sinappsus\N8nConnector\Integrations\Notifuse\ProfileManager as NotifuseProfileManager;
use Sinappsus\N8nConnector\Security\RequestAuthorizer;

final class Plugin
{
    private static ?self $instance = null;

    private FlowRepository $flows;

    private Logger $logger;

    private Dispatcher $dispatcher;

    private EventManager $events;

    private AdminPages $adminPages;

    private RestController $restController;

    private RequestAuthorizer $authorizer;

    private NotifuseClient $notifuseClient;

    private ErpnextClient $erpnextClient;

    private NotifuseProfileManager $notifuseProfileManager;

    private ErpnextProfileFieldsManager $erpnextProfileFieldsManager;

    private ProductSyncManager $productSyncManager;

    private ProductFieldsManager $productFieldsManager;

    private SyncScheduler $syncScheduler;

    private WidgetRegistrar $widgetRegistrar;

    public static function boot(): void
    {
        if (self::$instance instanceof self) {
            return;
        }

        self::$instance = new self();
        self::$instance->register();
    }

    private function __construct()
    {
        $this->flows = new FlowRepository();
        $this->logger = new Logger();
        $this->authorizer = new RequestAuthorizer($this->logger);
        $this->notifuseClient = new NotifuseClient($this->logger);
        $this->erpnextClient = new ErpnextClient($this->logger);
        $this->notifuseProfileManager = new NotifuseProfileManager($this->notifuseClient);
        $this->erpnextProfileFieldsManager = new ErpnextProfileFieldsManager();
        $this->productSyncManager = new ProductSyncManager($this->erpnextClient, $this->logger);
        $this->productFieldsManager = new ProductFieldsManager();
        $this->syncScheduler = new SyncScheduler($this->erpnextClient, $this->logger);
        $this->widgetRegistrar = new WidgetRegistrar();
        $this->dispatcher = new Dispatcher($this->flows, $this->logger);
        $this->events = new EventManager($this->flows, $this->dispatcher, $this->notifuseClient, $this->erpnextClient);
        $this->adminPages = new AdminPages($this->flows, $this->logger, $this->notifuseClient, $this->erpnextClient);
        $this->restController = new RestController($this->flows, $this->logger, $this->authorizer);
    }

    private function register(): void
    {
        add_action('admin_menu', [$this->adminPages, 'registerMenu']);
        add_action('admin_init', [$this->adminPages, 'registerSettings']);
        add_action('admin_post_snc_save_flow', [$this->adminPages, 'saveFlow']);
        add_action('admin_post_snc_delete_flow', [$this->adminPages, 'deleteFlow']);
        add_action('admin_post_snc_test_notifuse', [$this->adminPages, 'testNotifuse']);
        add_action('admin_post_snc_test_erpnext', [$this->adminPages, 'testErpnext']);
        add_action('admin_post_snc_replay_log', [$this->adminPages, 'replayLog']);
        add_action('admin_post_snc_send_test_flow', [$this->adminPages, 'sendTestFlow']);
        add_action('admin_post_snc_erp_import_products', [$this->productSyncManager, 'importProducts']);
        add_action('admin_post_snc_erp_export_product', [$this->productSyncManager, 'exportProduct']);
        add_action('admin_post_snc_erp_verify_stock', [$this->productSyncManager, 'verifyStock']);
        add_action('admin_post_snc_erp_run_product_sync', [$this->productSyncManager, 'runProductSync']);
        add_action('admin_post_snc_erp_run_stock_sync', [$this->productSyncManager, 'runStockSync']);
        add_action('admin_post_snc_test_erp_contracts', [$this->adminPages, 'testErpContracts']);
        add_action('admin_post_snc_clear_logs', [$this->adminPages, 'clearLogs']);

        add_action('rest_api_init', [$this->restController, 'registerRoutes']);

        add_action('sinappsus_n8n_process_delivery', [$this->dispatcher, 'processDelivery'], 10, 2);

        $this->notifuseProfileManager->register();
        $this->erpnextProfileFieldsManager->register();
        $this->productFieldsManager->register();
        $this->syncScheduler->register();
        $this->widgetRegistrar->register();
        $this->events->register();
    }
}
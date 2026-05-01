<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Core;

use TechnomancerWp\Connector\Admin\AdminPages;
use TechnomancerWp\Connector\Api\RestController;
use TechnomancerWp\Connector\Events\EventManager;
use TechnomancerWp\Connector\Flows\Dispatcher;
use TechnomancerWp\Connector\Flows\FlowRepository;
use TechnomancerWp\Connector\Flows\Logger;
use TechnomancerWp\Connector\Integrations\Erpnext\Admin\ProfileFieldsManager as ErpnextProfileFieldsManager;
use TechnomancerWp\Connector\Integrations\Erpnext\Admin\ProductFieldsManager;
use TechnomancerWp\Connector\Integrations\Erpnext\Admin\ProductSyncManager;
use TechnomancerWp\Connector\Integrations\Erpnext\Client as ErpnextClient;
use TechnomancerWp\Connector\Integrations\Erpnext\Sync\SyncScheduler;
use TechnomancerWp\Connector\Integrations\Notifuse\Elementor\WidgetRegistrar;
use TechnomancerWp\Connector\Integrations\Notifuse\Client as NotifuseClient;
use TechnomancerWp\Connector\Integrations\Notifuse\ProfileManager as NotifuseProfileManager;
use TechnomancerWp\Connector\Security\RequestAuthorizer;


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

    private ?object $wooCommerceIntegration = null;

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
        $this->notifuseProfileManager = new NotifuseProfileManager($this->notifuseClient, $this->logger);
        $this->erpnextProfileFieldsManager = new ErpnextProfileFieldsManager($this->erpnextClient);
        $this->productSyncManager = new ProductSyncManager($this->erpnextClient, $this->logger);
        $this->productFieldsManager = new ProductFieldsManager($this->erpnextClient);
        $this->syncScheduler = new SyncScheduler($this->erpnextClient, $this->logger);
        $this->widgetRegistrar = new WidgetRegistrar();
        $this->dispatcher = new Dispatcher($this->flows, $this->logger);
        $this->events = new EventManager($this->flows, $this->dispatcher, $this->notifuseClient, $this->erpnextClient);
        $this->adminPages = new AdminPages($this->flows, $this->logger, $this->notifuseClient, $this->erpnextClient);
        $this->restController = new RestController($this->flows, $this->logger, $this->authorizer);
        $wooCommerceIntegrationClass = '\\TechnomancerWp\\Connector\\Integrations\\WooCommerce\\WooCommerceIntegration';
        if (class_exists($wooCommerceIntegrationClass)) {
            $this->wooCommerceIntegration = new $wooCommerceIntegrationClass();
        }
    }

    private function register(): void
    {
        add_action('admin_menu', [$this->adminPages, 'registerMenu']);
        add_action('admin_init', [$this->adminPages, 'registerSettings']);
        add_action('admin_post_tmwp_save_flow', [$this->adminPages, 'saveFlow']);
        add_action('admin_post_tmwp_delete_flow', [$this->adminPages, 'deleteFlow']);
        add_action('admin_post_tmwp_test_notifuse', [$this->adminPages, 'testNotifuse']);
        add_action('admin_post_tmwp_test_erpnext', [$this->adminPages, 'testErpnext']);
        add_action('admin_post_tmwp_replay_log', [$this->adminPages, 'replayLog']);
        add_action('admin_post_tmwp_send_test_flow', [$this->adminPages, 'sendTestFlow']);
        add_action('admin_post_tmwp_erp_import_products', [$this->productSyncManager, 'importProducts']);
        add_action('admin_post_tmwp_erp_export_product', [$this->productSyncManager, 'exportProduct']);
        add_action('admin_post_tmwp_erp_verify_stock', [$this->productSyncManager, 'verifyStock']);
        add_action('admin_post_tmwp_erp_run_product_sync', [$this->productSyncManager, 'runProductSync']);
        add_action('admin_post_tmwp_erp_run_stock_sync', [$this->productSyncManager, 'runStockSync']);
        add_action('admin_post_tmwp_test_erp_contracts', [$this->adminPages, 'testErpContracts']);
        add_action('admin_post_tmwp_clear_logs', [$this->adminPages, 'clearLogs']);

        add_action('rest_api_init', [$this->restController, 'registerRoutes']);

        add_action('tmwp_process_delivery', [$this->dispatcher, 'processDelivery'], 10, 2);

        $this->notifuseProfileManager->register();
        $this->erpnextProfileFieldsManager->register();
        $this->productFieldsManager->register();
        $this->syncScheduler->register();
        $this->widgetRegistrar->register();
        if ($this->wooCommerceIntegration !== null && method_exists($this->wooCommerceIntegration, 'register')) {
            $this->wooCommerceIntegration->register();
        }
        $this->events->register();
    }
}

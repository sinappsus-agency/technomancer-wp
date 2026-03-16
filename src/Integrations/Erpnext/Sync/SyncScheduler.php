<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Erpnext\Sync;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Flows\Logger;
use Sinappsus\N8nConnector\Integrations\Erpnext\Client;

final class SyncScheduler
{
    private Client $client;

    private Logger $logger;

    public function __construct(Client $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'cronSchedules']);
        add_action('init', [$this, 'ensureSchedules']);
        add_action('snc_erp_sync_products', [$this, 'syncProducts']);
        add_action('snc_erp_sync_stock', [$this, 'syncStock']);
    }

    public function cronSchedules(array $schedules): array
    {
        $schedules['snc_every_fifteen_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => 'Every 15 Minutes',
        ];

        return $schedules;
    }

    public function ensureSchedules(): void
    {
        $settings = Settings::get('erpnext', []);
        $interval = (string) ($settings['sync_interval'] ?? 'hourly');
        if ($interval === '15min') {
            $interval = 'snc_every_fifteen_minutes';
        }

        $this->ensureEvent('snc_erp_sync_products', ! empty($settings['sync_products']), $interval);
        $this->ensureEvent('snc_erp_sync_stock', ! empty($settings['sync_stock']), $interval);
    }

    public function syncProducts(): void
    {
        $result = $this->client->importItemsToWooCommerce(20);
        $this->logger->log([
            'event_key' => 'erpnext.sync.products.scheduled',
            'entity_type' => 'erpnext',
            'status' => ! empty($result['success']) ? 'integration_sent' : 'integration_failed',
            'message' => $result['message'] ?? 'Scheduled product sync completed.',
        ]);
    }

    public function syncStock(): void
    {
        $result = $this->client->syncStockSnapshot(20);
        $this->logger->log([
            'event_key' => 'erpnext.sync.stock.scheduled',
            'entity_type' => 'erpnext',
            'status' => ! empty($result['success']) ? 'integration_sent' : 'integration_failed',
            'message' => $result['message'] ?? 'Scheduled stock sync completed.',
        ]);
    }

    private function ensureEvent(string $hook, bool $enabled, string $interval): void
    {
        $scheduled = wp_next_scheduled($hook);

        if ($enabled && ! $scheduled) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, $interval, $hook);
        }

        if (! $enabled && $scheduled) {
            wp_unschedule_event($scheduled, $hook);
        }
    }
}
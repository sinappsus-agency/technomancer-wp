<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Flows;

final class FlowRepository
{
    public function all(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'snc_flows';
        $results = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC", ARRAY_A);

        return array_map([$this, 'mapFlow'], is_array($results) ? $results : []);
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'snc_flows';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $this->mapFlow($row) : null;
    }

    public function findEnabledByTrigger(string $triggerKey): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'snc_flows';
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE trigger_key = %s AND is_enabled = 1 ORDER BY updated_at DESC",
                $triggerKey
            ),
            ARRAY_A
        );

        return array_map([$this, 'mapFlow'], is_array($results) ? $results : []);
    }

    public function save(array $data): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'snc_flows';
        $now = current_time('mysql');
        $payload = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'trigger_key' => sanitize_text_field($data['trigger_key'] ?? ''),
            'webhook_url' => esc_url_raw($data['webhook_url'] ?? ''),
            'secret_key' => sanitize_text_field($data['secret_key'] ?? ''),
            'payload_mode' => sanitize_text_field($data['payload_mode'] ?? 'standard'),
            'filters' => wp_json_encode($data['filters'] ?? []),
            'settings' => wp_json_encode($data['settings'] ?? []),
            'is_enabled' => empty($data['is_enabled']) ? 0 : 1,
            'updated_at' => $now,
        ];

        $flowId = isset($data['id']) ? (int) $data['id'] : 0;

        if ($flowId > 0) {
            $wpdb->update($table, $payload, ['id' => $flowId]);

            return $flowId;
        }

        $payload['created_at'] = $now;
        $wpdb->insert($table, $payload);

        return (int) $wpdb->insert_id;
    }

    public function delete(int $id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'snc_flows';
        $wpdb->delete($table, ['id' => $id]);
    }

    private function mapFlow(array $flow): array
    {
        $flow['id'] = (int) ($flow['id'] ?? 0);
        $flow['is_enabled'] = (bool) ($flow['is_enabled'] ?? false);
        $flow['filters'] = $this->decodeJson($flow['filters'] ?? '[]');
        $flow['settings'] = $this->decodeJson($flow['settings'] ?? '[]');

        return $flow;
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
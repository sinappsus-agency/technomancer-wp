<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Flows;

final class Logger
{
    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'snc_logs';
    }

    public function log(array $entry): void
    {
        global $wpdb;

        $table = $this->tableName();
        $wpdb->insert($table, [
            'flow_id' => (int) ($entry['flow_id'] ?? 0),
            'event_key' => sanitize_text_field($entry['event_key'] ?? ''),
            'entity_type' => sanitize_text_field($entry['entity_type'] ?? ''),
            'entity_id' => (int) ($entry['entity_id'] ?? 0),
            'status' => sanitize_text_field($entry['status'] ?? 'info'),
            'message' => isset($entry['message']) ? wp_json_encode($entry['message']) : null,
            'payload' => isset($entry['payload']) ? wp_json_encode($entry['payload']) : null,
            'response_code' => isset($entry['response_code']) ? (int) $entry['response_code'] : null,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function recent(int $limit = 20): array
    {
        return $this->query([
            'limit' => $limit,
        ]);
    }

    public function query(array $filters = []): array
    {
        global $wpdb;

        $table = $this->tableName();
        $where = ['1=1'];
        $values = [];
        $limit = isset($filters['limit']) ? max(1, min(500, (int) $filters['limit'])) : 20;

        if (! empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = sanitize_text_field((string) $filters['status']);
        }

        if (! empty($filters['event_key'])) {
            $where[] = 'event_key = %s';
            $values[] = sanitize_text_field((string) $filters['event_key']);
        }

        if (! empty($filters['entity_type'])) {
            $where[] = 'entity_type = %s';
            $values[] = sanitize_text_field((string) $filters['entity_type']);
        }

        if (! empty($filters['flow_id'])) {
            $where[] = 'flow_id = %d';
            $values[] = (int) $filters['flow_id'];
        }

        if (! empty($filters['search'])) {
            $where[] = '(event_key LIKE %s OR status LIKE %s OR entity_type LIKE %s OR message LIKE %s)';
            $like = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT %d';
        $values[] = $limit;
        $prepared = $wpdb->prepare($sql, ...$values);
        $results = $wpdb->get_results($prepared, ARRAY_A);

        if (! is_array($results)) {
            return [];
        }

        return array_map([$this, 'normalizeRow'], $results);
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $table = $this->tableName();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function stats(): array
    {
        global $wpdb;

        $table = $this->tableName();
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A);
        $stats = [
            'total' => 0,
            'by_status' => [],
        ];

        if (! is_array($rows)) {
            return $stats;
        }

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'unknown');
            $count = (int) ($row['total'] ?? 0);
            $stats['by_status'][$status] = $count;
            $stats['total'] += $count;
        }

        return $stats;
    }

    public function clear(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $wpdb->query("TRUNCATE TABLE {$table}");
    }

    private function normalizeRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['flow_id'] = (int) ($row['flow_id'] ?? 0);
        $row['entity_id'] = (int) ($row['entity_id'] ?? 0);
        $row['response_code'] = isset($row['response_code']) ? (int) $row['response_code'] : null;
        $row['message_json'] = $this->decodeJsonField($row['message'] ?? null);
        $row['payload_json'] = $this->decodeJsonField($row['payload'] ?? null);

        return $row;
    }

    private function decodeJsonField($value)
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
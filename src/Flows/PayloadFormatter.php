<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Flows;

final class PayloadFormatter
{
    public function format(array $flow, array $payload): array
    {
        $payload = $this->normalizeDelivery($flow, $payload);
        $mode = (string) ($flow['payload_mode'] ?? 'standard');

        if ($mode === 'minimal') {
            return [
                'event_id' => (string) ($payload['event_id'] ?? ''),
                'event_name' => (string) ($payload['event_name'] ?? ''),
                'source' => (string) ($payload['source'] ?? ''),
                'timestamp' => (string) ($payload['timestamp'] ?? ''),
                'entity' => [
                    'type' => (string) ($payload['entity']['type'] ?? ''),
                    'id' => (int) ($payload['entity']['id'] ?? 0),
                ],
                'changes' => is_array($payload['changes'] ?? null) ? $payload['changes'] : [],
                'delivery' => $payload['delivery'],
            ];
        }

        if ($mode === 'full') {
            $payload['flow'] = [
                'id' => (int) ($flow['id'] ?? 0),
                'name' => (string) ($flow['name'] ?? ''),
                'trigger_key' => (string) ($flow['trigger_key'] ?? ''),
                'payload_mode' => $mode,
            ];
            $payload['wordpress'] = [
                'locale' => get_locale(),
                'timezone' => wp_timezone_string(),
                'is_multisite' => is_multisite(),
                'current_user_id' => get_current_user_id(),
                'plugin_version' => defined('TECHNOMANCER_WP_VERSION') ? TECHNOMANCER_WP_VERSION : '',
            ];
        }

        return $payload;
    }

    private function normalizeDelivery(array $flow, array $payload): array
    {
        $settings = is_array($flow['settings'] ?? null) ? $flow['settings'] : [];
        $currentDelivery = is_array($payload['delivery'] ?? null) ? $payload['delivery'] : [];
        $maxAttempts = max(1, min(10, (int) ($settings['max_attempts'] ?? ($currentDelivery['max_attempts'] ?? 3))));

        $payload['delivery'] = array_merge([
            'attempt' => max(1, (int) ($currentDelivery['attempt'] ?? 1)),
            'max_attempts' => $maxAttempts,
            'queued_at' => (string) ($currentDelivery['queued_at'] ?? gmdate('c')),
        ], $currentDelivery);
        $payload['delivery']['max_attempts'] = $maxAttempts;

        return $payload;
    }
}

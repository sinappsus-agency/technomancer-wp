<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Flows;

final class Dispatcher
{
    private FlowRepository $flows;

    private Logger $logger;

    private PayloadFormatter $payloadFormatter;

    public function __construct(FlowRepository $flows, Logger $logger)
    {
        $this->flows = $flows;
        $this->logger = $logger;
        $this->payloadFormatter = new PayloadFormatter();
    }

    public function dispatch(array $flow, array $payload): void
    {
        $payload = $this->payloadFormatter->format($flow, $payload);

        wp_schedule_single_event(time() + 1, 'sinappsus_n8n_process_delivery', [$flow['id'], $payload]);
    }

    public function processDelivery(int $flowId, array $payload): void
    {
        $flow = $this->flows->find($flowId);

        if (! $flow || empty($flow['is_enabled']) || empty($flow['webhook_url'])) {
            return;
        }

        $payload = $this->payloadFormatter->format($flow, $payload);

        $body = wp_json_encode($payload);
        $signature = hash_hmac('sha256', $body ?: '', (string) ($flow['secret_key'] ?? ''));
        $response = wp_remote_post($flow['webhook_url'], [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-SINAPPSUS-Event' => (string) ($payload['event_name'] ?? ''),
                'X-SINAPPSUS-Signature' => $signature,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            $this->logger->log([
                'flow_id' => $flowId,
                'event_key' => (string) ($payload['event_name'] ?? ''),
                'entity_type' => (string) ($payload['entity']['type'] ?? ''),
                'entity_id' => (int) ($payload['entity']['id'] ?? 0),
                'status' => 'failed',
                'message' => $response->get_error_message(),
                'payload' => $payload,
            ]);

            $this->retryOrDeadLetter($flowId, $payload, $response->get_error_message());

            return;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $this->logger->log([
            'flow_id' => $flowId,
            'event_key' => (string) ($payload['event_name'] ?? ''),
            'entity_type' => (string) ($payload['entity']['type'] ?? ''),
            'entity_id' => (int) ($payload['entity']['id'] ?? 0),
            'status' => $code >= 200 && $code < 300 ? 'sent' : 'failed',
            'message' => wp_remote_retrieve_body($response),
            'payload' => $payload,
            'response_code' => $code,
        ]);

        if ($code < 200 || $code >= 300) {
            $this->retryOrDeadLetter($flowId, $payload, 'HTTP ' . $code);
        }
    }

    private function retryOrDeadLetter(int $flowId, array $payload, string $reason): void
    {
        $attempt = (int) ($payload['delivery']['attempt'] ?? 1);
        $maxAttempts = (int) ($payload['delivery']['max_attempts'] ?? 3);

        if ($attempt < $maxAttempts) {
            $payload['delivery']['attempt'] = $attempt + 1;
            $delay = min(300, $attempt * 30);
            wp_schedule_single_event(time() + $delay, 'sinappsus_n8n_process_delivery', [$flowId, $payload]);

            $this->logger->log([
                'flow_id' => $flowId,
                'event_key' => (string) ($payload['event_name'] ?? ''),
                'entity_type' => (string) ($payload['entity']['type'] ?? ''),
                'entity_id' => (int) ($payload['entity']['id'] ?? 0),
                'status' => 'retry_scheduled',
                'message' => ['reason' => $reason, 'attempt' => $attempt + 1],
                'payload' => $payload,
            ]);

            return;
        }

        $this->logger->log([
            'flow_id' => $flowId,
            'event_key' => (string) ($payload['event_name'] ?? ''),
            'entity_type' => (string) ($payload['entity']['type'] ?? ''),
            'entity_id' => (int) ($payload['entity']['id'] ?? 0),
            'status' => 'dead_letter',
            'message' => ['reason' => $reason, 'attempts' => $attempt],
            'payload' => $payload,
        ]);
    }
}
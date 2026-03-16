<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Notifuse;

use Sinappsus\N8nConnector\Core\Settings;
use Sinappsus\N8nConnector\Flows\Logger;

final class Client
{
    private Logger $logger;

    private ?array $listsCache = null;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function testConnection(): array
    {
        $settings = Settings::get('notifuse', []);
        $baseUrl = untrailingslashit((string) ($settings['base_url'] ?? ''));
        $apiKey = (string) ($settings['api_key'] ?? '');

        if ($baseUrl === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Notifuse base URL and API key are required.'];
        }

        $response = wp_remote_get($baseUrl . '/api', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return [
            'success' => $code >= 200 && $code < 300,
            'message' => $code >= 200 && $code < 300 ? 'Notifuse connection succeeded.' : 'Notifuse connection failed with HTTP ' . $code . '.',
        ];
    }

    public function subscribeUserById(int $userId, string $source): void
    {
        $settings = Settings::get('notifuse', []);
        if (empty($settings['signup_on_registration']) || $userId <= 0) {
            return;
        }

        $user = get_userdata($userId);
        if (! $user) {
            return;
        }

        $listIds = $this->getUserListIds($userId);

        $this->upsertContact([
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'source' => $source,
            'wp_user_id' => $user->ID,
        ], $listIds);
    }

    public function subscribeOrderById(int $orderId, string $source): void
    {
        $settings = Settings::get('notifuse', []);
        if (empty($settings['signup_on_checkout']) || ! function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($orderId);
        if (! $order) {
            return;
        }

        $this->upsertContact([
            'email' => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'source' => $source,
            'wp_order_id' => $order->get_id(),
        ]);
    }

    public function getLists(bool $forceRefresh = false): array
    {
        if ($this->listsCache !== null && ! $forceRefresh) {
            return $this->listsCache;
        }

        $response = $this->request('GET', '/api/lists');
        if (! $response['success']) {
            return [];
        }

        $body = is_array($response['body']) ? $response['body'] : [];
        $lists = isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
        $this->listsCache = is_array($lists) ? $lists : [];

        return $this->listsCache;
    }

    public function updateUserLists(int $userId, array $listIds): void
    {
        update_user_meta($userId, 'snc_notifuse_list_ids', array_values(array_filter(array_map('sanitize_text_field', $listIds))));
        $user = get_userdata($userId);

        if (! $user) {
            return;
        }

        $this->upsertContact([
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'wp_user_id' => $user->ID,
            'source' => 'admin_profile',
        ], $listIds);
    }

    public function subscribeEmail(string $email, array $attributes = [], array $listIds = []): array
    {
        return $this->upsertContact(array_merge($attributes, ['email' => $email]), $listIds, true);
    }

    public function unsubscribeEmail(string $email): array
    {
        $settings = Settings::get('notifuse', []);
        if (empty($settings['allow_unsubscribe'])) {
            return ['success' => false, 'message' => 'Unsubscribe is disabled.'];
        }

        $payload = ['email' => $email, 'unsubscribed' => true];

        return $this->request('POST', '/api/contacts', $payload);
    }

    private function upsertContact(array $contact, array $listIds = [], bool $returnResponse = false): ?array
    {
        $settings = Settings::get('notifuse', []);
        $baseUrl = untrailingslashit((string) ($settings['base_url'] ?? ''));
        $apiKey = (string) ($settings['api_key'] ?? '');
        $listId = (string) ($settings['default_list_id'] ?? '');

        if ($baseUrl === '' || $apiKey === '' || empty($contact['email'])) {
            return $returnResponse ? ['success' => false, 'message' => 'Notifuse is not configured.'] : null;
        }

        $payload = [
            'email' => $contact['email'],
            'external_id' => isset($contact['wp_user_id']) ? 'wp-user-' . (string) $contact['wp_user_id'] : (isset($contact['wp_order_id']) ? 'wc-order-' . (string) $contact['wp_order_id'] : ''),
            'first_name' => $contact['first_name'] ?? '',
            'last_name' => $contact['last_name'] ?? '',
            'listIds' => ! empty($listIds) ? array_values($listIds) : ($listId !== '' ? [$listId] : []),
            'custom_json_1' => $contact,
        ];

        $result = $this->request('POST', '/api/contacts', $payload);

        $this->logger->log([
            'event_key' => 'notifuse.contact.upsert',
            'entity_type' => 'contact',
            'status' => $result['success'] ? 'integration_sent' : 'integration_failed',
            'message' => $result['message'],
            'response_code' => $result['code'],
            'payload' => $payload,
        ]);

        return $returnResponse ? $result : null;
    }

    private function getUserListIds(int $userId): array
    {
        $stored = get_user_meta($userId, 'snc_notifuse_list_ids', true);

        if (is_array($stored) && ! empty($stored)) {
            return array_values(array_filter(array_map('sanitize_text_field', $stored)));
        }

        $settings = Settings::get('notifuse', []);
        $defaultList = (string) ($settings['default_list_id'] ?? '');

        return $defaultList !== '' ? [$defaultList] : [];
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $settings = Settings::get('notifuse', []);
        $baseUrl = untrailingslashit((string) ($settings['base_url'] ?? ''));
        $apiKey = (string) ($settings['api_key'] ?? '');

        if ($baseUrl === '' || $apiKey === '') {
            return ['success' => false, 'message' => 'Notifuse base URL and API key are required.', 'code' => 0, 'body' => []];
        }

        $args = [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'method' => strtoupper($method),
        ];

        if (! empty($payload)) {
            $args['body'] = wp_json_encode($payload);
        }

        $response = wp_remote_request($baseUrl . $path, $args);
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
<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Events;

final class SamplePayloadFactory
{
    private PayloadBuilder $payloadBuilder;

    public function __construct()
    {
        $this->payloadBuilder = new PayloadBuilder();
    }

    public function build(string $eventName, int $preferredEntityId = 0): array
    {
        $definitions = EventRegistry::definitions();
        $definition = $definitions[$eventName] ?? [
            'entity_type' => 'generic',
        ];

        $entityType = (string) ($definition['entity_type'] ?? 'generic');
        $entityId = $this->resolveEntityId($entityType, $eventName, $preferredEntityId);
        $snapshot = $this->buildSnapshot($entityType, $eventName, $entityId);
        $changes = $this->sampleChanges($eventName, $entityType);

        if ($entityId <= 0 && isset($snapshot['id']) && is_numeric($snapshot['id'])) {
            $entityId = (int) $snapshot['id'];
        }

        return $this->payloadBuilder->build($eventName, $entityType, $entityId, $snapshot, $changes);
    }

    private function resolveEntityId(string $entityType, string $eventName, int $preferredEntityId): int
    {
        if ($preferredEntityId > 0) {
            return $preferredEntityId;
        }

        switch ($entityType) {
            case 'user':
                $users = get_users(['number' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => ['ID']]);

                return isset($users[0]->ID) ? (int) $users[0]->ID : 0;

            case 'comment':
                $comments = get_comments(['number' => 1, 'orderby' => 'comment_ID', 'order' => 'DESC']);

                return isset($comments[0]->comment_ID) ? (int) $comments[0]->comment_ID : 0;

            case 'order':
                if (function_exists('wc_get_orders')) {
                    $orders = wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']);

                    return isset($orders[0]) ? (int) $orders[0]->get_id() : 0;
                }

                return 0;

            case 'product':
                $products = get_posts(['post_type' => 'product', 'posts_per_page' => 1, 'post_status' => 'any', 'orderby' => 'ID', 'order' => 'DESC']);

                return isset($products[0]->ID) ? (int) $products[0]->ID : 0;

            case 'attachment':
                $attachments = get_posts(['post_type' => 'attachment', 'posts_per_page' => 1, 'post_status' => 'inherit', 'orderby' => 'ID', 'order' => 'DESC']);

                return isset($attachments[0]->ID) ? (int) $attachments[0]->ID : 0;

            case 'post':
                $postType = str_contains($eventName, 'page') ? 'page' : 'post';
                $posts = get_posts(['post_type' => $postType, 'posts_per_page' => 1, 'post_status' => 'any', 'orderby' => 'ID', 'order' => 'DESC']);

                return isset($posts[0]->ID) ? (int) $posts[0]->ID : 0;

            default:
                return 0;
        }
    }

    private function buildSnapshot(string $entityType, string $eventName, int $entityId): array
    {
        switch ($entityType) {
            case 'user':
                return $entityId > 0 ? $this->payloadBuilder->userSnapshot($entityId) : [
                    'ID' => 0,
                    'user_login' => 'sample-user',
                    'user_email' => 'sample@example.com',
                    'display_name' => 'Sample User',
                    'roles' => ['subscriber'],
                ];

            case 'comment':
                return $entityId > 0 ? $this->payloadBuilder->commentSnapshot($entityId) : [
                    'comment_ID' => 0,
                    'comment_post_ID' => 0,
                    'comment_approved' => '1',
                    'comment_author' => 'Sample Commenter',
                    'comment_author_email' => 'commenter@example.com',
                    'user_id' => 0,
                ];

            case 'order':
                return $entityId > 0 ? $this->payloadBuilder->orderSnapshot($entityId) : [
                    'id' => 0,
                    'number' => 'SAMPLE-1001',
                    'status' => 'processing',
                    'currency' => get_woocommerce_currency(),
                    'total' => '149.99',
                    'email' => 'customer@example.com',
                    'customer_id' => 0,
                ];

            case 'product':
            case 'post':
            case 'attachment':
                return $entityId > 0 ? $this->payloadBuilder->postSnapshot($entityId) : [
                    'ID' => 0,
                    'post_type' => $entityType === 'product' ? 'product' : ($entityType === 'attachment' ? 'attachment' : 'post'),
                    'post_status' => 'publish',
                    'post_title' => 'Sample ' . ucfirst($entityType),
                    'post_name' => 'sample-' . $entityType,
                    'post_author' => 1,
                    'modified' => gmdate('Y-m-d H:i:s'),
                ];

            case 'cart':
            case 'checkout':
                $snapshot = $this->payloadBuilder->currentCartSnapshot();

                return ! empty($snapshot) ? $snapshot : [
                    'items' => [[
                        'product_id' => 101,
                        'variation_id' => 0,
                        'quantity' => 2,
                    ]],
                    'subtotal' => '99.99',
                    'total' => '109.99',
                    'user_id' => get_current_user_id(),
                ];

            case 'coupon':
                return [
                    'code' => 'WELCOME10',
                    'source' => 'sample',
                ];

            default:
                return [
                    'id' => 0,
                    'label' => 'Sample payload',
                ];
        }
    }

    private function sampleChanges(string $eventName, string $entityType): array
    {
        if ($eventName === 'wordpress.user.role_changed') {
            return ['old_roles' => ['subscriber'], 'new_role' => 'customer'];
        }

        if ($eventName === 'wordpress.comment.status_changed') {
            return ['old_status' => 'hold', 'new_status' => 'approved'];
        }

        if ($eventName === 'woocommerce.order.status_changed') {
            return ['old_status' => 'pending', 'new_status' => 'processing'];
        }

        if ($eventName === 'woocommerce.order.refunded') {
            return ['refund_id' => 1001, 'refunded_total' => '-25.00'];
        }

        if ($eventName === 'woocommerce.coupon.applied' || $eventName === 'woocommerce.coupon.removed') {
            return ['coupon_code' => 'WELCOME10'];
        }

        if ($entityType === 'cart') {
            return ['reason' => 'cart_activity'];
        }

        return [];
    }
}
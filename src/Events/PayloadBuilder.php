<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Events;

final class PayloadBuilder
{
    public function build(string $eventName, string $entityType, int $entityId, array $snapshot, array $changes = []): array
    {
        return [
            'event_id' => wp_generate_uuid4(),
            'event_name' => $eventName,
            'source' => str_starts_with($eventName, 'woocommerce.') ? 'woocommerce' : 'wordpress',
            'timestamp' => gmdate('c'),
            'site' => [
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
            ],
            'entity' => [
                'type' => $entityType,
                'id' => $entityId,
                'snapshot' => $snapshot,
            ],
            'changes' => $changes,
        ];
    }

    public function userSnapshot(int $userId): array
    {
        $user = get_userdata($userId);

        if (! $user) {
            return [];
        }

        return [
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
        ];
    }

    public function postSnapshot(int $postId): array
    {
        $post = get_post($postId);

        if (! $post) {
            return [];
        }

        return [
            'ID' => $post->ID,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'post_title' => $post->post_title,
            'post_name' => $post->post_name,
            'post_author' => $post->post_author,
            'modified' => $post->post_modified_gmt,
        ];
    }

    public function commentSnapshot(int $commentId): array
    {
        $comment = get_comment($commentId);

        if (! $comment) {
            return [];
        }

        return [
            'comment_ID' => (int) $comment->comment_ID,
            'comment_post_ID' => (int) $comment->comment_post_ID,
            'comment_approved' => $comment->comment_approved,
            'comment_author' => $comment->comment_author,
            'comment_author_email' => $comment->comment_author_email,
            'user_id' => (int) $comment->user_id,
        ];
    }

    public function orderSnapshot(int $orderId): array
    {
        if (! function_exists('wc_get_order')) {
            return [];
        }

        $order = wc_get_order($orderId);

        if (! $order) {
            return [];
        }

        return [
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'total' => $order->get_total(),
            'email' => $order->get_billing_email(),
            'customer_id' => $order->get_customer_id(),
        ];
    }

    public function currentCartSnapshot(): array
    {
        if (! function_exists('WC') || ! WC()->cart) {
            return [];
        }

        $items = [];
        foreach (WC()->cart->get_cart() as $item) {
            $items[] = [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'variation_id' => (int) ($item['variation_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        }

        return [
            'items' => $items,
            'subtotal' => WC()->cart->get_subtotal(),
            'total' => WC()->cart->get_total('edit'),
            'user_id' => get_current_user_id(),
        ];
    }
}
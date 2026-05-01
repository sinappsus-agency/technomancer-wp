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
                    'ID'              => 0,
                    'user_login'      => 'sample-user',
                    'user_email'      => 'sample@example.com',
                    'user_url'        => '',
                    'user_registered' => gmdate('Y-m-d H:i:s'),
                    'user_status'     => 0,
                    'display_name'    => 'Sample User',
                    'first_name'      => 'Sample',
                    'last_name'       => 'User',
                    'nickname'        => 'sample-user',
                    'description'     => '',
                    'locale'          => '',
                    'avatar_url'      => '',
                    'roles'           => ['subscriber'],
                ];

            case 'comment':
                return $entityId > 0 ? $this->payloadBuilder->commentSnapshot($entityId) : [
                    'comment_ID'           => 0,
                    'comment_post_ID'      => 0,
                    'post_title'           => 'Sample Post',
                    'comment_approved'     => '1',
                    'comment_type'         => 'comment',
                    'comment_author'       => 'Sample Commenter',
                    'comment_author_email' => 'commenter@example.com',
                    'comment_author_url'   => '',
                    'comment_content'      => 'This is a sample comment.',
                    'comment_date_gmt'     => gmdate('Y-m-d H:i:s'),
                    'comment_parent'       => 0,
                    'comment_karma'        => 0,
                    'user_id'              => 0,
                ];

            case 'order':
                return $entityId > 0 ? $this->payloadBuilder->orderSnapshot($entityId) : [
                    'id'                   => 0,
                    'number'               => 'SAMPLE-1001',
                    'status'               => 'processing',
                    'currency'             => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                    'subtotal'             => '149.99',
                    'discount_total'       => '0.00',
                    'shipping_total'       => '9.99',
                    'tax_total'            => '15.00',
                    'total'                => '174.98',
                    'payment_method'       => 'stripe',
                    'payment_method_title' => 'Credit Card',
                    'transaction_id'       => '',
                    'date_created'         => gmdate('c'),
                    'date_modified'        => gmdate('c'),
                    'customer_id'          => 0,
                    'customer_note'        => '',
                    'email'                => 'customer@example.com',
                    'billing' => [
                        'first_name' => 'Jane',
                        'last_name'  => 'Doe',
                        'company'    => '',
                        'address_1'  => '123 Main St',
                        'address_2'  => '',
                        'city'       => 'Springfield',
                        'state'      => 'IL',
                        'postcode'   => '62701',
                        'country'    => 'US',
                        'phone'      => '555-0100',
                        'email'      => 'customer@example.com',
                    ],
                    'shipping' => [
                        'first_name' => 'Jane',
                        'last_name'  => 'Doe',
                        'company'    => '',
                        'address_1'  => '123 Main St',
                        'address_2'  => '',
                        'city'       => 'Springfield',
                        'state'      => 'IL',
                        'postcode'   => '62701',
                        'country'    => 'US',
                    ],
                    'items' => [[
                        'item_id'      => 1,
                        'product_id'   => 101,
                        'variation_id' => 0,
                        'name'         => 'Sample Product',
                        'quantity'     => 2,
                        'subtotal'     => '149.99',
                        'total'        => '149.99',
                        'tax'          => '15.00',
                        'sku'          => 'SAMPLE-SKU',
                    ]],
                    'item_count' => 1,
                    'coupons'    => [],
                ];

            case 'product':
                return $entityId > 0 ? $this->payloadBuilder->productSnapshot($entityId) : [
                    'id'                => 0,
                    'name'              => 'Sample Product',
                    'slug'              => 'sample-product',
                    'status'            => 'publish',
                    'type'              => 'simple',
                    'sku'               => 'SAMPLE-SKU',
                    'price'             => '29.99',
                    'regular_price'     => '29.99',
                    'sale_price'        => '',
                    'on_sale'           => false,
                    'tax_status'        => 'taxable',
                    'tax_class'         => '',
                    'stock_status'      => 'instock',
                    'stock_quantity'    => null,
                    'manage_stock'      => false,
                    'weight'            => '',
                    'description'       => 'A sample product description.',
                    'short_description' => 'Short description.',
                    'url'               => home_url('/sample-product'),
                    'image_url'         => null,
                    'categories'        => [],
                    'tags'              => [],
                    'date_created'      => gmdate('c'),
                    'date_modified'     => gmdate('c'),
                ];

            case 'post':
            case 'attachment':
                return $entityId > 0 ? $this->payloadBuilder->postSnapshot($entityId) : [
                    'ID'                  => 0,
                    'post_type'           => $entityType === 'attachment' ? 'attachment' : 'post',
                    'post_status'         => 'publish',
                    'post_title'          => 'Sample ' . ucfirst($entityType),
                    'post_name'           => 'sample-' . $entityType,
                    'post_author'         => 1,
                    'post_content'        => 'Sample post content.',
                    'post_excerpt'        => '',
                    'post_date_gmt'       => gmdate('Y-m-d H:i:s'),
                    'modified'            => gmdate('Y-m-d H:i:s'),
                    'permalink'           => home_url('/sample-' . $entityType),
                    'comment_count'       => 0,
                    'menu_order'          => 0,
                    'post_parent'         => 0,
                    'featured_image_url'  => null,
                    'categories'          => [],
                    'tags'                => [],
                ];

            case 'cart':
            case 'checkout':
                $snapshot = $this->payloadBuilder->currentCartSnapshot();

                return ! empty($snapshot) ? $snapshot : [
                    'items' => [[
                        'product_id'   => 101,
                        'variation_id' => 0,
                        'name'         => 'Sample Product',
                        'sku'          => 'SAMPLE-SKU',
                        'quantity'     => 2,
                        'price'        => 49.99,
                        'line_total'   => 99.98,
                    ]],
                    'item_count'     => 2,
                    'subtotal'       => '99.98',
                    'discount_total' => '0.00',
                    'shipping_total' => '9.99',
                    'tax_total'      => '10.00',
                    'total'          => '119.97',
                    'coupon_codes'   => [],
                    'user_id'        => get_current_user_id(),
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
        if ($eventName === 'wordpress.user.updated') {
            return [
                'user_email' => ['from' => 'old@example.com', 'to' => 'new@example.com'],
            ];
        }

        if ($eventName === 'wordpress.post.updated') {
            return [
                'post_title' => ['from' => 'Old Title', 'to' => 'New Title'],
            ];
        }

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
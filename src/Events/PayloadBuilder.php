<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Events;

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

        $snapshot = [
            'ID'              => $user->ID,
            'user_login'      => $user->user_login,
            'user_email'      => $user->user_email,
            'user_url'        => $user->user_url,
            'user_registered' => $user->user_registered,
            'user_status'     => (int) $user->user_status,
            'display_name'    => $user->display_name,
            'first_name'      => (string) get_user_meta($userId, 'first_name', true),
            'last_name'       => (string) get_user_meta($userId, 'last_name', true),
            'nickname'        => (string) get_user_meta($userId, 'nickname', true),
            'description'     => (string) get_user_meta($userId, 'description', true),
            'locale'          => (string) get_user_meta($userId, 'locale', true),
            'avatar_url'      => get_avatar_url($userId, ['size' => 96]),
            'roles'           => $user->roles,
        ];

        if (class_exists('WooCommerce')) {
            $billing_fields  = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email'];
            $shipping_fields = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];

            $billing = [];
            foreach ($billing_fields as $field) {
                $billing[$field] = (string) get_user_meta($userId, 'billing_' . $field, true);
            }

            $shipping = [];
            foreach ($shipping_fields as $field) {
                $shipping[$field] = (string) get_user_meta($userId, 'shipping_' . $field, true);
            }

            $snapshot['billing']  = $billing;
            $snapshot['shipping'] = $shipping;
        }

        return $snapshot;
    }

    /**
     * Return an array of field-level changes between a WP_User (before) and a fresh snapshot (after).
     */
    public function userChanges(\WP_User $oldUser, array $newSnapshot): array
    {
        $changes = [];
        $trackFields = ['user_email', 'user_url', 'display_name', 'user_status', 'first_name', 'last_name'];

        foreach ($trackFields as $field) {
            $oldVal = (string) ($oldUser->data->$field ?? $oldUser->get($field) ?? '');
            $newVal = (string) ($newSnapshot[$field] ?? '');
            if ($oldVal !== $newVal) {
                $changes[$field] = ['from' => $oldVal, 'to' => $newVal];
            }
        }

        if ($oldUser->roles !== ($newSnapshot['roles'] ?? [])) {
            $changes['roles'] = ['from' => $oldUser->roles, 'to' => $newSnapshot['roles'] ?? []];
        }

        return $changes;
    }

    public function postSnapshot(int $postId): array
    {
        $post = get_post($postId);

        if (! $post) {
            return [];
        }

        $snapshot = [
            'ID'            => $post->ID,
            'post_type'     => $post->post_type,
            'post_status'   => $post->post_status,
            'post_title'    => $post->post_title,
            'post_name'     => $post->post_name,
            'post_author'   => (int) $post->post_author,
            'post_content'  => $post->post_content,
            'post_excerpt'  => $post->post_excerpt,
            'post_date_gmt' => $post->post_date_gmt,
            'modified'      => $post->post_modified_gmt,
            'permalink'     => get_permalink($postId),
            'comment_count' => (int) $post->comment_count,
            'menu_order'    => (int) $post->menu_order,
            'post_parent'   => (int) $post->post_parent,
        ];

        if ($post->post_type === 'attachment') {
            $snapshot['file_url']   = wp_get_attachment_url($postId) ?: null;
            $snapshot['mime_type']  = $post->post_mime_type;
        } else {
            $snapshot['featured_image_url'] = get_the_post_thumbnail_url($postId, 'full') ?: null;

            $categories = wp_get_post_categories($postId, ['fields' => 'all']);
            if (is_array($categories) && ! is_wp_error($categories)) {
                $snapshot['categories'] = array_map(
                    fn ($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug],
                    $categories
                );
            }

            $tags = wp_get_post_tags($postId, ['fields' => 'all']);
            if (is_array($tags) && ! is_wp_error($tags)) {
                $snapshot['tags'] = array_map(
                    fn ($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug],
                    $tags
                );
            }
        }

        $rawMeta = get_post_meta($postId);
        if (is_array($rawMeta)) {
            $meta = [];
            foreach ($rawMeta as $key => $values) {
                if (str_starts_with($key, '_')) {
                    continue;
                }
                $meta[$key] = count($values) === 1 ? $values[0] : $values;
            }
            if (! empty($meta)) {
                $snapshot['meta'] = $meta;
            }
        }

        return $snapshot;
    }

    public function commentSnapshot(int $commentId): array
    {
        $comment = get_comment($commentId);

        if (! $comment) {
            return [];
        }

        return [
            'comment_ID'           => (int) $comment->comment_ID,
            'comment_post_ID'      => (int) $comment->comment_post_ID,
            'post_title'           => get_the_title((int) $comment->comment_post_ID),
            'comment_approved'     => $comment->comment_approved,
            'comment_type'         => $comment->comment_type,
            'comment_author'       => $comment->comment_author,
            'comment_author_email' => $comment->comment_author_email,
            'comment_author_url'   => $comment->comment_author_url,
            'comment_content'      => $comment->comment_content,
            'comment_date_gmt'     => $comment->comment_date_gmt,
            'comment_parent'       => (int) $comment->comment_parent,
            'comment_karma'        => (int) $comment->comment_karma,
            'user_id'              => (int) $comment->user_id,
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

        $items = [];
        foreach ($order->get_items() as $item) {
            $product   = $item->get_product();
            $items[] = [
                'item_id'       => $item->get_id(),
                'product_id'    => $item->get_product_id(),
                'variation_id'  => $item->get_variation_id(),
                'name'          => $item->get_name(),
                'quantity'      => $item->get_quantity(),
                'subtotal'      => $item->get_subtotal(),
                'total'         => $item->get_total(),
                'tax'           => $item->get_total_tax(),
                'sku'           => $product instanceof \WC_Product ? $product->get_sku() : '',
            ];
        }

        return [
            'id'                    => $order->get_id(),
            'number'                => $order->get_order_number(),
            'status'                => $order->get_status(),
            'currency'              => $order->get_currency(),
            'subtotal'              => $order->get_subtotal(),
            'discount_total'        => $order->get_discount_total(),
            'shipping_total'        => $order->get_shipping_total(),
            'tax_total'             => $order->get_total_tax(),
            'total'                 => $order->get_total(),
            'payment_method'        => $order->get_payment_method(),
            'payment_method_title'  => $order->get_payment_method_title(),
            'transaction_id'        => $order->get_transaction_id(),
            'date_created'          => $order->get_date_created() ? $order->get_date_created()->format('c') : null,
            'date_modified'         => $order->get_date_modified() ? $order->get_date_modified()->format('c') : null,
            'customer_id'           => $order->get_customer_id(),
            'customer_note'         => $order->get_customer_note(),
            'email'                 => $order->get_billing_email(),
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name'  => $order->get_billing_last_name(),
                'company'    => $order->get_billing_company(),
                'address_1'  => $order->get_billing_address_1(),
                'address_2'  => $order->get_billing_address_2(),
                'city'       => $order->get_billing_city(),
                'state'      => $order->get_billing_state(),
                'postcode'   => $order->get_billing_postcode(),
                'country'    => $order->get_billing_country(),
                'phone'      => $order->get_billing_phone(),
                'email'      => $order->get_billing_email(),
            ],
            'shipping' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name'  => $order->get_shipping_last_name(),
                'company'    => $order->get_shipping_company(),
                'address_1'  => $order->get_shipping_address_1(),
                'address_2'  => $order->get_shipping_address_2(),
                'city'       => $order->get_shipping_city(),
                'state'      => $order->get_shipping_state(),
                'postcode'   => $order->get_shipping_postcode(),
                'country'    => $order->get_shipping_country(),
            ],
            'items'      => $items,
            'item_count' => count($items),
            'coupons'    => array_values($order->get_coupon_codes()),
        ];
    }

    public function currentCartSnapshot(): array
    {
        if (! function_exists('WC') || ! WC()->cart) {
            return [];
        }

        $items = [];
        foreach (WC()->cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            $items[] = [
                'product_id'   => (int) ($item['product_id'] ?? 0),
                'variation_id' => (int) ($item['variation_id'] ?? 0),
                'name'         => $product instanceof \WC_Product ? $product->get_name() : '',
                'sku'          => $product instanceof \WC_Product ? $product->get_sku() : '',
                'quantity'     => (int) ($item['quantity'] ?? 0),
                'price'        => $product instanceof \WC_Product ? (float) $product->get_price() : 0.0,
                'line_total'   => (float) ($item['line_total'] ?? 0),
            ];
        }

        return [
            'items'          => $items,
            'item_count'     => WC()->cart->get_cart_contents_count(),
            'subtotal'       => WC()->cart->get_subtotal(),
            'discount_total' => WC()->cart->get_discount_total(),
            'shipping_total' => WC()->cart->get_shipping_total(),
            'tax_total'      => WC()->cart->get_total_tax(),
            'total'          => WC()->cart->get_total('edit'),
            'coupon_codes'   => WC()->cart->get_applied_coupons(),
            'user_id'        => get_current_user_id(),
        ];
    }

    public function productSnapshot(int $productId): array
    {
        if (! function_exists('wc_get_product')) {
            return $this->postSnapshot($productId);
        }

        $product = wc_get_product($productId);

        if (! $product) {
            return $this->postSnapshot($productId);
        }

        $snapshot = [
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'status'            => $product->get_status(),
            'type'              => $product->get_type(),
            'sku'               => $product->get_sku(),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'on_sale'           => $product->is_on_sale(),
            'tax_status'        => $product->get_tax_status(),
            'tax_class'         => $product->get_tax_class(),
            'stock_status'      => $product->get_stock_status(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'manage_stock'      => $product->managing_stock(),
            'weight'            => $product->get_weight(),
            'description'       => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'url'               => $product->get_permalink(),
            'image_url'         => wp_get_attachment_url($product->get_image_id()) ?: null,
            'date_created'      => $product->get_date_created() ? $product->get_date_created()->format('c') : null,
            'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->format('c') : null,
        ];

        $cats = wp_get_post_terms($productId, 'product_cat');
        $snapshot['categories'] = (is_array($cats) && ! is_wp_error($cats))
            ? array_map(fn ($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug], $cats)
            : [];

        $productTags = wp_get_post_terms($productId, 'product_tag');
        $snapshot['tags'] = (is_array($productTags) && ! is_wp_error($productTags))
            ? array_map(fn ($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug], $productTags)
            : [];

        if ($product->is_type('variable')) {
            $variations = [];
            foreach ($product->get_children() as $variationId) {
                $variation = wc_get_product($variationId);
                if ($variation) {
                    $variations[] = [
                        'id'           => $variation->get_id(),
                        'sku'          => $variation->get_sku(),
                        'price'        => $variation->get_price(),
                        'stock_status' => $variation->get_stock_status(),
                        'attributes'   => $variation->get_variation_attributes(),
                    ];
                }
            }
            $snapshot['variations'] = $variations;
        }

        return $snapshot;
    }
}

<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Events;

final class EventRegistry
{
    public static function definitions(): array
    {
        $events = [
            'wordpress.user.created' => [
                'group' => 'WordPress Core',
                'hook' => 'user_register',
                'entity_type' => 'user',
                'label' => 'User Created',
                'payload_notes' => 'Fires when a new user is created.',
            ],
            'wordpress.user.updated' => [
                'group' => 'WordPress Core',
                'hook' => 'profile_update',
                'entity_type' => 'user',
                'label' => 'User Updated',
                'payload_notes' => 'Includes updated user snapshot.',
            ],
            'wordpress.user.deleted' => [
                'group' => 'WordPress Core',
                'hook' => 'delete_user',
                'entity_type' => 'user',
                'label' => 'User Deleted',
                'payload_notes' => 'Fires before a user is deleted.',
            ],
            'wordpress.user.role_changed' => [
                'group' => 'WordPress Core',
                'hook' => 'set_user_role',
                'entity_type' => 'user',
                'label' => 'User Role Changed',
                'payload_notes' => 'Includes old roles and new role in changes.',
            ],
            'wordpress.user.login' => [
                'group' => 'WordPress Core',
                'hook' => 'wp_login',
                'entity_type' => 'user',
                'label' => 'User Login',
                'payload_notes' => 'Fires when a user logs in.',
            ],
            'wordpress.user.logout' => [
                'group' => 'WordPress Core',
                'hook' => 'wp_logout',
                'entity_type' => 'user',
                'label' => 'User Logout',
                'payload_notes' => 'Fires when the current user logs out.',
            ],
            'wordpress.user.password_reset' => [
                'group' => 'WordPress Core',
                'hook' => 'after_password_reset',
                'entity_type' => 'user',
                'label' => 'Password Reset',
                'payload_notes' => 'Fires after a user password reset completes.',
            ],
            'wordpress.comment.created' => [
                'group' => 'WordPress Core',
                'hook' => 'comment_post',
                'entity_type' => 'comment',
                'label' => 'Comment Created',
                'payload_notes' => 'Fires after a comment is stored.',
            ],
            'wordpress.comment.updated' => [
                'group' => 'WordPress Core',
                'hook' => 'edit_comment',
                'entity_type' => 'comment',
                'label' => 'Comment Updated',
                'payload_notes' => 'Fires after comment edits are saved.',
            ],
            'wordpress.comment.deleted' => [
                'group' => 'WordPress Core',
                'hook' => 'delete_comment',
                'entity_type' => 'comment',
                'label' => 'Comment Deleted',
                'payload_notes' => 'Fires before a comment is deleted.',
            ],
            'wordpress.comment.status_changed' => [
                'group' => 'WordPress Core',
                'hook' => 'transition_comment_status',
                'entity_type' => 'comment',
                'label' => 'Comment Status Changed',
                'payload_notes' => 'Includes old and new status in metadata.',
            ],
            'wordpress.post.published' => [
                'group' => 'WordPress Core',
                'hook' => 'publish_post',
                'entity_type' => 'post',
                'label' => 'Post Published',
                'payload_notes' => 'Fires when a post is published.',
            ],
            'wordpress.post.created' => [
                'group' => 'WordPress Core',
                'hook' => 'wp_after_insert_post',
                'entity_type' => 'post',
                'label' => 'Post Created',
                'payload_notes' => 'Fires on first insert for post types.',
            ],
            'wordpress.post.updated' => [
                'group' => 'WordPress Core',
                'hook' => 'post_updated',
                'entity_type' => 'post',
                'label' => 'Post Updated',
                'payload_notes' => 'Fires when a post is updated.',
            ],
            'wordpress.post.deleted' => [
                'group' => 'WordPress Core',
                'hook' => 'before_delete_post',
                'entity_type' => 'post',
                'label' => 'Post Deleted',
                'payload_notes' => 'Fires before a post is deleted.',
            ],
            'wordpress.post.trashed' => [
                'group' => 'WordPress Core',
                'hook' => 'trashed_post',
                'entity_type' => 'post',
                'label' => 'Post Trashed',
                'payload_notes' => 'Fires when a post enters the trash.',
            ],
            'wordpress.post.restored' => [
                'group' => 'WordPress Core',
                'hook' => 'untrashed_post',
                'entity_type' => 'post',
                'label' => 'Post Restored',
                'payload_notes' => 'Fires when a trashed post is restored.',
            ],
            'wordpress.media.uploaded' => [
                'group' => 'WordPress Core',
                'hook' => 'add_attachment',
                'entity_type' => 'attachment',
                'label' => 'Media Uploaded',
                'payload_notes' => 'Attachment uploaded to media library.',
            ],
            'wordpress.media.deleted' => [
                'group' => 'WordPress Core',
                'hook' => 'delete_attachment',
                'entity_type' => 'attachment',
                'label' => 'Media Deleted',
                'payload_notes' => 'Fires before an attachment is removed.',
            ],
            'woocommerce.checkout.started' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_before_checkout_form',
                'entity_type' => 'checkout',
                'label' => 'Checkout Started',
                'payload_notes' => 'Fires when checkout is viewed.',
            ],
            'woocommerce.checkout.completed' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_checkout_order_processed',
                'entity_type' => 'order',
                'label' => 'Checkout Completed',
                'payload_notes' => 'Fires when checkout becomes an order.',
            ],
            'woocommerce.order.created' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_new_order',
                'entity_type' => 'order',
                'label' => 'Order Created',
                'payload_notes' => 'Available only when WooCommerce is active.',
            ],
            'woocommerce.order.paid' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_payment_complete',
                'entity_type' => 'order',
                'label' => 'Order Paid',
                'payload_notes' => 'Payment completed for order.',
            ],
            'woocommerce.order.status_changed' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_order_status_changed',
                'entity_type' => 'order',
                'label' => 'Order Status Changed',
                'payload_notes' => 'Includes old and new status in metadata.',
            ],
            'woocommerce.order.refunded' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_order_refunded',
                'entity_type' => 'order',
                'label' => 'Order Refunded',
                'payload_notes' => 'Fires when an order refund is created.',
            ],
            'woocommerce.cart.updated' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_cart_updated',
                'entity_type' => 'cart',
                'label' => 'Cart Updated',
                'payload_notes' => 'Fires when cart contents or totals change.',
            ],
            'woocommerce.coupon.applied' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_applied_coupon',
                'entity_type' => 'coupon',
                'label' => 'Coupon Applied',
                'payload_notes' => 'Includes the coupon code in metadata.',
            ],
            'woocommerce.coupon.removed' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_removed_coupon',
                'entity_type' => 'coupon',
                'label' => 'Coupon Removed',
                'payload_notes' => 'Includes the coupon code in metadata.',
            ],
            'woocommerce.product.created' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_new_product',
                'entity_type' => 'product',
                'label' => 'Product Created',
                'payload_notes' => 'Fires when a WooCommerce product is created.',
            ],
            'woocommerce.product.updated' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_update_product',
                'entity_type' => 'product',
                'label' => 'Product Updated',
                'payload_notes' => 'Fires when a product is updated.',
            ],
            'woocommerce.product.deleted' => [
                'group' => 'WooCommerce',
                'hook' => 'before_delete_post',
                'entity_type' => 'product',
                'label' => 'Product Deleted',
                'payload_notes' => 'Fires before a WooCommerce product is deleted.',
            ],
            'woocommerce.cart.abandoned' => [
                'group' => 'WooCommerce',
                'hook' => 'woocommerce_cart_emptied',
                'entity_type' => 'cart',
                'label' => 'Cart Abandoned',
                'payload_notes' => 'Fires when an identifiable cart is emptied or discarded.',
            ],
        ];

        return $events;
    }
}
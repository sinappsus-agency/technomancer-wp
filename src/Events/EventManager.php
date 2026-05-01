<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Events;

use Sinappsus\N8nConnector\Flows\Dispatcher;
use Sinappsus\N8nConnector\Flows\FlowRepository;
use Sinappsus\N8nConnector\Integrations\Erpnext\Client as ErpnextClient;
use Sinappsus\N8nConnector\Integrations\Notifuse\Client as NotifuseClient;

final class EventManager
{
    private FlowRepository $flows;

    private Dispatcher $dispatcher;

    private PayloadBuilder $payloadBuilder;

    private NotifuseClient $notifuseClient;

    private ErpnextClient $erpnextClient;

    public function __construct(FlowRepository $flows, Dispatcher $dispatcher, NotifuseClient $notifuseClient, ErpnextClient $erpnextClient)
    {
        $this->flows = $flows;
        $this->dispatcher = $dispatcher;
        $this->payloadBuilder = new PayloadBuilder();
        $this->notifuseClient = $notifuseClient;
        $this->erpnextClient = $erpnextClient;
    }

    public function register(): void
    {
        add_action('user_register', [$this, 'onUserCreated']);
        add_action('profile_update', [$this, 'onUserUpdated'], 10, 3);
        add_action('delete_user', [$this, 'onUserDeleted'], 10, 1);
        add_action('set_user_role', [$this, 'onUserRoleChanged'], 10, 3);
        add_action('wp_login', [$this, 'onUserLogin'], 10, 2);
        add_action('wp_logout', [$this, 'onUserLogout']);
        add_action('after_password_reset', [$this, 'onPasswordReset'], 10, 1);
        add_action('comment_post', [$this, 'onCommentCreated'], 10, 1);
        add_action('edit_comment', [$this, 'onCommentUpdated'], 10, 1);
        add_action('delete_comment', [$this, 'onCommentDeleted'], 10, 1);
        add_action('transition_comment_status', [$this, 'onCommentStatusChanged'], 10, 3);
        add_action('wp_after_insert_post', [$this, 'onPostInserted'], 10, 3);
        add_action('publish_post', [$this, 'onPostPublished'], 10, 1);
        add_action('post_updated', [$this, 'onPostUpdated'], 10, 3);
        add_action('before_delete_post', [$this, 'onPostDeleted'], 10, 1);
        add_action('trashed_post', [$this, 'onPostTrashed'], 10, 1);
        add_action('untrashed_post', [$this, 'onPostRestored'], 10, 1);
        add_action('add_attachment', [$this, 'onAttachmentAdded'], 10, 1);
        add_action('delete_attachment', [$this, 'onAttachmentDeleted'], 10, 1);

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_before_checkout_form', [$this, 'onCheckoutStarted']);
            add_action('woocommerce_checkout_order_processed', [$this, 'onCheckoutCompleted'], 10, 1);
            add_action('woocommerce_new_order', [$this, 'onOrderCreated'], 10, 1);
            add_action('woocommerce_payment_complete', [$this, 'onOrderPaid'], 10, 1);
            add_action('woocommerce_order_status_changed', [$this, 'onOrderStatusChanged'], 10, 3);
            add_action('woocommerce_order_refunded', [$this, 'onOrderRefunded'], 10, 2);
            add_action('woocommerce_cart_updated', [$this, 'onCartUpdated']);
            add_action('woocommerce_applied_coupon', [$this, 'onCouponApplied'], 10, 1);
            add_action('woocommerce_removed_coupon', [$this, 'onCouponRemoved'], 10, 1);
            add_action('woocommerce_new_product', [$this, 'onProductCreated'], 10, 1);
            add_action('woocommerce_update_product', [$this, 'onProductUpdated'], 10, 1);
            add_action('woocommerce_cart_emptied', [$this, 'onCartAbandoned']);
        }
    }

    public function onUserCreated(int $userId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.user.created',
            'user',
            $userId,
            $this->payloadBuilder->userSnapshot($userId)
        );

        $this->emit('wordpress.user.created', $payload);
        $this->notifuseClient->subscribeUserById($userId, 'registration');
        $this->notifuseClient->trackCustomEvent(
            (string) ($payload['entity']['snapshot']['user_email'] ?? ''),
            'user.signup',
            ['wp_user_id' => $userId],
            'signup',
            null,
            'wp-user-signup-' . $userId,
            $payload['entity']['snapshot']
        );
        $this->notifuseClient->sendConfiguredTransactional(
            'welcome_template_id',
            [
                'email' => (string) ($payload['entity']['snapshot']['user_email'] ?? ''),
                'first_name' => (string) ($payload['entity']['snapshot']['display_name'] ?? ''),
            ],
            ['user_login' => (string) ($payload['entity']['snapshot']['user_login'] ?? '')],
            ['source' => 'wordpress.user.created', 'wp_user_id' => $userId],
            'welcome-user-' . $userId
        );
    }

    public function onUserUpdated(int $userId, ?\WP_User $oldUser = null, ?array $userdata = null): void
    {
        $snapshot = $this->payloadBuilder->userSnapshot($userId);
        $changes  = $oldUser instanceof \WP_User
            ? $this->payloadBuilder->userChanges($oldUser, $snapshot)
            : [];

        $payload = $this->payloadBuilder->build(
            'wordpress.user.updated',
            'user',
            $userId,
            $snapshot,
            $changes
        );

        $this->emit('wordpress.user.updated', $payload);
    }

    public function onUserDeleted(int $userId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.user.deleted',
            'user',
            $userId,
            $this->payloadBuilder->userSnapshot($userId)
        );

        $this->emit('wordpress.user.deleted', $payload);
    }

    public function onUserRoleChanged(int $userId, string $role, array $oldRoles): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.user.role_changed',
            'user',
            $userId,
            $this->payloadBuilder->userSnapshot($userId),
            ['old_roles' => $oldRoles, 'new_role' => $role]
        );

        $this->emit('wordpress.user.role_changed', $payload);
    }

    public function onUserLogin(string $userLogin, \WP_User $user): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.user.login',
            'user',
            (int) $user->ID,
            $this->payloadBuilder->userSnapshot((int) $user->ID),
            ['user_login' => $userLogin]
        );

        $this->emit('wordpress.user.login', $payload);
    }

    public function onUserLogout(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $payload = $this->payloadBuilder->build(
            'wordpress.user.logout',
            'user',
            $userId,
            $this->payloadBuilder->userSnapshot($userId)
        );

        $this->emit('wordpress.user.logout', $payload);
    }

    public function onPasswordReset(\WP_User $user): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.user.password_reset',
            'user',
            (int) $user->ID,
            $this->payloadBuilder->userSnapshot((int) $user->ID)
        );

        $this->emit('wordpress.user.password_reset', $payload);
    }

    public function onCommentCreated(int $commentId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.comment.created',
            'comment',
            $commentId,
            $this->payloadBuilder->commentSnapshot($commentId)
        );

        $this->emit('wordpress.comment.created', $payload);
    }

    public function onCommentUpdated(int $commentId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.comment.updated',
            'comment',
            $commentId,
            $this->payloadBuilder->commentSnapshot($commentId)
        );

        $this->emit('wordpress.comment.updated', $payload);
    }

    public function onCommentDeleted(int $commentId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.comment.deleted',
            'comment',
            $commentId,
            $this->payloadBuilder->commentSnapshot($commentId)
        );

        $this->emit('wordpress.comment.deleted', $payload);
    }

    public function onCommentStatusChanged(string $newStatus, string $oldStatus, \WP_Comment $comment): void
    {
        $commentId = (int) $comment->comment_ID;
        $payload = $this->payloadBuilder->build(
            'wordpress.comment.status_changed',
            'comment',
            $commentId,
            $this->payloadBuilder->commentSnapshot($commentId),
            ['old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        $this->emit('wordpress.comment.status_changed', $payload);
    }

    public function onPostInserted(int $postId, \WP_Post $post, bool $update): void
    {
        if ($update || wp_is_post_revision($postId)) {
            return;
        }

        $payload = $this->payloadBuilder->build(
            'wordpress.post.created',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.created', $payload);
    }

    public function onPostPublished(int $postId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.post.published',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.published', $payload);
    }

    public function onPostUpdated(int $postId, ?\WP_Post $postAfter = null, ?\WP_Post $postBefore = null): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        $changes = [];
        if ($postBefore instanceof \WP_Post && $postAfter instanceof \WP_Post) {
            foreach (['post_title', 'post_status', 'post_content', 'post_excerpt', 'post_name'] as $field) {
                if ($postBefore->$field !== $postAfter->$field) {
                    $changes[$field] = ['from' => $postBefore->$field, 'to' => $postAfter->$field];
                }
            }
        }

        $payload = $this->payloadBuilder->build(
            'wordpress.post.updated',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId),
            $changes
        );

        $this->emit('wordpress.post.updated', $payload);
    }

    public function onPostDeleted(int $postId): void
    {
        $post = get_post($postId);
        $payload = $this->payloadBuilder->build(
            'wordpress.post.deleted',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.deleted', $payload);

        if ($post instanceof \WP_Post && $post->post_type === 'product' && class_exists('WooCommerce')) {
            $productPayload = $this->payloadBuilder->build(
                'woocommerce.product.deleted',
                'product',
                $postId,
                $this->payloadBuilder->postSnapshot($postId)
            );

            $this->emit('woocommerce.product.deleted', $productPayload);
        }
    }

    public function onPostTrashed(int $postId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.post.trashed',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.trashed', $payload);
    }

    public function onPostRestored(int $postId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.post.restored',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.restored', $payload);
    }

    public function onAttachmentAdded(int $attachmentId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.media.uploaded',
            'attachment',
            $attachmentId,
            $this->payloadBuilder->postSnapshot($attachmentId)
        );

        $this->emit('wordpress.media.uploaded', $payload);
    }

    public function onAttachmentDeleted(int $attachmentId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.media.deleted',
            'attachment',
            $attachmentId,
            $this->payloadBuilder->postSnapshot($attachmentId)
        );

        $this->emit('wordpress.media.deleted', $payload);
    }

    public function onOrderCreated(int $orderId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.order.created',
            'order',
            $orderId,
            $this->payloadBuilder->orderSnapshot($orderId)
        );

        $this->emit('woocommerce.order.created', $payload);
        $this->erpnextClient->syncOrder($orderId, 'created');
    }

    public function onOrderPaid(int $orderId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.order.paid',
            'order',
            $orderId,
            $this->payloadBuilder->orderSnapshot($orderId)
        );

        $this->emit('woocommerce.order.paid', $payload);
        $this->erpnextClient->syncOrder($orderId, 'paid');
        $this->notifuseClient->trackCustomEvent(
            (string) ($payload['entity']['snapshot']['email'] ?? ''),
            'order.paid',
            $payload['entity']['snapshot'],
            'purchase',
            isset($payload['entity']['snapshot']['total']) ? (float) $payload['entity']['snapshot']['total'] : null,
            'order-paid-' . $orderId,
            $this->buildOrderContact($orderId)
        );
        $this->notifuseClient->sendConfiguredTransactional(
            'order_paid_template_id',
            $this->buildOrderContact($orderId),
            $payload['entity']['snapshot'],
            ['source' => 'woocommerce.order.paid', 'order_id' => $orderId],
            'order-paid-' . $orderId
        );
    }

    public function onOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.order.status_changed',
            'order',
            $orderId,
            $this->payloadBuilder->orderSnapshot($orderId),
            ['old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        $this->emit('woocommerce.order.status_changed', $payload);
    }

    public function onCheckoutStarted(): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.checkout.started',
            'checkout',
            0,
            $this->payloadBuilder->currentCartSnapshot()
        );

        $this->emit('woocommerce.checkout.started', $payload);
    }

    public function onCheckoutCompleted(int $orderId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.checkout.completed',
            'order',
            $orderId,
            $this->payloadBuilder->orderSnapshot($orderId)
        );

        $this->emit('woocommerce.checkout.completed', $payload);
        $this->notifuseClient->subscribeOrderById($orderId, 'checkout');
        $this->notifuseClient->trackCustomEvent(
            (string) ($payload['entity']['snapshot']['email'] ?? ''),
            'order.completed',
            $payload['entity']['snapshot'],
            'purchase',
            isset($payload['entity']['snapshot']['total']) ? (float) $payload['entity']['snapshot']['total'] : null,
            'order-completed-' . $orderId,
            $this->buildOrderContact($orderId)
        );
        $this->notifuseClient->sendConfiguredTransactional(
            'order_confirmation_template_id',
            $this->buildOrderContact($orderId),
            $payload['entity']['snapshot'],
            ['source' => 'woocommerce.checkout.completed', 'order_id' => $orderId],
            'order-confirmation-' . $orderId
        );
        $this->erpnextClient->syncCustomerFromOrder($orderId);
    }

    public function onOrderRefunded(int $orderId, int $refundId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.order.refunded',
            'order',
            $orderId,
            $this->payloadBuilder->orderSnapshot($orderId),
            ['refund_id' => $refundId]
        );

        $this->emit('woocommerce.order.refunded', $payload);
        $refundAmount = $this->refundAmount($refundId);
        $this->notifuseClient->trackCustomEvent(
            (string) ($payload['entity']['snapshot']['email'] ?? ''),
            'order.refunded',
            array_merge($payload['entity']['snapshot'], ['refund_id' => $refundId]),
            'purchase',
            $refundAmount === null ? null : (0 - abs($refundAmount)),
            'order-refunded-' . $refundId,
            $this->buildOrderContact($orderId)
        );
        $this->notifuseClient->sendConfiguredTransactional(
            'refund_template_id',
            $this->buildOrderContact($orderId),
            array_merge($payload['entity']['snapshot'], ['refund_id' => $refundId, 'refund_total' => $refundAmount]),
            ['source' => 'woocommerce.order.refunded', 'order_id' => $orderId, 'refund_id' => $refundId],
            'order-refund-' . $refundId
        );
    }

    public function onCartUpdated(): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.cart.updated',
            'cart',
            0,
            $this->payloadBuilder->currentCartSnapshot()
        );

        $this->emit('woocommerce.cart.updated', $payload);
    }

    public function onCouponApplied(string $couponCode): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.coupon.applied',
            'coupon',
            0,
            ['code' => $couponCode],
            ['coupon_code' => $couponCode]
        );

        $this->emit('woocommerce.coupon.applied', $payload);
    }

    public function onCouponRemoved(string $couponCode): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.coupon.removed',
            'coupon',
            0,
            ['code' => $couponCode],
            ['coupon_code' => $couponCode]
        );

        $this->emit('woocommerce.coupon.removed', $payload);
    }

    public function onProductCreated(int $productId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.product.created',
            'product',
            $productId,
            $this->payloadBuilder->productSnapshot($productId)
        );

        $this->emit('woocommerce.product.created', $payload);
    }

    public function onProductUpdated(int $productId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.product.updated',
            'product',
            $productId,
            $this->payloadBuilder->productSnapshot($productId)
        );

        $this->emit('woocommerce.product.updated', $payload);
    }

    public function onCartAbandoned(): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.cart.abandoned',
            'cart',
            0,
            $this->payloadBuilder->currentCartSnapshot()
        );

        $this->emit('woocommerce.cart.abandoned', $payload);
    }

    private function emit(string $eventName, array $payload): void
    {
        $flows = $this->flows->findEnabledByTrigger($eventName);

        foreach ($flows as $flow) {
            $this->dispatcher->dispatch($flow, $payload);
        }
    }

    private function buildOrderContact(int $orderId): array
    {
        if (! function_exists('wc_get_order')) {
            return [];
        }

        $order = wc_get_order($orderId);
        if (! $order) {
            return [];
        }

        return [
            'email' => $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
        ];
    }

    private function refundAmount(int $refundId): ?float
    {
        if (! function_exists('wc_get_order')) {
            return null;
        }

        $refund = wc_get_order($refundId);

        return $refund ? (float) $refund->get_total() : null;
    }
}
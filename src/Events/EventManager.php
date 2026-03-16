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
        add_action('profile_update', [$this, 'onUserUpdated'], 10, 1);
        add_action('wp_login', [$this, 'onUserLogin'], 10, 2);
        add_action('wp_logout', [$this, 'onUserLogout']);
        add_action('after_password_reset', [$this, 'onPasswordReset'], 10, 1);
        add_action('comment_post', [$this, 'onCommentCreated'], 10, 1);
        add_action('wp_after_insert_post', [$this, 'onPostInserted'], 10, 3);
        add_action('publish_post', [$this, 'onPostPublished'], 10, 1);
        add_action('post_updated', [$this, 'onPostUpdated'], 10, 1);
        add_action('before_delete_post', [$this, 'onPostDeleted'], 10, 1);
        add_action('add_attachment', [$this, 'onAttachmentAdded'], 10, 1);

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_before_checkout_form', [$this, 'onCheckoutStarted']);
            add_action('woocommerce_checkout_order_processed', [$this, 'onCheckoutCompleted'], 10, 1);
            add_action('woocommerce_new_order', [$this, 'onOrderCreated'], 10, 1);
            add_action('woocommerce_payment_complete', [$this, 'onOrderPaid'], 10, 1);
            add_action('woocommerce_order_status_changed', [$this, 'onOrderStatusChanged'], 10, 3);
            add_action('woocommerce_order_refunded', [$this, 'onOrderRefunded'], 10, 2);
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
    }

    public function onUserUpdated(int $userId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.user.updated',
            'user',
            $userId,
            $this->payloadBuilder->userSnapshot($userId)
        );

        $this->emit('wordpress.user.updated', $payload);
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

    public function onPostUpdated(int $postId): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        $payload = $this->payloadBuilder->build(
            'wordpress.post.updated',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.updated', $payload);
    }

    public function onPostDeleted(int $postId): void
    {
        $payload = $this->payloadBuilder->build(
            'wordpress.post.deleted',
            'post',
            $postId,
            $this->payloadBuilder->postSnapshot($postId)
        );

        $this->emit('wordpress.post.deleted', $payload);
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
    }

    public function onProductUpdated(int $productId): void
    {
        $payload = $this->payloadBuilder->build(
            'woocommerce.product.updated',
            'product',
            $productId,
            $this->payloadBuilder->postSnapshot($productId)
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
}
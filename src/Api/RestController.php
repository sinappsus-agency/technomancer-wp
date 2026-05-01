<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Api;

use TechnomancerWp\Connector\Core\Settings;
use TechnomancerWp\Connector\Events\EventRegistry;
use TechnomancerWp\Connector\Flows\FlowRepository;
use TechnomancerWp\Connector\Flows\Logger;
use TechnomancerWp\Connector\Security\RequestAuthorizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RestController
{
    private FlowRepository $flows;

    private Logger $logger;

    private RequestAuthorizer $authorizer;

    public function __construct(FlowRepository $flows, Logger $logger, RequestAuthorizer $authorizer)
    {
        $this->flows = $flows;
        $this->logger = $logger;
        $this->authorizer = $authorizer;
    }

    public function registerRoutes(): void
    {
        register_rest_route('technomancer-wp/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('technomancer-wp/v1', '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'events'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('technomancer-wp/v1', '/flows', [
            'methods' => 'GET',
            'callback' => [$this, 'flows'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('technomancer-wp/v1', '/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'logs'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route('technomancer-wp/v1', '/entity/(?P<type>[a-z_-]+)/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'entity'],
            'permission_callback' => [$this, 'authorizeN8nRequest'],
        ]);

        register_rest_route('technomancer-wp/v1', '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'authorizeN8nRequest'],
        ]);

        register_rest_route('technomancer-wp/v1', '/action/meta', [
            'methods' => 'POST',
            'callback' => [$this, 'updateMeta'],
            'permission_callback' => [$this, 'authorizeN8nRequest'],
        ]);

        register_rest_route('technomancer-wp/v1', '/action/order-note', [
            'methods' => 'POST',
            'callback' => [$this, 'addOrderNote'],
            'permission_callback' => [$this, 'authorizeN8nRequest'],
        ]);
    }

    public function health(): WP_REST_Response
    {
        return new WP_REST_Response([
            'plugin' => 'Technomancer WP',
            'version' => TECHNOMANCER_WP_VERSION,
            'woocommerce_active' => class_exists('WooCommerce'),
        ]);
    }

    public function events(): WP_REST_Response
    {
        return new WP_REST_Response(array_values(EventRegistry::definitions()));
    }

    public function flows(): WP_REST_Response
    {
        return new WP_REST_Response($this->flows->all());
    }

    public function logs(): WP_REST_Response
    {
        return new WP_REST_Response([
            'stats' => $this->logger->stats(),
            'items' => $this->logger->query([
                'limit' => (int) (($_GET['limit'] ?? 50)),
                'status' => sanitize_text_field((string) ($_GET['status'] ?? '')),
                'event_key' => sanitize_text_field((string) ($_GET['event_key'] ?? '')),
                'entity_type' => sanitize_text_field((string) ($_GET['entity_type'] ?? '')),
                'flow_id' => (int) (($_GET['flow_id'] ?? 0)),
                'search' => sanitize_text_field((string) ($_GET['search'] ?? '')),
            ]),
        ]);
    }

    public function entity(WP_REST_Request $request)
    {
        $type = (string) $request->get_param('type');
        $id = (int) $request->get_param('id');

        if ($type === 'user') {
            $user = get_userdata($id);

            return $user ? new WP_REST_Response([
                'ID' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'display_name' => $user->display_name,
                'roles' => $user->roles,
            ]) : new WP_Error('tmwp_not_found', 'User not found.', ['status' => 404]);
        }

        if ($type === 'post' || $type === 'page' || $type === 'attachment') {
            $post = get_post($id);

            return $post ? new WP_REST_Response($post) : new WP_Error('tmwp_not_found', 'Post not found.', ['status' => 404]);
        }

        if ($type === 'order' && function_exists('wc_get_order')) {
            $order = wc_get_order($id);

            return $order ? new WP_REST_Response($order->get_data()) : new WP_Error('tmwp_not_found', 'Order not found.', ['status' => 404]);
        }

        return new WP_Error('tmwp_invalid_type', 'Entity type not supported.', ['status' => 400]);
    }

    public function search(WP_REST_Request $request)
    {
        $type = sanitize_text_field((string) $request->get_param('type'));
        $term = sanitize_text_field((string) $request->get_param('term'));
        $limit = max(1, min(50, (int) $request->get_param('limit')));

        if ($type === 'user') {
            $users = get_users([
                'search' => '*' . $term . '*',
                'number' => $limit,
                'fields' => ['ID', 'user_login', 'user_email', 'display_name'],
            ]);

            return new WP_REST_Response($users);
        }

        if ($type === 'post') {
            $posts = get_posts([
                'post_type' => 'any',
                's' => $term,
                'posts_per_page' => $limit,
                'post_status' => 'any',
            ]);

            return new WP_REST_Response($posts);
        }

        if ($type === 'order' && function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'limit' => $limit,
                'search' => $term,
            ]);
            $result = [];
            foreach ($orders as $order) {
                $result[] = $order->get_data();
            }

            return new WP_REST_Response($result);
        }

        return new WP_Error('tmwp_invalid_search_type', 'Search type not supported.', ['status' => 400]);
    }

    public function updateMeta(WP_REST_Request $request)
    {
        $entityType = sanitize_text_field((string) $request->get_param('entity_type'));
        $entityId = (int) $request->get_param('entity_id');
        $metaKey = sanitize_key((string) $request->get_param('meta_key'));
        $metaValue = $request->get_param('meta_value');

        if (! in_array($entityType, ['user', 'post', 'order'], true)) {
            return new WP_Error('tmwp_invalid_type', 'Write target not allowed.', ['status' => 400]);
        }

        if ($entityType === 'user') {
            update_user_meta($entityId, $metaKey, $metaValue);
        }

        if ($entityType === 'post') {
            update_post_meta($entityId, $metaKey, $metaValue);
        }

        if ($entityType === 'order') {
            if (! function_exists('wc_get_order')) {
                return new WP_Error('tmwp_wc_missing', 'WooCommerce is not active.', ['status' => 400]);
            }

            $order = wc_get_order($entityId);

            if (! $order) {
                return new WP_Error('tmwp_not_found', 'Order not found.', ['status' => 404]);
            }

            $order->update_meta_data($metaKey, $metaValue);
            $order->save();
        }

        $this->logger->log([
            'event_key' => 'api.meta.update',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => 'api_success',
            'message' => ['meta_key' => $metaKey],
        ]);

        return new WP_REST_Response(['updated' => true]);
    }

    public function addOrderNote(WP_REST_Request $request)
    {
        if (! function_exists('wc_get_order')) {
            return new WP_Error('tmwp_wc_missing', 'WooCommerce is not active.', ['status' => 400]);
        }

        $orderId = (int) $request->get_param('order_id');
        $note = sanitize_textarea_field((string) $request->get_param('note'));
        $isCustomerNote = (bool) $request->get_param('customer_note');
        $order = wc_get_order($orderId);

        if (! $order) {
            return new WP_Error('tmwp_not_found', 'Order not found.', ['status' => 404]);
        }

        $order->add_order_note($note, $isCustomerNote);

        $this->logger->log([
            'event_key' => 'api.order.note',
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'status' => 'api_success',
            'message' => ['customer_note' => $isCustomerNote],
        ]);

        return new WP_REST_Response(['created' => true]);
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    public function authorizeN8nRequest(WP_REST_Request $request)
    {
        return $this->authorizer->authorize($request);
    }
}

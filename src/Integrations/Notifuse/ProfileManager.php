<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Integrations\Notifuse;

use TechnomancerWp\Connector\Flows\Logger;

final class ProfileManager
{
    private Client $client;

    private Logger $logger;

    public function __construct(Client $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('show_user_profile', [$this, 'renderUserLists']);
        add_action('edit_user_profile', [$this, 'renderUserLists']);
        add_action('personal_options_update', [$this, 'saveUserLists']);
        add_action('edit_user_profile_update', [$this, 'saveUserLists']);

        add_shortcode('tmwp_notifuse_subscribe', [$this, 'renderSubscribeForm']);
        add_shortcode('tmwp_notifuse_unsubscribe', [$this, 'renderUnsubscribeForm']);

        add_action('wp_ajax_tmwp_notifuse_subscribe', [$this, 'handleSubscribe']);
        add_action('wp_ajax_nopriv_tmwp_notifuse_subscribe', [$this, 'handleSubscribe']);
        add_action('wp_ajax_tmwp_notifuse_unsubscribe', [$this, 'handleUnsubscribe']);
        add_action('wp_ajax_nopriv_tmwp_notifuse_unsubscribe', [$this, 'handleUnsubscribe']);
    }

    public function enqueueAssets(): void
    {
        wp_register_script(
            'snc-notifuse-forms',
            TECHNOMANCER_WP_URL . 'assets/js/notifuse-forms.js',
            [],
            TECHNOMANCER_WP_VERSION,
            true
        );
        wp_register_style(
            'snc-notifuse-forms',
            TECHNOMANCER_WP_URL . 'assets/css/notifuse-forms.css',
            [],
            TECHNOMANCER_WP_VERSION
        );

        wp_enqueue_script('snc-notifuse-forms');
        wp_enqueue_style('snc-notifuse-forms');
    }

    public function renderUserLists(\WP_User $user): void
    {
        if (! current_user_can('edit_user', $user->ID)) {
            return;
        }

        $lists = $this->client->getLists();
        $selected = get_user_meta($user->ID, 'tmwp_notifuse_list_ids', true);
        $selected = is_array($selected) ? $selected : [];
        ?>
        <h2>Notifuse Lists</h2>
        <p class="description">Assign this WordPress user to existing lists from your connected Notifuse workspace. Saving this form updates the remote contact membership; it does not create a new list.</p>
        <table class="form-table">
            <tr>
                <th><label for="tmwp_notifuse_list_ids">Assigned Existing Remote Lists</label></th>
                <td>
                    <select id="tmwp_notifuse_list_ids" name="tmwp_notifuse_list_ids[]" multiple size="6" style="min-width:320px;">
                        <?php foreach ($lists as $list) : ?>
                            <?php $listId = (string) ($list['id'] ?? $list['uuid'] ?? ''); ?>
                            <?php $label = (string) ($list['name'] ?? $listId); ?>
                            <option value="<?php echo esc_attr($listId); ?>" <?php selected(in_array($listId, $selected, true), true); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Choose one or more existing Notifuse lists that this user should belong to.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function saveUserLists(int $userId): void
    {
        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        $listIds = isset($_POST['tmwp_notifuse_list_ids']) && is_array($_POST['tmwp_notifuse_list_ids']) ? $_POST['tmwp_notifuse_list_ids'] : [];
        $this->client->updateUserLists($userId, $listIds);
    }

    public function renderSubscribeForm(array $attributes = []): string
    {
        $attributes = shortcode_atts([
            'button_text' => 'Subscribe',
            'list_ids' => '',
            'show_lists' => '0',
            'consent_text' => '',
            'consent_required' => '',
            'redirect_url' => '',
        ], $attributes);

        return $this->renderForm(
            'subscribe',
            (string) $attributes['button_text'],
            (string) $attributes['list_ids'],
            filter_var($attributes['show_lists'], FILTER_VALIDATE_BOOLEAN),
            (string) $attributes['consent_text'],
            (string) $attributes['consent_required'],
            (string) $attributes['redirect_url']
        );
    }

    public function renderUnsubscribeForm(array $attributes = []): string
    {
        $attributes = shortcode_atts([
            'button_text' => 'Unsubscribe',
            'redirect_url' => '',
        ], $attributes);

        return $this->renderForm('unsubscribe', (string) $attributes['button_text'], '', false, '', '', (string) $attributes['redirect_url']);
    }

    public function handleSubscribe(): void
    {
        if (! $this->validateAjaxNonce('subscribe')) {
            return;
        }

        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $firstName = sanitize_text_field((string) ($_POST['first_name'] ?? ''));
        $lastName = sanitize_text_field((string) ($_POST['last_name'] ?? ''));
        if ($email === '') {
            $this->logger->log([
                'event_key' => 'notifuse.form.subscribe',
                'entity_type' => 'contact',
                'status' => 'integration_failed',
                'message' => 'Email address is required.',
            ]);
            wp_send_json(['success' => false, 'message' => 'Email address is required.'], 400);
        }

        $listIds = [];
        if (isset($_POST['list_ids'])) {
            if (is_array($_POST['list_ids'])) {
                $listIds = array_map('sanitize_text_field', $_POST['list_ids']);
            } else {
                $listIds = array_values(array_filter(array_map('trim', explode(',', (string) $_POST['list_ids']))));
            }
        }

        $result = $this->client->subscribeEmail($email, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'source' => 'widget_form',
        ], $listIds, [
            'consent_given' => ! empty($_POST['consent_given']),
            'consent_text' => sanitize_text_field((string) ($_POST['consent_text'] ?? '')),
        ]);

        $this->logger->log([
            'event_key' => 'notifuse.form.subscribe',
            'entity_type' => 'contact',
            'status' => ! empty($result['success']) ? 'integration_sent' : 'integration_failed',
            'message' => $result['message'] ?? 'Subscribe request completed.',
            'response_code' => isset($result['code']) ? (int) $result['code'] : null,
            'payload' => [
                'email' => $email,
                'list_ids' => $listIds,
            ],
        ]);

        wp_send_json($result, $result['success'] ? 200 : 400);
    }

    public function handleUnsubscribe(): void
    {
        if (! $this->validateAjaxNonce('unsubscribe')) {
            return;
        }

        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        if ($email === '') {
            $this->logger->log([
                'event_key' => 'notifuse.form.unsubscribe',
                'entity_type' => 'contact',
                'status' => 'integration_failed',
                'message' => 'Email address is required.',
            ]);
            wp_send_json(['success' => false, 'message' => 'Email address is required.'], 400);
        }

        $result = $this->client->unsubscribeEmail($email);

        $this->logger->log([
            'event_key' => 'notifuse.form.unsubscribe',
            'entity_type' => 'contact',
            'status' => ! empty($result['success']) ? 'integration_sent' : 'integration_failed',
            'message' => $result['message'] ?? 'Unsubscribe request completed.',
            'response_code' => isset($result['code']) ? (int) $result['code'] : null,
            'payload' => [
                'email' => $email,
            ],
        ]);

        wp_send_json($result, $result['success'] ? 200 : 400);
    }

    private function validateAjaxNonce(string $action): bool
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) $_POST['nonce']) : '';

        if ($nonce !== '' && wp_verify_nonce($nonce, 'tmwp_notifuse_form')) {
            return true;
        }

        $this->logger->log([
            'event_key' => 'notifuse.form.' . $action,
            'entity_type' => 'contact',
            'status' => 'security_failed',
            'message' => 'Invalid or expired frontend form nonce.',
        ]);
        wp_send_json(['success' => false, 'message' => 'This form expired. Refresh the page and try again.'], 403);

        return false;
    }

    private function renderForm(string $action, string $buttonText, string $listIds, bool $showLists = false, string $consentText = '', string $consentRequired = '', string $redirectUrl = ''): string
    {
        $ajaxAction = $action === 'unsubscribe' ? 'tmwp_notifuse_unsubscribe' : 'tmwp_notifuse_subscribe';
        $settings = \TechnomancerWp\Connector\Core\Settings::get('notifuse', []);
        $configuredLists = $this->client->getConfiguredFormLists();
        $defaultListIds = array_values(array_filter(array_map('trim', explode(',', $listIds))));
        $configuredDefaultListId = sanitize_text_field((string) ($settings['default_list_id'] ?? ''));
        $redirectUrl = esc_url_raw(trim($redirectUrl));

        if (empty($defaultListIds) && $configuredDefaultListId !== '') {
            $defaultListIds = [$configuredDefaultListId];
        }

        if ($action !== 'unsubscribe' && ! $showLists && empty($defaultListIds)) {
            if (count($configuredLists) === 1) {
                $singleListId = (string) ($configuredLists[0]['id'] ?? $configuredLists[0]['uuid'] ?? '');
                if ($singleListId !== '') {
                    $defaultListIds = [$singleListId];
                }
            } elseif (count($configuredLists) > 1) {
                $showLists = true;
            }
        }

        $resolvedListIds = implode(',', $defaultListIds);
        $resolvedConsentText = $consentText !== '' ? $consentText : (string) ($settings['consent_label'] ?? 'I agree to receive updates by email.');
        $requiresConsent = $consentRequired === '' ? ! empty($settings['require_consent']) : filter_var($consentRequired, FILTER_VALIDATE_BOOLEAN);
        ob_start();
        ?>
        <form class="snc-notifuse-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"<?php echo $redirectUrl !== '' ? ' data-success-redirect="' . esc_url($redirectUrl) . '"' : ''; ?>>
            <input type="hidden" name="action" value="<?php echo esc_attr($ajaxAction); ?>" />
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('tmwp_notifuse_form')); ?>" />
            <?php if ($action !== 'unsubscribe' && ! $showLists && $resolvedListIds !== '') : ?>
                <input type="hidden" name="list_ids" value="<?php echo esc_attr($resolvedListIds); ?>" />
            <?php endif; ?>
            <p><input type="email" name="email" placeholder="Email" required /></p>
            <?php if ($action !== 'unsubscribe') : ?>
                <p><input type="text" name="first_name" placeholder="First name" /></p>
                <p><input type="text" name="last_name" placeholder="Last name" /></p>
                <?php if ($showLists && ! empty($configuredLists)) : ?>
                    <fieldset class="snc-notifuse-list-selector">
                        <legend>Select lists</legend>
                        <?php foreach ($configuredLists as $list) : ?>
                            <?php $listId = (string) ($list['id'] ?? $list['uuid'] ?? ''); ?>
                            <label>
                                <input type="checkbox" name="list_ids[]" value="<?php echo esc_attr($listId); ?>" <?php checked(in_array($listId, $defaultListIds, true), true); ?> />
                                <?php echo esc_html((string) ($list['name'] ?? $listId)); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php elseif ($action !== 'unsubscribe' && $resolvedListIds === '') : ?>
                    <p class="snc-notifuse-status is-error">No Notifuse list is currently available for this form. Set a fallback list ID or expose selectable lists in the plugin settings.</p>
                <?php endif; ?>
                <?php if ($resolvedConsentText !== '') : ?>
                    <label class="snc-notifuse-consent">
                        <input type="checkbox" name="consent_given" value="1" <?php echo $requiresConsent ? 'required' : ''; ?> />
                        <span><?php echo esc_html($resolvedConsentText); ?></span>
                    </label>
                    <input type="hidden" name="consent_text" value="<?php echo esc_attr($resolvedConsentText); ?>" />
                <?php endif; ?>
            <?php endif; ?>
            <p><button type="submit"><?php echo esc_html($buttonText); ?></button></p>
            <p class="snc-notifuse-status" aria-live="polite"></p>
        </form>
        <?php

        return (string) ob_get_clean();
    }
}

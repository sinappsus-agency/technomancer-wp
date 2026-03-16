<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Notifuse;

final class ProfileManager
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('show_user_profile', [$this, 'renderUserLists']);
        add_action('edit_user_profile', [$this, 'renderUserLists']);
        add_action('personal_options_update', [$this, 'saveUserLists']);
        add_action('edit_user_profile_update', [$this, 'saveUserLists']);

        add_shortcode('snc_notifuse_subscribe', [$this, 'renderSubscribeForm']);
        add_shortcode('snc_notifuse_unsubscribe', [$this, 'renderUnsubscribeForm']);

        add_action('wp_ajax_snc_notifuse_subscribe', [$this, 'handleSubscribe']);
        add_action('wp_ajax_nopriv_snc_notifuse_subscribe', [$this, 'handleSubscribe']);
        add_action('wp_ajax_snc_notifuse_unsubscribe', [$this, 'handleUnsubscribe']);
        add_action('wp_ajax_nopriv_snc_notifuse_unsubscribe', [$this, 'handleUnsubscribe']);
    }

    public function enqueueAssets(): void
    {
        wp_register_script(
            'snc-notifuse-forms',
            SINAPPSUS_N8N_CONNECTOR_URL . 'assets/js/notifuse-forms.js',
            [],
            SINAPPSUS_N8N_CONNECTOR_VERSION,
            true
        );
        wp_register_style(
            'snc-notifuse-forms',
            SINAPPSUS_N8N_CONNECTOR_URL . 'assets/css/notifuse-forms.css',
            [],
            SINAPPSUS_N8N_CONNECTOR_VERSION
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
        $selected = get_user_meta($user->ID, 'snc_notifuse_list_ids', true);
        $selected = is_array($selected) ? $selected : [];
        ?>
        <h2>Notifuse Lists</h2>
        <p class="description">Assign this WordPress user to existing lists from your connected Notifuse workspace. Saving this form updates the remote contact membership; it does not create a new list.</p>
        <table class="form-table">
            <tr>
                <th><label for="snc_notifuse_list_ids">Assigned Existing Remote Lists</label></th>
                <td>
                    <select id="snc_notifuse_list_ids" name="snc_notifuse_list_ids[]" multiple size="6" style="min-width:320px;">
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

        $listIds = isset($_POST['snc_notifuse_list_ids']) && is_array($_POST['snc_notifuse_list_ids']) ? $_POST['snc_notifuse_list_ids'] : [];
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
        ], $attributes);

        return $this->renderForm(
            'subscribe',
            (string) $attributes['button_text'],
            (string) $attributes['list_ids'],
            filter_var($attributes['show_lists'], FILTER_VALIDATE_BOOLEAN),
            (string) $attributes['consent_text'],
            (string) $attributes['consent_required']
        );
    }

    public function renderUnsubscribeForm(array $attributes = []): string
    {
        $attributes = shortcode_atts(['button_text' => 'Unsubscribe'], $attributes);

        return $this->renderForm('unsubscribe', (string) $attributes['button_text'], '');
    }

    public function handleSubscribe(): void
    {
        check_ajax_referer('snc_notifuse_form', 'nonce');

        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $firstName = sanitize_text_field((string) ($_POST['first_name'] ?? ''));
        $lastName = sanitize_text_field((string) ($_POST['last_name'] ?? ''));
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

        wp_send_json($result, $result['success'] ? 200 : 400);
    }

    public function handleUnsubscribe(): void
    {
        check_ajax_referer('snc_notifuse_form', 'nonce');

        $email = sanitize_email((string) ($_POST['email'] ?? ''));
        $result = $this->client->unsubscribeEmail($email);

        wp_send_json($result, $result['success'] ? 200 : 400);
    }

    private function renderForm(string $action, string $buttonText, string $listIds, bool $showLists = false, string $consentText = '', string $consentRequired = ''): string
    {
        $ajaxAction = $action === 'unsubscribe' ? 'snc_notifuse_unsubscribe' : 'snc_notifuse_subscribe';
        $settings = \Sinappsus\N8nConnector\Core\Settings::get('notifuse', []);
        $configuredLists = $this->client->getConfiguredFormLists();
        $defaultListIds = array_values(array_filter(array_map('trim', explode(',', $listIds))));
        $resolvedConsentText = $consentText !== '' ? $consentText : (string) ($settings['consent_label'] ?? 'I agree to receive updates by email.');
        $requiresConsent = $consentRequired === '' ? ! empty($settings['require_consent']) : filter_var($consentRequired, FILTER_VALIDATE_BOOLEAN);
        ob_start();
        ?>
        <form class="snc-notifuse-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr($ajaxAction); ?>" />
            <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('snc_notifuse_form')); ?>" />
            <?php if ($action !== 'unsubscribe' && ! $showLists && $listIds !== '') : ?>
                <input type="hidden" name="list_ids" value="<?php echo esc_attr($listIds); ?>" />
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
<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Integrations\Erpnext\Admin;

use TechnomancerWp\Connector\Integrations\Erpnext\Client;

final class ProfileFieldsManager
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function register(): void
    {
        add_action('register_form', [$this, 'renderRegisterFields']);
        add_action('user_register', [$this, 'saveRegisterFields']);
        add_action('show_user_profile', [$this, 'renderProfileFields']);
        add_action('edit_user_profile', [$this, 'renderProfileFields']);
        add_action('personal_options_update', [$this, 'saveProfileFields']);
        add_action('edit_user_profile_update', [$this, 'saveProfileFields']);
    }

    public function renderRegisterFields(): void
    {
        $customerGroupOptions = $this->client->getReferenceOptions('Customer Group');
        $territoryOptions = $this->client->getReferenceOptions('Territory');
        ?>
        <p>
            <label for="tmwp_erp_customer_name">ERP Customer Name<br />
                <input type="text" name="tmwp_erp_customer_name" id="tmwp_erp_customer_name" class="input" value="" />
            </label>
        </p>
        <p>
            <label for="tmwp_erp_customer_group">ERP Customer Group<br /></label>
            <?php $this->renderSelectOrInput('tmwp_erp_customer_group', 'tmwp_erp_customer_group', 'Commercial', $customerGroupOptions, 'input'); ?>
        </p>
        <p>
            <label for="tmwp_erp_territory">ERP Territory<br /></label>
            <?php $this->renderSelectOrInput('tmwp_erp_territory', 'tmwp_erp_territory', 'All Territories', $territoryOptions, 'input'); ?>
        </p>
        <?php
    }

    public function saveRegisterFields(int $userId): void
    {
        $this->persistFields($userId);
    }

    public function renderProfileFields(\WP_User $user): void
    {
        $customerGroupOptions = $this->client->getReferenceOptions('Customer Group');
        $territoryOptions = $this->client->getReferenceOptions('Territory');
        $companyOptions = $this->client->getReferenceOptions('Company');
        ?>
        <h2>ERPNext Profile Fields</h2>
        <table class="form-table">
            <tr>
                <th><label for="tmwp_erp_customer_name">ERP Customer Name</label></th>
                <td><input type="text" name="tmwp_erp_customer_name" id="tmwp_erp_customer_name" class="regular-text" value="<?php echo esc_attr((string) get_user_meta($user->ID, 'tmwp_erp_customer_name', true)); ?>" /></td>
            </tr>
            <tr>
                <th><label for="tmwp_erp_customer_group">ERP Customer Group</label></th>
                <td><?php $this->renderSelectOrInput('tmwp_erp_customer_group', 'tmwp_erp_customer_group', (string) get_user_meta($user->ID, 'tmwp_erp_customer_group', true), $customerGroupOptions, 'regular-text'); ?></td>
            </tr>
            <tr>
                <th><label for="tmwp_erp_territory">ERP Territory</label></th>
                <td><?php $this->renderSelectOrInput('tmwp_erp_territory', 'tmwp_erp_territory', (string) get_user_meta($user->ID, 'tmwp_erp_territory', true), $territoryOptions, 'regular-text'); ?></td>
            </tr>
            <tr>
                <th><label for="tmwp_erp_company">ERP Company</label></th>
                <td><?php $this->renderSelectOrInput('tmwp_erp_company', 'tmwp_erp_company', (string) get_user_meta($user->ID, 'tmwp_erp_company', true), $companyOptions, 'regular-text'); ?></td>
            </tr>
        </table>
        <?php
    }

    public function saveProfileFields(int $userId): void
    {
        if (! current_user_can('edit_user', $userId)) {
            return;
        }

        $this->persistFields($userId);
    }

    private function persistFields(int $userId): void
    {
        update_user_meta($userId, 'tmwp_erp_customer_name', sanitize_text_field((string) ($_POST['tmwp_erp_customer_name'] ?? '')));
        update_user_meta($userId, 'tmwp_erp_customer_group', sanitize_text_field((string) ($_POST['tmwp_erp_customer_group'] ?? '')));
        update_user_meta($userId, 'tmwp_erp_territory', sanitize_text_field((string) ($_POST['tmwp_erp_territory'] ?? '')));
        update_user_meta($userId, 'tmwp_erp_company', sanitize_text_field((string) ($_POST['tmwp_erp_company'] ?? '')));
    }

    private function renderSelectOrInput(string $id, string $name, string $value, array $options, string $className): void
    {
        if (! empty($options)) {
            ?>
            <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>">
                <option value="">Select from ERPNext</option>
                <?php if ($value !== '' && ! isset($options[$value])) : ?>
                    <option value="<?php echo esc_attr($value); ?>" selected="selected"><?php echo esc_html('Current saved value (' . $value . ')'); ?></option>
                <?php endif; ?>
                <?php foreach ($options as $optionValue => $optionLabel) : ?>
                    <option value="<?php echo esc_attr((string) $optionValue); ?>" <?php selected($value, (string) $optionValue); ?>><?php echo esc_html((string) $optionLabel); ?></option>
                <?php endforeach; ?>
            </select>
            <?php

            return;
        }

        ?>
        <input type="text" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>" class="<?php echo esc_attr($className); ?>" value="<?php echo esc_attr($value); ?>" />
        <?php
    }
}

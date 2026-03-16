<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Erpnext\Admin;

final class ProfileFieldsManager
{
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
        ?>
        <p>
            <label for="snc_erp_customer_name">ERP Customer Name<br />
                <input type="text" name="snc_erp_customer_name" id="snc_erp_customer_name" class="input" value="" />
            </label>
        </p>
        <p>
            <label for="snc_erp_customer_group">ERP Customer Group<br />
                <input type="text" name="snc_erp_customer_group" id="snc_erp_customer_group" class="input" value="Commercial" />
            </label>
        </p>
        <p>
            <label for="snc_erp_territory">ERP Territory<br />
                <input type="text" name="snc_erp_territory" id="snc_erp_territory" class="input" value="All Territories" />
            </label>
        </p>
        <?php
    }

    public function saveRegisterFields(int $userId): void
    {
        $this->persistFields($userId);
    }

    public function renderProfileFields(\WP_User $user): void
    {
        ?>
        <h2>ERPNext Profile Fields</h2>
        <table class="form-table">
            <tr>
                <th><label for="snc_erp_customer_name">ERP Customer Name</label></th>
                <td><input type="text" name="snc_erp_customer_name" id="snc_erp_customer_name" class="regular-text" value="<?php echo esc_attr((string) get_user_meta($user->ID, 'snc_erp_customer_name', true)); ?>" /></td>
            </tr>
            <tr>
                <th><label for="snc_erp_customer_group">ERP Customer Group</label></th>
                <td><input type="text" name="snc_erp_customer_group" id="snc_erp_customer_group" class="regular-text" value="<?php echo esc_attr((string) get_user_meta($user->ID, 'snc_erp_customer_group', true)); ?>" /></td>
            </tr>
            <tr>
                <th><label for="snc_erp_territory">ERP Territory</label></th>
                <td><input type="text" name="snc_erp_territory" id="snc_erp_territory" class="regular-text" value="<?php echo esc_attr((string) get_user_meta($user->ID, 'snc_erp_territory', true)); ?>" /></td>
            </tr>
            <tr>
                <th><label for="snc_erp_company">ERP Company</label></th>
                <td><input type="text" name="snc_erp_company" id="snc_erp_company" class="regular-text" value="<?php echo esc_attr((string) get_user_meta($user->ID, 'snc_erp_company', true)); ?>" /></td>
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
        update_user_meta($userId, 'snc_erp_customer_name', sanitize_text_field((string) ($_POST['snc_erp_customer_name'] ?? '')));
        update_user_meta($userId, 'snc_erp_customer_group', sanitize_text_field((string) ($_POST['snc_erp_customer_group'] ?? '')));
        update_user_meta($userId, 'snc_erp_territory', sanitize_text_field((string) ($_POST['snc_erp_territory'] ?? '')));
        update_user_meta($userId, 'snc_erp_company', sanitize_text_field((string) ($_POST['snc_erp_company'] ?? '')));
    }
}
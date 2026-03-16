<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Erpnext\Admin;

final class ProductFieldsManager
{
    public function register(): void
    {
        add_action('woocommerce_product_options_inventory_product_data', [$this, 'renderFields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'saveFields']);
    }

    public function renderFields(): void
    {
        if (! function_exists('woocommerce_wp_text_input')) {
            return;
        }

        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_snc_erp_item_code',
            'label' => 'ERP Item Code',
            'desc_tip' => true,
            'description' => 'ERPNext item code used for sync and stock verification.',
        ]);
        woocommerce_wp_text_input([
            'id' => '_snc_erp_item_group',
            'label' => 'ERP Item Group',
            'desc_tip' => true,
            'description' => 'ERPNext item group for export mapping.',
        ]);
        woocommerce_wp_text_input([
            'id' => '_snc_erp_warehouse',
            'label' => 'ERP Warehouse',
            'desc_tip' => true,
            'description' => 'ERPNext warehouse/source for this product.',
        ]);
        echo '</div>';
    }

    public function saveFields($product): void
    {
        if (! $product instanceof \WC_Product) {
            return;
        }

        $product->update_meta_data('_snc_erp_item_code', sanitize_text_field((string) ($_POST['_snc_erp_item_code'] ?? '')));
        $product->update_meta_data('_snc_erp_item_group', sanitize_text_field((string) ($_POST['_snc_erp_item_group'] ?? '')));
        $product->update_meta_data('_snc_erp_warehouse', sanitize_text_field((string) ($_POST['_snc_erp_warehouse'] ?? '')));
    }
}
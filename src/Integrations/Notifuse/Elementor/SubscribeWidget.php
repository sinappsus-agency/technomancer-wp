<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Notifuse\Elementor;

if (! class_exists('\Elementor\Widget_Base')) {
    return;
}

final class SubscribeWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'snc_notifuse_subscribe';
    }

    public function get_title(): string
    {
        return 'Notifuse Subscribe';
    }

    public function get_icon(): string
    {
        return 'eicon-mail';
    }

    public function get_categories(): array
    {
        return ['general'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content_section', ['label' => 'Content']);
        $this->add_control('button_text', [
            'label' => 'Button Text',
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => 'Subscribe',
        ]);
        $this->add_control('list_ids', [
            'label' => 'List IDs',
            'type' => \Elementor\Controls_Manager::TEXT,
            'description' => 'Comma-separated Notifuse list IDs.',
            'default' => '',
        ]);
        $this->add_control('show_lists', [
            'label' => 'Show Selectable Lists',
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => 'Yes',
            'label_off' => 'No',
            'return_value' => 'yes',
            'default' => '',
        ]);
        $this->add_control('consent_text', [
            'label' => 'Consent Text',
            'type' => \Elementor\Controls_Manager::TEXT,
            'default' => '',
            'description' => 'Leave blank to use the plugin default consent text.',
        ]);
        $this->add_control('consent_required', [
            'label' => 'Require Consent Checkbox',
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => 'Yes',
            'label_off' => 'No',
            'return_value' => '1',
            'default' => '',
        ]);
        $this->add_control('redirect_url', [
            'label' => 'Success Redirect URL',
            'type' => \Elementor\Controls_Manager::URL,
            'placeholder' => 'https://example.com/thank-you/',
            'description' => 'Optional. Redirect after a successful form submission.',
            'show_external' => false,
            'dynamic' => [
                'active' => true,
            ],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $redirectUrl = '';
        if (isset($settings['redirect_url']) && is_array($settings['redirect_url'])) {
            $redirectUrl = (string) ($settings['redirect_url']['url'] ?? '');
        }

        echo do_shortcode('[snc_notifuse_subscribe button_text="' . esc_attr((string) ($settings['button_text'] ?? 'Subscribe')) . '" list_ids="' . esc_attr((string) ($settings['list_ids'] ?? '')) . '" show_lists="' . esc_attr(! empty($settings['show_lists']) ? '1' : '0') . '" consent_text="' . esc_attr((string) ($settings['consent_text'] ?? '')) . '" consent_required="' . esc_attr((string) ($settings['consent_required'] ?? '')) . '" redirect_url="' . esc_attr($redirectUrl) . '"]');
    }
}
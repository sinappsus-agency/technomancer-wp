<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Notifuse\Elementor;

if (! class_exists('\Elementor\Widget_Base')) {
    return;
}

final class UnsubscribeWidget extends \Elementor\Widget_Base
{
    public function get_name(): string
    {
        return 'snc_notifuse_unsubscribe';
    }

    public function get_title(): string
    {
        return 'Notifuse Unsubscribe';
    }

    public function get_icon(): string
    {
        return 'eicon-close-circle-o';
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
            'default' => 'Unsubscribe',
        ]);
        $this->add_control('redirect_url', [
            'label' => 'Success Redirect URL',
            'type' => \Elementor\Controls_Manager::URL,
            'placeholder' => 'https://example.com/goodbye/',
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

        echo do_shortcode('[snc_notifuse_unsubscribe button_text="' . esc_attr((string) ($settings['button_text'] ?? 'Unsubscribe')) . '" redirect_url="' . esc_attr($redirectUrl) . '"]');
    }
}
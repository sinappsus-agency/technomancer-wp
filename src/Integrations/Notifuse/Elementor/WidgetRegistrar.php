<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Integrations\Notifuse\Elementor;

final class WidgetRegistrar
{
    public function register(): void
    {
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
    }

    public function registerWidgets($widgetsManager): void
    {
        if (! class_exists('\Elementor\Widget_Base')) {
            return;
        }

        $widgetsManager->register(new SubscribeWidget());
        $widgetsManager->register(new UnsubscribeWidget());
    }
}
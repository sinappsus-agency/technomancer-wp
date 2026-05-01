<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Core;

final class Settings
{
    public static function all(): array
    {
        $settings = get_option('tmwp_settings', []);

        return is_array($settings) ? $settings : [];
    }

    public static function get(string $key, $default = null)
    {
        $settings = self::all();

        return $settings[$key] ?? $default;
    }

    public static function update(array $settings): void
    {
        update_option('tmwp_settings', $settings);
    }
}

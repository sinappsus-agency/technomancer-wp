<?php

declare(strict_types=1);

namespace Sinappsus\N8nConnector\Core;

final class Updater
{
    /**
     * Keep a reference so the checker instance is not garbage-collected.
     *
     * @var object|null
     */
    private static $checker = null;

    public static function boot(): void
    {
        if (! self::loadLibrary()) {
            return;
        }

        if (! class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return;
        }

        $metadataUrl = trim((string) apply_filters('snc_update_metadata_url', ''));

        $source = (string) apply_filters(
            'snc_update_source',
            'https://github.com/sinappsus-agency/technomancer-wp/'
        );
        $source = trim($source);

        $updateEndpoint = $metadataUrl !== '' ? $metadataUrl : $source;

        if ($updateEndpoint === '') {
            return;
        }

        $slug = (string) apply_filters('snc_update_slug', 'technomancer-wp');
        $branch = (string) apply_filters('snc_update_branch', 'main');
        $token = trim((string) apply_filters('snc_update_token', ''));

        self::$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $updateEndpoint,
            SINAPPSUS_N8N_CONNECTOR_FILE,
            $slug
        );

        if (is_object(self::$checker) && method_exists(self::$checker, 'setBranch') && $branch !== '') {
            self::$checker->setBranch($branch);
        }

        if (
            is_object(self::$checker)
            && $metadataUrl === ''
            && stripos($source, 'github.com') !== false
            && method_exists(self::$checker, 'getVcsApi')
        ) {
            $api = self::$checker->getVcsApi();

            if (is_object($api) && method_exists($api, 'enableReleaseAssets')) {
                $api->enableReleaseAssets();
            }

            if ($token !== '' && is_object($api) && method_exists($api, 'setAuthentication')) {
                $api->setAuthentication($token);
            }
        }

        do_action('snc_update_checker_ready', self::$checker);
    }

    private static function loadLibrary(): bool
    {
        if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return true;
        }

        $paths = [
            SINAPPSUS_N8N_CONNECTOR_PATH . 'vendor/plugin-update-checker/plugin-update-checker.php',
            SINAPPSUS_N8N_CONNECTOR_PATH . 'lib/plugin-update-checker/plugin-update-checker.php',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;

                if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
                    return true;
                }
            }
        }

        return false;
    }
}

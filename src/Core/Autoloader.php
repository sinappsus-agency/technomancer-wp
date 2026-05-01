<?php

declare(strict_types=1);

namespace TechnomancerWp\Connector\Core;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $className): void
    {
        $prefix = 'TechnomancerWp\\Connector\\';

        if (strpos($className, $prefix) !== 0) {
            return;
        }

        $relativeClass = substr($className, strlen($prefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $filePath = TECHNOMANCER_WP_PATH . 'src/' . $relativePath;

        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
}

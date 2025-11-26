<?php

namespace WP_EvManager;

defined('ABSPATH') || exit;

final class Autoloader
{
    private static string $baseNamespace;
    private static string $baseDir;

    public static function register(string $baseNamespace, string $baseDir): void
    {
        self::$baseNamespace = trim($baseNamespace, '\\');
        self::$baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (strpos($class, self::$baseNamespace . '\\') !== 0) {
            return;
        }
        $relative = substr($class, strlen(self::$baseNamespace . '\\'));
        $path = self::$baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_readable($path)) {
            require $path;
        }
    }
}


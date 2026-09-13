<?php
declare(strict_types=1);

namespace App\Core;

/**
 * config/*.php ফাইলগুলো lazy-load করে ডট নোটেশনে পড়ায়:  Config::get('app.name')
 */
final class Config
{
    private static array $loaded = [];
    private static string $path = '';

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $file = array_shift($segments);

        if (!isset(self::$loaded[$file])) {
            $target = self::$path . '/' . $file . '.php';
            self::$loaded[$file] = is_file($target) ? require $target : [];
        }

        $value = self::$loaded[$file];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** রানটাইমে ওভাররাইড — মূলত টেস্ট ও ইনস্টলারের জন্য। */
    public static function set(string $file, array $values): void
    {
        self::$loaded[$file] = array_replace(self::$loaded[$file] ?? [], $values);
    }
}

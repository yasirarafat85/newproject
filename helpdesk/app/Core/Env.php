<?php
declare(strict_types=1);

namespace App\Core;

/**
 * .env ফাইলের সরল পার্সার। বুটের সবচেয়ে আগে চলে, তাই কোনো নির্ভরতা নেই।
 */
final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // ইনলাইন কমেন্ট ছাঁটা — কেবল উদ্ধৃতিহীন মানের ক্ষেত্রে
            if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
                $hash = strpos($value, ' #');
                if ($hash !== false) {
                    $value = substr($value, 0, $hash);
                }
                $value = trim($value);
            } elseif (strlen($value) >= 2) {
                $quote = $value[0];
                $end = strrpos($value, $quote);
                if ($end > 0) {
                    $value = substr($value, 1, $end - 1);
                }
            }

            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            return $default;
        }

        $value = self::$vars[$key] ?? $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            default            => $value,
        };
    }

    /** ইনস্টলার .env লেখার সময় ব্যবহার করে। */
    public static function set(string $key, mixed $value): void
    {
        self::$vars[$key] = $value;
    }

    public static function all(): array
    {
        return self::$vars;
    }
}

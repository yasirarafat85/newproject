<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal .env reader. Values are never interpolated, so bcrypt hashes
 * and tokens containing $ are kept exactly as written.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    public static function load(string $file): bool
    {
        if (!is_file($file) || !is_readable($file)) {
            return false;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $quote = $value[0] ?? '';
            if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote) && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            } else {
                // Strip trailing inline comment on unquoted values: KEY=value # note
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }

            self::$values[$key] = $value;
        }

        return true;
    }

    public static function get(string $key, string $default = ''): string
    {
        $value = self::$values[$key] ?? '';
        return $value === '' ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = strtolower(self::get($key));
        if ($value === '') {
            return $default;
        }
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function has(string $key): bool
    {
        return (self::$values[$key] ?? '') !== '';
    }
}

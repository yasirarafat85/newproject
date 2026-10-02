<?php

declare(strict_types=1);

namespace App;

/**
 * Updates single keys in the .env file (used by CLI setup scripts).
 */
final class EnvFile
{
    public static function path(): string
    {
        return getenv('FBBOT_ENV_FILE') ?: BASE_PATH . '/.env';
    }

    public static function set(string $key, string $value): void
    {
        $file = self::path();
        if (!is_file($file)) {
            throw new \RuntimeException('.env not found. Run php bin/setup.php first.');
        }

        $line = $key . "='" . str_replace("'", '', $value) . "'";
        $env = (string) file_get_contents($file);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        $env = preg_match($pattern, $env)
            ? preg_replace_callback($pattern, static fn () => $line, $env)
            : rtrim($env) . "\n$line\n";

        file_put_contents($file, $env);
        @chmod($file, 0600);
        Env::load($file);
    }
}

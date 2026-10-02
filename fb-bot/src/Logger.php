<?php

declare(strict_types=1);

namespace App;

/**
 * Writes log rows to SQLite. Secrets are scrubbed before anything is stored.
 */
final class Logger
{
    private const SECRET_ENV_KEYS = ['PAGE_ACCESS_TOKEN', 'APP_SECRET', 'AI_API_KEY', 'WEBHOOK_VERIFY_TOKEN', 'ADMIN_PASSWORD_HASH'];
    private const SECRET_FIELD_PATTERN = '/token|secret|password|passwd|api[_-]?key|authorization|signature|hash/i';

    /** @param array<string, mixed> $context */
    public static function info(string $channel, string $message, array $context = []): void
    {
        self::write('info', $channel, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::write('warning', $channel, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function error(string $channel, string $message, array $context = []): void
    {
        self::write('error', $channel, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public static function write(string $level, string $channel, string $message, array $context = []): void
    {
        $message = self::scrubString($message);
        $json = $context === [] ? null : json_encode(self::scrub($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        try {
            Database::run(
                'INSERT INTO logs (level, channel, message, context, created_at) VALUES (?, ?, ?, ?, ?)',
                [$level, $channel, $message, $json, Database::now()]
            );
        } catch (\Throwable $e) {
            // Database unavailable: fall back to a file outside the web root.
            @file_put_contents(
                BASE_PATH . '/storage/logs/fallback.log',
                sprintf("[%s] %s.%s: %s %s | db error: %s\n", Database::now(), $channel, $level, $message, $json ?? '', $e->getMessage()),
                FILE_APPEND
            );
        }
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_FIELD_PATTERN, $key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::scrub($value);
            } elseif (is_string($value)) {
                $data[$key] = self::scrubString($value);
            }
        }
        return $data;
    }

    public static function scrubString(string $value): string
    {
        foreach (self::SECRET_ENV_KEYS as $envKey) {
            $secret = Env::get($envKey);
            if (strlen($secret) >= 8) {
                $value = str_replace($secret, '[redacted]', $value);
            }
        }
        // access_token=... in URLs
        return (string) preg_replace('/(access_token=)[^&\s"]+/i', '$1[redacted]', $value);
    }
}

<?php
declare(strict_types=1);

namespace App\Core;

/**
 * দৈনিক ফাইলে লেখা সরল লগার (storage/logs/helpdesk-Y-m-d.log)।
 * Composer থাকলে Monolog-এ বদলানো যাবে; ইন্টারফেস একই রাখা হয়েছে।
 */
final class Logger
{
    private static string $path = '';

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function exception(\Throwable $e): void
    {
        self::write('ERROR', $e->getMessage(), [
            'type' => $e::class,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ]);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (self::$path === '' || !is_dir(self::$path)) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents(self::$path . '/helpdesk-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}

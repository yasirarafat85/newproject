<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const FLASH_KEY = '_flash';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) Config::get('app.session_secure', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('helpdesk_session');
        session_start();

        self::enforceIdleTimeout();
        self::ageFlash();
    }

    private static function enforceIdleTimeout(): void
    {
        $lifetime = (int) Config::get('app.session_lifetime', 120) * 60;
        $last = $_SESSION['_last_activity'] ?? null;

        if ($last !== null && (time() - (int) $last) > $lifetime) {
            self::invalidate();
        }

        $_SESSION['_last_activity'] = time();
    }

    /** আগের রিকোয়েস্টের flash এই রিকোয়েস্টে পড়া যাবে, পরেরটিতে আর নয়। */
    private static function ageFlash(): void
    {
        $_SESSION[self::FLASH_KEY]['read'] = $_SESSION[self::FLASH_KEY]['new'] ?? [];
        $_SESSION[self::FLASH_KEY]['new'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION[self::FLASH_KEY]['new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION[self::FLASH_KEY]['read'][$key] ?? $default;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION[self::FLASH_KEY]['read'][$key]);
    }

    /** লগইন/লগআউটে সেশন ফিক্সেশন ঠেকায়। */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

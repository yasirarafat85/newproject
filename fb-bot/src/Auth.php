<?php

declare(strict_types=1);

namespace App;

/**
 * Single-password admin login with session, CSRF token and IP lockout.
 */
final class Auth
{
    private const MAX_FAILURES = 5;
    private const LOCKOUT_MINUTES = 15;
    private const IDLE_TIMEOUT_SECONDS = 8 * 3600;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(Env::get('APP_URL'), 'https://');
        session_name('fbbot_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $last = $_SESSION['last_seen'] ?? 0;
        if (!empty($_SESSION['admin']) && time() - $last > self::IDLE_TIMEOUT_SECONDS) {
            self::logout();
            session_start();
        }
        $_SESSION['last_seen'] = time();
    }

    public static function isConfigured(): bool
    {
        return str_starts_with(Env::get('ADMIN_PASSWORD_HASH'), '$');
    }

    public static function check(): bool
    {
        return !empty($_SESSION['admin']);
    }

    /** Redirect to the login page unless logged in. */
    public static function require(): void
    {
        self::startSession();
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    /** @return string|null error message in Bengali, or null on success */
    public static function attempt(string $password, string $ip): ?string
    {
        if (!self::isConfigured()) {
            return '.env ফাইলে ADMIN_PASSWORD_HASH সেট করা নেই।';
        }

        $since = gmdate('Y-m-d H:i:s', time() - self::LOCKOUT_MINUTES * 60);
        $failures = (int) (Database::one(
            'SELECT COUNT(*) AS n FROM login_attempts WHERE ip = ? AND created_at >= ?',
            [$ip, $since]
        )['n'] ?? 0);

        if ($failures >= self::MAX_FAILURES) {
            Logger::warning('auth', 'Login blocked by lockout', ['ip' => $ip]);
            return 'অনেকবার ভুল পাসওয়ার্ড দেওয়া হয়েছে। ' . self::LOCKOUT_MINUTES . ' মিনিট পরে আবার চেষ্টা করুন।';
        }

        if (!password_verify($password, Env::get('ADMIN_PASSWORD_HASH'))) {
            Database::run('INSERT INTO login_attempts (ip, created_at) VALUES (?, ?)', [$ip, Database::now()]);
            Logger::warning('auth', 'Failed admin login', ['ip' => $ip]);
            return 'পাসওয়ার্ড সঠিক নয়।';
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['admin'] = true;
        $_SESSION['last_seen'] = time();
        Database::run('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        Logger::info('auth', 'Admin logged in', ['ip' => $ip]);
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}

<?php

declare(strict_types=1);

namespace App;

/**
 * Runtime settings stored in the database (changed from the admin panel).
 * Secrets stay in .env and never go here.
 */
final class Settings
{
    public static function get(string $name, ?string $default = null): ?string
    {
        $row = Database::one('SELECT value FROM settings WHERE name = ?', [$name]);
        return $row === null ? $default : $row['value'];
    }

    public static function set(string $name, ?string $value): void
    {
        $now = Database::now();
        // Check existence instead of relying on rowCount: MySQL reports 0 for unchanged rows.
        if (Database::one('SELECT 1 FROM settings WHERE name = ?', [$name]) !== null) {
            Database::run('UPDATE settings SET value = ?, updated_at = ? WHERE name = ?', [$value, $now, $name]);
        } else {
            Database::run('INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?)', [$name, $value, $now]);
        }
    }

    public static function botEnabled(): bool
    {
        return self::get('bot_enabled', '1') === '1';
    }
}

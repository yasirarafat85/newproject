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

    // Values below can be changed in the panel; .env gives the first default.

    public static function dryRun(): bool
    {
        return self::get('dry_run', Env::bool('DRY_RUN', true) ? '1' : '0') === '1';
    }

    public static function aiModel(): string
    {
        $model = (string) self::get('ai_model', Env::get('AI_MODEL', AiModels::DEFAULT_MODEL));
        return AiModels::get($model) !== null ? $model : AiModels::DEFAULT_MODEL;
    }

    public static function aiEffort(): string
    {
        $effort = (string) self::get('ai_effort', 'low');
        return in_array($effort, AiModels::EFFORTS, true) ? $effort : 'low';
    }

    public static function replyTone(): string
    {
        return (string) self::get('reply_tone', 'বন্ধুসুলভ ও ভদ্র, ছোট করে');
    }

    public static function handoffContact(): string
    {
        return (string) self::get('handoff_contact', Env::get('HANDOFF_CONTACT'));
    }

    public static function repliesPerUserPerHour(): int
    {
        return max(1, (int) self::get('replies_per_user_per_hour', (string) Env::int('REPLIES_PER_USER_PER_HOUR', 5)));
    }

    public static function maxRepliesPerDay(): int
    {
        return max(1, (int) self::get('max_replies_per_day', (string) Env::int('MAX_REPLIES_PER_DAY', 300)));
    }
}

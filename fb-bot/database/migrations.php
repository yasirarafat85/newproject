<?php

declare(strict_types=1);

/*
 * Schema migrations, applied in order and recorded in schema_migrations.
 * Never edit a migration that has already shipped; add a new one instead.
 *
 * Portability rules (for a later move to MySQL):
 *  - {ID} is replaced with the auto-increment primary key for the driver.
 *  - Only INTEGER and TEXT columns; timestamps are UTC 'Y-m-d H:i:s' text.
 *  - No reserved words as column names (e.g. use `name`, not `key`).
 */

return [
    1 => [
        // Key/value runtime settings (bot on/off, worker heartbeat, ...)
        "CREATE TABLE IF NOT EXISTS settings (
            name TEXT NOT NULL PRIMARY KEY,
            value TEXT,
            updated_at TEXT NOT NULL
        )",

        // Every inbound event, outbound call and error. Never store tokens here.
        "CREATE TABLE IF NOT EXISTS logs (
            id {ID},
            level TEXT NOT NULL,
            channel TEXT NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            created_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_logs_created ON logs (created_at)",

        // Webhook events already accepted, so Facebook retries are ignored.
        "CREATE TABLE IF NOT EXISTS processed_events (
            event_key TEXT NOT NULL PRIMARY KEY,
            created_at TEXT NOT NULL
        )",

        // Slow work queued by the webhook and done by the cron worker.
        "CREATE TABLE IF NOT EXISTS jobs (
            id {ID},
            type TEXT NOT NULL,
            payload TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            run_after TEXT NOT NULL,
            last_error TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_jobs_status ON jobs (status, run_after)",

        // Page comments and what the bot did with each one.
        // status: new | replied | dry_run | needs_human | skipped | failed | resolved
        "CREATE TABLE IF NOT EXISTS comments (
            id {ID},
            comment_id TEXT NOT NULL UNIQUE,
            post_id TEXT,
            parent_id TEXT,
            from_id TEXT,
            from_name TEXT,
            message TEXT,
            status TEXT NOT NULL DEFAULT 'new',
            reply_text TEXT,
            reply_comment_id TEXT,
            note TEXT,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_comments_status ON comments (status, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_comments_from ON comments (from_id, created_at)",

        // Failed admin logins, for lockout.
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id {ID},
            ip TEXT NOT NULL,
            created_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts (ip, created_at)",
    ],

    2 => [
        // What the AI decided for each comment and which model answered.
        "ALTER TABLE comments ADD COLUMN ai_action TEXT",
        "ALTER TABLE comments ADD COLUMN ai_model TEXT",
        "ALTER TABLE comments ADD COLUMN cost_micros INTEGER",
        "ALTER TABLE comments ADD COLUMN replied_at TEXT",

        // Every AI call with token usage and estimated cost (USD millionths).
        // purpose: comment | test
        "CREATE TABLE IF NOT EXISTS ai_calls (
            id {ID},
            purpose TEXT NOT NULL,
            comment_id TEXT,
            model TEXT NOT NULL,
            input_tokens INTEGER NOT NULL DEFAULT 0,
            output_tokens INTEGER NOT NULL DEFAULT 0,
            cache_read_tokens INTEGER NOT NULL DEFAULT 0,
            cache_write_tokens INTEGER NOT NULL DEFAULT 0,
            cost_micros INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )",
        "CREATE INDEX IF NOT EXISTS idx_ai_calls_created ON ai_calls (created_at)",
    ],

    3 => [
        // Who posted the reply: ai (automatic), approved (AI draft approved
        // by the owner) or admin (written by the owner in the panel).
        "ALTER TABLE comments ADD COLUMN replied_by TEXT",
    ],
];

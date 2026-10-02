<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal database job queue for the cron worker.
 * status: pending | running | done | failed
 */
final class Jobs
{
    public const MAX_ATTEMPTS = 4;
    /** A job stuck in "running" this long (worker crashed) is picked up again. */
    private const STALE_RUNNING_SECONDS = 600;

    /** @param array<string, mixed> $payload */
    public static function push(string $type, array $payload, int $delaySeconds = 0): void
    {
        $now = Database::now();
        Database::run(
            'INSERT INTO jobs (type, payload, status, attempts, run_after, created_at, updated_at) VALUES (?, ?, ?, 0, ?, ?, ?)',
            [$type, json_encode($payload, JSON_UNESCAPED_UNICODE), 'pending', gmdate('Y-m-d H:i:s', time() + $delaySeconds), $now, $now]
        );
    }

    /**
     * Claims the next due job, or null when there is none.
     * @return array<string, mixed>|null
     */
    public static function claim(): ?array
    {
        $now = Database::now();
        $stale = gmdate('Y-m-d H:i:s', time() - self::STALE_RUNNING_SECONDS);
        $job = Database::one(
            "SELECT * FROM jobs
             WHERE (status = 'pending' AND run_after <= ?) OR (status = 'running' AND updated_at <= ?)
             ORDER BY id LIMIT 1",
            [$now, $stale]
        );
        if ($job === null) {
            return null;
        }

        // Guard against another worker claiming the same row.
        $claimed = Database::run(
            "UPDATE jobs SET status = 'running', attempts = attempts + 1, updated_at = ? WHERE id = ? AND status = ? AND updated_at = ?",
            [$now, $job['id'], $job['status'], $job['updated_at']]
        );
        if ($claimed === 0) {
            return null;
        }

        $job['attempts'] = (int) $job['attempts'] + 1;
        $job['payload'] = json_decode((string) $job['payload'], true) ?: [];
        return $job;
    }

    public static function done(int $id): void
    {
        Database::run("UPDATE jobs SET status = 'done', last_error = NULL, updated_at = ? WHERE id = ?", [Database::now(), $id]);
    }

    /** Retries with backoff (1, 4, 9 minutes) unless out of attempts or not retryable. */
    public static function fail(array $job, string $error, bool $retryable): bool
    {
        $willRetry = $retryable && $job['attempts'] < self::MAX_ATTEMPTS;
        $delay = 60 * $job['attempts'] ** 2;
        Database::run(
            'UPDATE jobs SET status = ?, last_error = ?, run_after = ?, updated_at = ? WHERE id = ?',
            [$willRetry ? 'pending' : 'failed', mb_substr(Logger::scrubString($error), 0, 1000), gmdate('Y-m-d H:i:s', time() + $delay), Database::now(), $job['id']]
        );
        return $willRetry;
    }
}

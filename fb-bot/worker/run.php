<?php

declare(strict_types=1);

/*
 * Background worker, run by cron every minute:
 *   * * * * * /usr/local/bin/php /home/USER/path/fb-bot/worker/run.php >/dev/null 2>&1
 *
 * Records a heartbeat, then works through queued jobs (AI replies to
 * comments) for up to ~50 seconds so runs don't overlap.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap.php';

use App\AiException;
use App\AnthropicAiClient;
use App\CommentResponder;
use App\Database;
use App\GraphException;
use App\Health;
use App\Jobs;
use App\Logger;
use App\ReplyGenerator;
use App\Settings;

const TIME_BUDGET_SECONDS = 50;

// Prevent two runs overlapping if one takes longer than a minute.
$lock = fopen(BASE_PATH . '/storage/worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

Health::recordWorkerRun();
housekeeping();

// Without an AI key or the SDK, leave jobs queued (comments stay "new") until fixed.
if (!App\Env::has('AI_API_KEY') || !class_exists(Anthropic\Client::class)) {
    if (Settings::get('ai_missing_logged') !== gmdate('Y-m-d H')) {
        Settings::set('ai_missing_logged', gmdate('Y-m-d H'));
        Logger::warning('worker', 'AI not configured: set AI_API_KEY in .env and run composer install. Jobs are waiting.');
    }
    exit(0);
}

$started = time();
$responder = null;

while (time() - $started < TIME_BUDGET_SECONDS && ($job = Jobs::claim()) !== null) {
    try {
        if ($job['type'] !== 'reply_comment') {
            Jobs::fail($job, 'Unknown job type: ' . $job['type'], false);
            continue;
        }
        $responder ??= new CommentResponder(new ReplyGenerator(AnthropicAiClient::fromEnv()));
        $responder->handle((string) ($job['payload']['comment_id'] ?? ''), !empty($job['payload']['manual']));
        Jobs::done((int) $job['id']);
    } catch (AiException | GraphException $e) {
        $retryable = $e instanceof AiException ? $e->retryable : ($e->httpStatus === 0 || $e->httpStatus >= 500);
        $willRetry = Jobs::fail($job, $e->getMessage(), $retryable);
        Logger::error('worker', ($e instanceof AiException ? 'AI' : 'Facebook') . ' error: ' . $e->getMessage(), [
            'job_id' => $job['id'],
            'attempt' => $job['attempts'],
            'will_retry' => $willRetry,
        ]);
        if (!$willRetry) {
            CommentResponder::markFailed((string) ($job['payload']['comment_id'] ?? ''), $e->getMessage());
        }
    } catch (Throwable $e) {
        Jobs::fail($job, $e->getMessage(), false);
        Logger::error('worker', 'Job crashed: ' . $e->getMessage(), ['job_id' => $job['id'], 'file' => $e->getFile() . ':' . $e->getLine()]);
        CommentResponder::markFailed((string) ($job['payload']['comment_id'] ?? ''), $e->getMessage());
    }
}

flock($lock, LOCK_UN);
fclose($lock);

/** Once a day: drop old dedupe keys, finished jobs and old logs. */
function housekeeping(): void
{
    $today = gmdate('Y-m-d');
    if (Settings::get('housekeeping_date') === $today) {
        return;
    }
    Settings::set('housekeeping_date', $today);
    $days = static fn (int $n): string => gmdate('Y-m-d H:i:s', time() - $n * 86400);
    Database::run('DELETE FROM processed_events WHERE created_at < ?', [$days(30)]);
    Database::run("DELETE FROM jobs WHERE status = 'done' AND updated_at < ?", [$days(14)]);
    Database::run('DELETE FROM logs WHERE created_at < ?', [$days(60)]);
    Database::run('DELETE FROM login_attempts WHERE created_at < ?', [$days(1)]);
}

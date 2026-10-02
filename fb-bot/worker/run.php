<?php

declare(strict_types=1);

/*
 * Background worker, run by cron every minute:
 *   * * * * * /usr/local/bin/php /home/USER/path/fb-bot/worker/run.php >/dev/null 2>&1
 *
 * Phase 0: records a heartbeat so the dashboard can show that cron works
 * and how often it really runs. Job processing is added in Phase 2.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../bootstrap.php';

use App\Health;

// Prevent two runs overlapping if one takes longer than a minute.
$lock = fopen(BASE_PATH . '/storage/worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

Health::recordWorkerRun();

flock($lock, LOCK_UN);
fclose($lock);

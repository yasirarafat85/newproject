<?php

declare(strict_types=1);

namespace App;

/**
 * System checks shown on the dashboard so setup problems are visible
 * without a terminal.
 */
final class Health
{
    /** Worker is considered alive if it ran within this many seconds. */
    public const WORKER_STALE_SECONDS = 180;

    /**
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    public static function checks(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP ভার্সন',
            'ok' => PHP_VERSION_ID >= 80200,
            'detail' => PHP_VERSION . (PHP_VERSION_ID >= 80200 ? '' : ' — cPanel-এ 8.2 বা বেশি বাছাই করুন'),
        ];

        foreach (['pdo_sqlite', 'curl', 'mbstring'] as $ext) {
            $loaded = extension_loaded($ext);
            $checks[] = ['label' => "PHP extension: $ext", 'ok' => $loaded, 'detail' => $loaded ? 'আছে' : 'নেই — cPanel-এ চালু করুন'];
        }

        $checks[] = ['label' => '.env ফাইল', 'ok' => Env::has('APP_URL'), 'detail' => Env::has('APP_URL') ? 'পাওয়া গেছে' : 'পাওয়া যায়নি বা APP_URL খালি'];

        try {
            $tables = Migrator::tables(Database::pdo());
            $checks[] = ['label' => 'ডাটাবেস', 'ok' => true, 'detail' => 'ঠিক আছে (' . count($tables) . 'টি টেবিল)'];
        } catch (\Throwable $e) {
            $checks[] = ['label' => 'ডাটাবেস', 'ok' => false, 'detail' => 'সমস্যা: ' . Logger::scrubString($e->getMessage())];
        }

        foreach (['storage' => BASE_PATH . '/storage', 'public/media' => BASE_PATH . '/public/media'] as $label => $dir) {
            $ok = is_dir($dir) && is_writable($dir);
            $checks[] = ['label' => "লেখার অনুমতি: $label", 'ok' => $ok, 'detail' => $ok ? 'আছে' : 'নেই — ফোল্ডারের permission 755 দিন'];
        }

        $checks[] = self::workerCheck();

        $missing = array_values(array_filter(
            ['APP_ID', 'APP_SECRET', 'WEBHOOK_VERIFY_TOKEN', 'PAGE_ID', 'PAGE_ACCESS_TOKEN'],
            static fn (string $key) => !Env::has($key)
        ));
        $checks[] = [
            'label' => 'Facebook সেটিং (.env)',
            'ok' => $missing === [],
            'detail' => $missing === [] ? 'সব পূরণ করা আছে' : 'খালি: ' . implode(', ', $missing),
        ];

        $sdk = class_exists(\Anthropic\Client::class);
        $checks[] = ['label' => 'AI লাইব্রেরি (composer)', 'ok' => $sdk, 'detail' => $sdk ? 'ইনস্টল করা আছে' : 'নেই — Terminal-এ composer install --no-dev চালান'];
        $checks[] = ['label' => 'AI API key (.env)', 'ok' => Env::has('AI_API_KEY'), 'detail' => Env::has('AI_API_KEY') ? 'সেট করা আছে' : 'AI_API_KEY খালি'];
        $kb = KnowledgeBase::isFilled();
        $checks[] = ['label' => 'Knowledge Base', 'ok' => $kb, 'detail' => $kb ? 'তথ্য আছে' : 'খালি — প্যানেলের Knowledge Base পেজে লিখুন'];

        $lastEvent = null;
        try {
            $lastEvent = Settings::get('webhook_last_event');
        } catch (\Throwable) {
        }
        $checks[] = [
            'label' => 'Facebook থেকে শেষ ইভেন্ট',
            'ok' => $lastEvent !== null,
            'detail' => $lastEvent === null ? 'এখনো কিছু আসেনি' : View::localTime($lastEvent),
        ];

        return $checks;
    }

    /** @return array{label: string, ok: bool, detail: string} */
    public static function workerCheck(): array
    {
        $last = null;
        try {
            $last = Settings::get('worker_last_run');
        } catch (\Throwable) {
        }

        if ($last === null) {
            return ['label' => 'Worker (cron)', 'ok' => false, 'detail' => 'এখনো একবারও চলেনি — cron সেট করুন'];
        }

        $age = time() - strtotime($last . ' UTC');
        $interval = self::cronInterval();
        $detail = 'শেষ চলেছে ' . View::localTime($last);
        if ($interval !== null) {
            $detail .= ' · প্রায় প্রতি ' . $interval . ' সেকেন্ডে চলছে';
        }

        return ['label' => 'Worker (cron)', 'ok' => $age <= self::WORKER_STALE_SECONDS, 'detail' => $detail];
    }

    /** Average seconds between the last few worker runs, to learn the real cron frequency. */
    public static function cronInterval(): ?int
    {
        $history = json_decode((string) Settings::get('worker_run_history', '[]'), true);
        if (!is_array($history) || count($history) < 2) {
            return null;
        }
        $gaps = [];
        for ($i = 1; $i < count($history); $i++) {
            $gaps[] = $history[$i] - $history[$i - 1];
        }
        return (int) round(array_sum($gaps) / count($gaps));
    }

    /** Called by the worker on every run. */
    public static function recordWorkerRun(): void
    {
        $history = json_decode((string) Settings::get('worker_run_history', '[]'), true);
        $history = is_array($history) ? $history : [];
        $history[] = time();
        Settings::set('worker_run_history', json_encode(array_slice($history, -6)));
        Settings::set('worker_last_run', Database::now());
    }
}

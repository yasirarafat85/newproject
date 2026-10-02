<?php

declare(strict_types=1);

/*
 * Self-checks that need no Facebook or AI account:
 *   php tests/run.php
 * Uses a throwaway database and .env in the system temp folder.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

$tmp = sys_get_temp_dir() . '/fbbot-test-' . bin2hex(random_bytes(4));
mkdir($tmp);
$envFile = "$tmp/.env";
file_put_contents($envFile, implode("\n", [
    'APP_URL=https://bot.example.test',
    "DB_PATH=$tmp/test.sqlite",
    "ADMIN_PASSWORD_HASH='" . password_hash('correct horse battery', PASSWORD_DEFAULT) . "'",
    'PAGE_ACCESS_TOKEN=EAAB-very-secret-token-123',
    'APP_SECRET=test-app-secret-456',
    'WEBHOOK_VERIFY_TOKEN=verify-me',
    'PAGE_ID=111',
    'DRY_RUN=true # inline comment',
    '',
]));
putenv("FBBOT_ENV_FILE=$envFile");

require dirname(__DIR__) . '/bootstrap.php';

use App\Auth;
use App\Database;
use App\Env;
use App\Health;
use App\Logger;
use App\Migrator;
use App\Settings;
use App\Webhook;

$failures = 0;
function check(string $name, bool $ok): void
{
    global $failures;
    echo ($ok ? '  PASS ' : '  FAIL ') . $name . "\n";
    if (!$ok) {
        $failures++;
    }
}

echo "Env\n";
check('bcrypt hash with $ is read verbatim', str_starts_with(Env::get('ADMIN_PASSWORD_HASH'), '$2y$'));
check('inline comment stripped from unquoted value', Env::bool('DRY_RUN') === true && Env::get('DRY_RUN') === 'true');
check('missing key falls back to default', Env::get('NOPE', 'x') === 'x');

echo "Database\n";
$pdo = Database::pdo();
$tables = Migrator::tables($pdo);
foreach (['settings', 'logs', 'processed_events', 'jobs', 'comments', 'login_attempts', 'schema_migrations'] as $t) {
    check("table $t exists", in_array($t, $tables, true));
}
check('migrations are idempotent', Migrator::run($pdo) === []);

echo "Settings\n";
Settings::set('bot_enabled', '1');
Settings::set('bot_enabled', '1'); // same value twice must not fail
Settings::set('bot_enabled', '0');
check('setting updates in place', Settings::get('bot_enabled') === '0' && !Settings::botEnabled());

echo "Logger\n";
Logger::info('test', 'calling https://graph.facebook.com/me?access_token=EAAB-very-secret-token-123&x=1', [
    'access_token' => 'abc',
    'nested' => ['note' => 'token is EAAB-very-secret-token-123'],
]);
$row = Database::one('SELECT message, context FROM logs ORDER BY id DESC LIMIT 1');
$stored = $row['message'] . $row['context'];
check('secret token never stored', !str_contains($stored, 'very-secret'));
check('token field redacted', str_contains((string) $row['context'], '"access_token":"[redacted]"'));

echo "Auth\n";
$_SESSION = [];
check('wrong password rejected', Auth::attempt('wrong', '10.0.0.1') !== null);
check('right password accepted', Auth::attempt('correct horse battery', '10.0.0.2') === null && Auth::check());
$_SESSION = [];
for ($i = 0; $i < 5; $i++) {
    Auth::attempt('wrong', '10.0.0.3');
}
check('lockout after 5 failures, even with right password', Auth::attempt('correct horse battery', '10.0.0.3') !== null && !Auth::check());
$_SESSION = [];
$token = Auth::csrfToken();
check('csrf token verifies', Auth::verifyCsrf($token) && !Auth::verifyCsrf('bad') && !Auth::verifyCsrf(null));

echo "Worker heartbeat\n";
check('worker reported as never run', Health::workerCheck()['ok'] === false);
Health::recordWorkerRun();
check('worker reported alive after a run', Health::workerCheck()['ok'] === true);

echo "Webhook verification\n";
check('correct verify token returns challenge', Webhook::verify(['hub_mode' => 'subscribe', 'hub_verify_token' => 'verify-me', 'hub_challenge' => '12345']) === [200, '12345']);
check('wrong verify token rejected', Webhook::verify(['hub_mode' => 'subscribe', 'hub_verify_token' => 'nope', 'hub_challenge' => '12345'])[0] === 403);
check('unsafe challenge rejected', Webhook::verify(['hub_mode' => 'subscribe', 'hub_verify_token' => 'verify-me', 'hub_challenge' => '<script>'])[0] === 403);

echo "Webhook events\n";
function commentPayload(string $commentId, string $fromId, string $verb = 'add', string $message = 'দাম কত?'): string
{
    return json_encode(['object' => 'page', 'entry' => [['id' => '111', 'time' => time(), 'changes' => [[
        'field' => 'feed',
        'value' => [
            'item' => 'comment', 'verb' => $verb, 'comment_id' => $commentId, 'post_id' => '111_222',
            'parent_id' => '111_222', 'from' => ['id' => $fromId, 'name' => 'Rahim'], 'message' => $message,
        ],
    ]]]]], JSON_UNESCAPED_UNICODE);
}
function sign(string $body): string
{
    return 'sha256=' . hash_hmac('sha256', $body, 'test-app-secret-456');
}
$countComments = static fn (): int => (int) Database::one('SELECT COUNT(*) AS n FROM comments')['n'];

$body = commentPayload('222_1', '999');
check('missing signature rejected', Webhook::receive($body, null)[0] === 403);
check('wrong signature rejected', Webhook::receive($body, 'sha256=' . str_repeat('0', 64))[0] === 403);
check('tampered body rejected', Webhook::receive(str_replace('Rahim', 'Karim', $body), sign($body))[0] === 403);
check('nothing stored from rejected requests', $countComments() === 0);

check('valid comment accepted', Webhook::receive($body, sign($body)) === [200, 'EVENT_RECEIVED']);
$row = Database::one("SELECT * FROM comments WHERE comment_id = '222_1'");
check('comment stored as new with text and author', $row !== null && $row['status'] === 'new' && $row['message'] === 'দাম কত?' && $row['from_name'] === 'Rahim');

check('retried delivery accepted', Webhook::receive($body, sign($body))[0] === 200);
check('retried delivery stored only once', $countComments() === 1);

$own = commentPayload('222_2', '111');
Webhook::receive($own, sign($own));
check("Page's own comment marked skipped", Database::one("SELECT status FROM comments WHERE comment_id = '222_2'")['status'] === 'skipped');

$edit = commentPayload('222_1', '999', 'edited', 'changed');
Webhook::receive($edit, sign($edit));
check('edited comment does not change or add rows', $countComments() === 2 && Database::one("SELECT message FROM comments WHERE comment_id = '222_1'")['message'] === 'দাম কত?');

$other = json_encode(['object' => 'instagram', 'entry' => []]);
check('non-page object acknowledged and ignored', Webhook::receive($other, sign($other))[0] === 200 && $countComments() === 2);

$big = str_repeat('x', Webhook::MAX_BODY_BYTES + 1);
check('oversized body rejected', Webhook::receive($big, sign($big))[0] === 413);

check('last event time recorded', Settings::get('webhook_last_event') !== null);
check('app secret never stored in logs', Database::one("SELECT COUNT(*) AS n FROM logs WHERE context LIKE '%test-app-secret%' OR message LIKE '%test-app-secret%'")['n'] == 0);

// Clean up
Database::setPdo(null);
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);

echo $failures === 0 ? "\nAll tests passed.\n" : "\n$failures test(s) failed.\n";
exit($failures === 0 ? 0 : 1);

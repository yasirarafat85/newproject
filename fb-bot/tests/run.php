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

if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
    exit("Composer packages missing. Run: composer install --no-dev\n");
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
    'AI_API_KEY=sk-ant-test-key-xyz-789',
    "KB_PATH=$tmp/knowledge.md",
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
use App\AiClient;
use App\AiException;
use App\AiModels;
use App\AiResponse;
use App\AnthropicAiClient;
use App\CommentActions;
use App\CommentResponder;
use App\GraphException;
use App\Jobs;
use App\KnowledgeBase;
use App\ReplyGenerator;
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

echo "Job queue\n";
check('new comment from a person queued a reply job', (int) Database::one("SELECT COUNT(*) AS n FROM jobs WHERE type = 'reply_comment'")['n'] === 1);
check("Page's own comment queued no job", Database::one("SELECT COUNT(*) AS n FROM jobs WHERE payload LIKE '%222_2%'")['n'] == 0);

/** Fake AI: returns queued answers and records requests. */
final class FakeAi implements AiClient
{
    /** @var list<AiResponse|AiException> */
    public array $queue = [];
    /** @var list<array{system: string, user: string, model: string, effort: ?string}> */
    public array $calls = [];

    public function complete(string $system, string $user, array $schema, string $model, ?string $effort): AiResponse
    {
        $this->calls[] = compact('system', 'user', 'model', 'effort');
        $next = array_shift($this->queue) ?? throw new RuntimeException('FakeAi queue empty');
        if ($next instanceof AiException) {
            throw $next;
        }
        return $next;
    }

    public function answer(string $action, string $reply, string $reason = 'test', ?string $stop = 'end_turn'): void
    {
        $this->queue[] = new AiResponse(json_encode(compact('action', 'reply', 'reason'), JSON_UNESCAPED_UNICODE), $stop, 'claude-opus-5-5', 3000, 120, 0, 0);
    }
}

$ai = new FakeAi();
$posted = [];
$postFails = 0;
$poster = static function (string $commentId, string $message) use (&$posted, &$postFails): string {
    if ($postFails > 0) {
        $postFails--;
        throw new GraphException('Service unavailable', 503);
    }
    $posted[] = [$commentId, $message];
    return $commentId . '_reply';
};
$responder = new CommentResponder(new ReplyGenerator($ai), $poster);

$addComment = static function (string $id, string $message, string $from = '999', ?string $createdAt = null): void {
    $now = $createdAt ?? Database::now();
    Database::run(
        "INSERT INTO comments (comment_id, post_id, from_id, from_name, message, status, created_at, updated_at) VALUES (?, '111_222', ?, 'Rahim', ?, 'new', ?, ?)",
        [$id, $from, $message, $now, $now]
    );
};
$comment = static fn (string $id): array => Database::one('SELECT * FROM comments WHERE comment_id = ?', [$id]);

echo "Knowledge base\n";
check('template counts as empty', !KnowledgeBase::isFilled());
$addComment('c_empty_kb', 'দাম কত?');
$responder->handle('c_empty_kb');
check('empty knowledge base skips without calling AI', $comment('c_empty_kb')['status'] === 'skipped' && $ai->calls === []);
KnowledgeBase::write("# Products\nটি-শার্ট - ৫৫০ টাকা\n<!-- private note -->\n## Delivery\nঢাকায় ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা\n");
check('filled knowledge base detected', KnowledgeBase::isFilled());
check('HTML comments removed from prompt', !str_contains(KnowledgeBase::forPrompt(), 'private note'));
check('system prompt contains knowledge base', str_contains(ReplyGenerator::systemPrompt(), 'টি-শার্ট - ৫৫০ টাকা'));

echo "AI replies (DRY_RUN)\n";
Settings::set('bot_enabled', '1');
Settings::set('dry_run', '1');
Settings::set('ai_model', 'claude-sonnet-5-5');
$ai->answer('reply', 'টি-শার্টের দাম ৫৫০ টাকা।');
$addComment('c1', 'ভাই টি-শার্টের দাম কত?');
$responder->handle('c1');
$c1 = $comment('c1');
check('dry run stores reply without posting', $c1['status'] === 'dry_run' && $c1['reply_text'] === 'টি-শার্টের দাম ৫৫০ টাকা।' && $posted === []);
check('model from settings is used', $ai->calls[0]['model'] === 'claude-sonnet-5-5' && $ai->calls[0]['effort'] === 'low');
check('comment is wrapped as untrusted data', str_contains($ai->calls[0]['user'], "<comment>\nভাই টি-শার্টের দাম কত?\n</comment>"));
check('cost recorded', (int) $c1['cost_micros'] > 0 && Database::one("SELECT COUNT(*) AS n FROM ai_calls WHERE comment_id = 'c1'")['n'] == 1);
$responder->handle('c1');
check('handled comment is not answered twice', count($ai->calls) === 1);

echo "AI replies (live)\n";
Settings::set('dry_run', '0');
$ai->answer('reply', '**ঢাকার বাইরে** ডেলিভারি চার্জ ১২০ টাকা।');
$addComment('c2', 'ঢাকার বাইরে ডেলিভারি কত?', '777');
$responder->handle('c2');
$c2 = $comment('c2');
check('live mode posts reply under the comment', $c2['status'] === 'replied' && $posted[0][0] === 'c2' && $c2['reply_comment_id'] === 'c2_reply');
check('markdown stripped from reply', $posted[0][1] === 'ঢাকার বাইরে ডেলিভারি চার্জ ১২০ টাকা।');

$ai->answer('handoff', '', 'Order status not in knowledge base');
$addComment('c3', 'আমার অর্ডার কোথায়?', '778');
$responder->handle('c3');
check('handoff posts polite reply and marks needs_human', $comment('c3')['status'] === 'needs_human' && str_contains($posted[1][1], 'যোগাযোগ'));

$ai->answer('ignore', 'should not be used');
$addComment('c4', 'www.spam.example buy now', '779');
$responder->handle('c4');
check('ignore posts nothing', $comment('c4')['status'] === 'skipped' && count($posted) === 2 && $comment('c4')['reply_text'] === null);

$ai->answer('reply', '', 'x', 'refusal');
$addComment('c5', 'something', '780');
$responder->handle('c5');
check('refusal becomes handoff', $comment('c5')['ai_action'] === 'handoff');

$ai->queue[] = new AiResponse('not json', 'end_turn', 'claude-opus-5-5');
$addComment('c6', 'hello?', '781');
$responder->handle('c6');
check('unreadable AI answer becomes handoff', $comment('c6')['ai_action'] === 'handoff');

echo "Skips and limits\n";
$addComment('c7', '', '782');
$responder->handle('c7');
check('empty comment (sticker) skipped', $comment('c7')['status'] === 'skipped');
$addComment('c8', 'দাম?', '783', gmdate('Y-m-d H:i:s', time() - 2 * 86400));
$responder->handle('c8');
check('old comment skipped', $comment('c8')['status'] === 'skipped');
Settings::set('bot_enabled', '0');
$addComment('c9', 'দাম?', '784');
$responder->handle('c9');
check('bot off skips', $comment('c9')['status'] === 'skipped' && str_contains($comment('c9')['note'], 'বন্ধ'));
Settings::set('bot_enabled', '1');

Settings::set('replies_per_user_per_hour', '2');
$callsBefore = count($ai->calls);
$ai->answer('reply', 'এক');
$ai->answer('reply', 'দুই');
foreach (['u1', 'u2', 'u3'] as $id) {
    $addComment($id, 'দাম কত?', '900');
    $responder->handle($id);
}
check('per-user hourly limit stops the third reply', count($ai->calls) - $callsBefore === 2 && $comment('u3')['status'] === 'skipped');
Settings::set('replies_per_user_per_hour', '5');

Settings::set('max_replies_per_day', (string) Database::one("SELECT COUNT(*) AS n FROM ai_calls WHERE purpose = 'comment'")['n']);
$addComment('d1', 'দাম কত?', '901');
$responder->handle('d1');
check('daily limit stops replies', $comment('d1')['status'] === 'skipped');
Settings::set('max_replies_per_day', '300');

echo "Retries\n";
$ai->answer('reply', 'ঢাকায় ডেলিভারি ৬০ টাকা।');
$addComment('r1', 'ঢাকায় ডেলিভারি?', '902');
$postFails = 1;
$threw = false;
try {
    $responder->handle('r1');
} catch (GraphException $e) {
    $threw = $e->httpStatus === 503;
}
$callsAfterFirst = count($ai->calls);
check('Facebook error is raised for retry, AI answer kept', $threw && $comment('r1')['status'] === 'new' && $comment('r1')['reply_text'] === 'ঢাকায় ডেলিভারি ৬০ টাকা।');
$responder->handle('r1');
check('retry posts without a second AI call', $comment('r1')['status'] === 'replied' && count($ai->calls) === $callsAfterFirst);

Jobs::push('reply_comment', ['comment_id' => 'x']);
$job = Jobs::claim();
while ($job !== null && $job['payload']['comment_id'] !== 'x') {
    Jobs::done((int) $job['id']);
    $job = Jobs::claim();
}
check('job claimed once', $job !== null && Jobs::claim() === null);
check('retryable failure re-queued', Jobs::fail($job, 'overloaded', true) === true);
check('re-queued job waits for backoff', Jobs::claim() === null);
Database::run("UPDATE jobs SET run_after = ? WHERE id = ?", [gmdate('Y-m-d H:i:s', time() - 1), $job['id']]);
$job = Jobs::claim();
check('non-retryable failure stops', $job !== null && Jobs::fail($job, 'bad key', false) === false
    && Database::one('SELECT status FROM jobs WHERE id = ?', [$job['id']])['status'] === 'failed');
CommentResponder::markFailed('c_missing', 'x');
$addComment('f1', 'test', '903');
CommentResponder::markFailed('f1', 'API key rejected: sk-ant-test-key-xyz-789');
check('failed comment note never contains the API key', $comment('f1')['status'] === 'failed' && !str_contains($comment('f1')['note'], 'sk-ant-test'));

echo "Owner actions (queue)\n";
$adminPosted = [];
$adminFails = 0;
$actions = new CommentActions(static function (string $commentId, string $message) use (&$adminPosted, &$adminFails): string {
    if ($adminFails > 0) {
        $adminFails--;
        throw new GraphException('(#200) Permissions error', 403);
    }
    $adminPosted[] = [$commentId, $message];
    return $commentId . '_admin';
});
$setStatus = static fn (string $id, string $status, ?string $aiAction = null, ?string $reply = null) => Database::run(
    'UPDATE comments SET status = ?, ai_action = ?, reply_text = ? WHERE comment_id = ?', [$status, $aiAction, $reply, $id]
);

$addComment('q1', 'দাম কত?', '950');
$setStatus('q1', 'dry_run', 'reply', 'দাম ৫৫০ টাকা।');
check('queue counts dry run', CommentActions::queueCount() >= 1);
check('approve posts edited AI draft', $actions->reply('q1', '  দাম ৫৫০ টাকা। ধন্যবাদ!  ', true) === null
    && end($adminPosted) === ['q1', 'দাম ৫৫০ টাকা। ধন্যবাদ!']);
$q1 = $comment('q1');
check('approved reply marked replied by approval', $q1['status'] === 'replied' && $q1['replied_by'] === 'approved' && $q1['reply_comment_id'] === 'q1_admin');
$before = count($adminPosted);
check('second click does not post again', $actions->reply('q1', 'আবার', true) !== null && count($adminPosted) === $before);

$addComment('q2', 'অর্ডার কোথায়?', '951');
$setStatus('q2', 'dry_run', 'handoff', 'টিম যোগাযোগ করবে।');
$actions->reply('q2', 'টিম যোগাযোগ করবে।', true);
check('approved handoff stays in needs_human for follow-up', $comment('q2')['status'] === 'needs_human');
check('manual follow-up reply resolves it', $actions->reply('q2', 'আপনার অর্ডার আজ পাঠানো হয়েছে।', false) === null
    && $comment('q2')['status'] === 'resolved' && $comment('q2')['replied_by'] === 'admin');

$addComment('q3', 'হ্যালো', '952');
$setStatus('q3', 'needs_human', 'handoff');
check('empty manual reply rejected', $actions->reply('q3', "  \n ", false) !== null && $comment('q3')['status'] === 'needs_human');
check('too long reply rejected', $actions->reply('q3', str_repeat('ক', CommentActions::MAX_REPLY_CHARS + 1), false) !== null);
$adminFails = 1;
$err = $actions->reply('q3', 'উত্তর', false);
check('Facebook error reported and comment stays in queue', $err !== null && str_contains($err, 'Facebook') && $comment('q3')['status'] === 'needs_human');
check('resolve without posting', $actions->resolve('q3') === null && $comment('q3')['status'] === 'resolved');
check('resolve twice reports already done', $actions->resolve('q3') !== null);

$addComment('q4', 'দাম?', '953', gmdate('Y-m-d H:i:s', time() - 3 * 86400));
$setStatus('q4', 'failed');
check('only failed comments can be retried', $actions->retry('q3') !== null);
check('retry puts failed comment back as new with a manual job', $actions->retry('q4') === null && $comment('q4')['status'] === 'new'
    && Database::one("SELECT COUNT(*) AS n FROM jobs WHERE payload LIKE '%q4%' AND payload LIKE '%manual%'")['n'] == 1);
$ai->answer('reply', 'দাম ৫৫০ টাকা।');
Settings::set('dry_run', '0');
$responder->handle('q4', true);
check('manual retry ignores the 24h age limit', $comment('q4')['status'] === 'replied' && $comment('q4')['replied_by'] === 'ai');

echo "Anthropic SDK request\n";
/** Fake PSR-18 transport: records the request, returns a canned Messages API response. */
final class FakeTransport implements Psr\Http\Client\ClientInterface
{
    public ?Psr\Http\Message\RequestInterface $request = null;
    public function __construct(private int $status, private array $body)
    {
    }
    public function sendRequest(Psr\Http\Message\RequestInterface $request): Psr\Http\Message\ResponseInterface
    {
        $this->request = $request;
        return new GuzzleHttp\Psr7\Response($this->status, ['Content-Type' => 'application/json'], json_encode($this->body));
    }
}
$transport = new FakeTransport(200, [
    'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
    'content' => [
        ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
        ['type' => 'text', 'text' => '{"action":"reply","reply":"৫৫০ টাকা","reason":"price in kb"}'],
    ],
    'stop_reason' => 'end_turn', 'stop_sequence' => null,
    'usage' => ['input_tokens' => 2500, 'output_tokens' => 80, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0],
]);
$client = new AnthropicAiClient('sk-ant-test-key-xyz-789', $transport);
$res = $client->complete('SYSTEM', 'USER', ['type' => 'object'], 'claude-opus-5-5', 'low');
$sent = json_decode((string) $transport->request->getBody(), true);
check('response text and usage parsed', $res->text === '{"action":"reply","reply":"৫৫০ টাকা","reason":"price in kb"}' && $res->inputTokens === 2500 && $res->outputTokens === 80);
check('request uses model, effort and JSON schema', $sent['model'] === 'claude-opus-5-5' && $sent['output_config']['effort'] === 'low' && $sent['output_config']['format']['type'] === 'json_schema');
check('system prompt marked for caching', ($sent['system'][0]['cache_control']['type'] ?? '') === 'ephemeral');
check('server-side fallback enabled', ($sent['fallbacks'] ?? null) === 'default' && str_contains($transport->request->getHeaderLine('anthropic-beta'), 'server-side-fallback-2026-07-01'));
check('no thinking override sent', !array_key_exists('thinking', $sent));
check('API key sent in header, not body', $transport->request->getHeaderLine('x-api-key') === 'sk-ant-test-key-xyz-789' && !str_contains(json_encode($sent), 'sk-ant'));

$haiku = new FakeTransport(200, ['id' => 'm', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-4-5', 'content' => [['type' => 'text', 'text' => '{}']], 'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]]);
(new AnthropicAiClient('sk-ant-test-key-xyz-789', $haiku))->complete('S', 'U', ['type' => 'object'], 'claude-haiku-4-5', 'low');
$sentHaiku = json_decode((string) $haiku->request->getBody(), true);
check('Haiku gets no effort and no fallbacks', !isset($sentHaiku['output_config']['effort']) && !isset($sentHaiku['fallbacks']));

$bad = new FakeTransport(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
try {
    (new AnthropicAiClient('sk-ant-test-key-xyz-789', $bad))->complete('S', 'U', ['type' => 'object'], 'claude-opus-5-5', 'low');
    check('auth error raised', false);
} catch (AiException $e) {
    check('auth error is not retryable', $e->retryable === false);
}
$busy = new FakeTransport(529, ['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]);
try {
    (new AnthropicAiClient('sk-ant-test-key-xyz-789', $busy))->complete('S', 'U', ['type' => 'object'], 'claude-opus-5-5', 'low');
    check('overload raised', false);
} catch (AiException $e) {
    check('overload is retryable', $e->retryable === true);
}

echo "Cost\n";
$r = new AiResponse('', 'end_turn', 'claude-opus-5-5', 1_000_000, 1_000_000);
check('Opus 5.5 priced $4 + $20 per million', AiModels::costMicros('claude-opus-5-5', $r) === 24_000_000);
check('cost formatting', AiModels::formatCost(15000) === '$0.0150' && AiModels::formatCost(2_500_000) === '$2.50');
check('API key never stored in logs', Database::one("SELECT COUNT(*) AS n FROM logs WHERE context LIKE '%sk-ant-test%' OR message LIKE '%sk-ant-test%'")['n'] == 0);

// Clean up
Database::setPdo(null);
array_map('unlink', glob("$tmp/*") ?: []);
@rmdir($tmp);

echo $failures === 0 ? "\nAll tests passed.\n" : "\n$failures test(s) failed.\n";
exit($failures === 0 ? 0 : 1);

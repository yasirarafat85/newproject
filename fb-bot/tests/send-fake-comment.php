<?php

declare(strict_types=1);

/*
 * Sends a fake, correctly signed comment webhook, like Facebook would:
 *   php tests/send-fake-comment.php https://bot.example.com/webhook.php "দাম কত?"
 * Optional third argument: commenter ID (use your PAGE_ID to test the loop guard).
 * Run it twice with the same FAKE_COMMENT_ID env var to test de-duplication.
 */

if (PHP_SAPI !== 'cli') {
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Env;

$url = $argv[1] ?? '';
$message = $argv[2] ?? 'Test comment: দাম কত?';
$fromId = $argv[3] ?? '1000000000000001';

if (!preg_match('#^https?://#', $url)) {
    exit("Usage: php tests/send-fake-comment.php <webhook URL> [message] [from id]\n");
}
if (!Env::has('APP_SECRET')) {
    exit("APP_SECRET is empty in .env; the webhook would reject the signature.\n");
}

$pageId = Env::get('PAGE_ID', '1000000000000000');
$postId = $pageId . '_2000000000000000';
$commentId = getenv('FAKE_COMMENT_ID') ?: '2000000000000000_' . random_int(100000000, 999999999);

$body = json_encode([
    'object' => 'page',
    'entry' => [[
        'id' => $pageId,
        'time' => time(),
        'changes' => [[
            'field' => 'feed',
            'value' => [
                'from' => ['id' => $fromId, 'name' => $fromId === $pageId ? Env::get('PAGE_NAME', 'Page') : 'Test User'],
                'post' => ['status_type' => 'added_photos', 'is_published' => true, 'id' => $postId],
                'message' => $message,
                'post_id' => $postId,
                'comment_id' => $commentId,
                'created_time' => time(),
                'item' => 'comment',
                'parent_id' => $postId,
                'verb' => 'add',
            ],
        ]],
    ]],
], JSON_UNESCAPED_SLASHES);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Hub-Signature-256: sha256=' . hash_hmac('sha256', $body, Env::get('APP_SECRET')),
    ],
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

echo "Sent comment $commentId\n";
echo "Response: HTTP $status " . ($response === false ? '(no response)' : $response) . "\n";
echo $status === 200 ? "OK — open the admin panel → কমেন্ট to see it.\n" : "Failed — check the admin panel → লগ.\n";

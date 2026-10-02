<?php

declare(strict_types=1);

namespace App;

/**
 * Facebook webhook handling, kept free of globals so it can be tested.
 * Each handler returns [HTTP status, response body].
 */
final class Webhook
{
    /** Facebook payloads are small; anything bigger is not from Facebook. */
    public const MAX_BODY_BYTES = 1_000_000;

    /**
     * GET verification handshake from the Meta dashboard.
     * PHP turns "hub.mode" into "hub_mode" in $_GET.
     *
     * @param array<string, mixed> $query
     * @return array{int, string}
     */
    public static function verify(array $query): array
    {
        $mode = (string) ($query['hub_mode'] ?? '');
        $token = (string) ($query['hub_verify_token'] ?? '');
        $challenge = (string) ($query['hub_challenge'] ?? '');
        $expected = Env::get('WEBHOOK_VERIFY_TOKEN');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)
            && preg_match('/^[A-Za-z0-9_-]{1,200}$/', $challenge)) {
            Logger::info('webhook', 'Webhook verified by Meta');
            return [200, $challenge];
        }

        Logger::warning('webhook', 'Webhook verification rejected', ['mode' => $mode]);
        return [403, 'Forbidden'];
    }

    public static function signatureValid(string $rawBody, ?string $header): bool
    {
        $secret = Env::get('APP_SECRET');
        if ($secret === '' || $header === null || !str_starts_with($header, 'sha256=')) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, substr($header, 7));
    }

    /**
     * POST event delivery. Only fast work happens here: verify, record, return.
     *
     * @return array{int, string}
     */
    public static function receive(string $rawBody, ?string $signatureHeader): array
    {
        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            Logger::warning('webhook', 'Rejected oversized payload', ['bytes' => strlen($rawBody)]);
            return [413, 'Payload too large'];
        }

        if (!self::signatureValid($rawBody, $signatureHeader)) {
            Logger::warning('webhook', 'Rejected payload with invalid signature', ['bytes' => strlen($rawBody)]);
            return [403, 'Invalid signature'];
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            Logger::warning('webhook', 'Rejected payload that is not JSON');
            return [400, 'Bad request'];
        }

        Settings::set('webhook_last_event', Database::now());

        if (($payload['object'] ?? '') !== 'page') {
            Logger::info('webhook', 'Ignored non-page event', ['object' => $payload['object'] ?? null]);
            return [200, 'EVENT_RECEIVED'];
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach ($entry['changes'] ?? [] as $change) {
                if (is_array($change)) {
                    self::handleChange((string) ($entry['id'] ?? ''), $change);
                }
            }
            if (!empty($entry['messaging'])) {
                Logger::info('webhook', 'Ignored Messenger event (inbox comes in a later phase)', ['count' => count((array) $entry['messaging'])]);
            }
        }

        return [200, 'EVENT_RECEIVED'];
    }

    /** @param array<string, mixed> $change */
    private static function handleChange(string $pageId, array $change): void
    {
        $field = (string) ($change['field'] ?? '');
        $value = is_array($change['value'] ?? null) ? $change['value'] : [];
        $item = (string) ($value['item'] ?? '');
        $verb = (string) ($value['verb'] ?? '');

        if ($field !== 'feed' || $item !== 'comment') {
            Logger::info('webhook', "Ignored $field event", ['item' => $item, 'verb' => $verb]);
            return;
        }

        $commentId = (string) ($value['comment_id'] ?? '');
        if ($commentId === '') {
            Logger::warning('webhook', 'Comment event without comment_id', ['verb' => $verb]);
            return;
        }

        // Facebook retries deliveries: handle each (comment, verb) once.
        // The dedupe key and the comment row are saved together, so a failure
        // returns 500 and Facebook's retry is processed instead of being lost.
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            self::recordComment($pageId, $commentId, $verb, $value);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $value */
    private static function recordComment(string $pageId, string $commentId, string $verb, array $value): void
    {
        if (!self::markProcessed("comment:$commentId:$verb")) {
            Logger::info('webhook', 'Duplicate comment event ignored', ['comment_id' => $commentId, 'verb' => $verb]);
            return;
        }

        if ($verb !== 'add') {
            Logger::info('webhook', "Comment $verb event ignored", ['comment_id' => $commentId]);
            return;
        }

        $from = is_array($value['from'] ?? null) ? $value['from'] : [];
        $fromId = isset($from['id']) ? (string) $from['id'] : null;
        $ownPageId = Env::get('PAGE_ID', $pageId);
        $isOwn = $fromId !== null && ($fromId === $ownPageId || $fromId === $pageId);

        $now = Database::now();
        Database::run(
            'INSERT INTO comments (comment_id, post_id, parent_id, from_id, from_name, message, status, note, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $commentId,
                isset($value['post_id']) ? (string) $value['post_id'] : null,
                isset($value['parent_id']) ? (string) $value['parent_id'] : null,
                $fromId,
                isset($from['name']) ? mb_substr((string) $from['name'], 0, 200) : null,
                mb_substr((string) ($value['message'] ?? ''), 0, 8000),
                $isOwn ? 'skipped' : 'new',
                $isOwn ? 'পেজের নিজের কমেন্ট — উত্তর দেওয়া হবে না' : ($fromId === null ? 'Facebook কমেন্টকারীর তথ্য পাঠায়নি' : null),
                $now,
                $now,
            ]
        );

        Logger::info('webhook', $isOwn ? 'Page\'s own comment recorded (no reply)' : 'Comment received', [
            'comment_id' => $commentId,
            'post_id' => $value['post_id'] ?? null,
            'from' => $from['name'] ?? null,
            'message' => mb_substr((string) ($value['message'] ?? ''), 0, 200),
        ]);
    }

    /** @return bool true if this key is new, false if it was seen before */
    private static function markProcessed(string $key): bool
    {
        try {
            Database::run('INSERT INTO processed_events (event_key, created_at) VALUES (?, ?)', [$key, Database::now()]);
            return true;
        } catch (\PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) { // integrity constraint violation
                return false;
            }
            throw $e;
        }
    }
}

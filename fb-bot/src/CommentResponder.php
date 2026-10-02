<?php

declare(strict_types=1);

namespace App;

/**
 * Worker logic for one comment: checks, AI decision, then posting the reply
 * (or only recording it in DRY_RUN mode).
 */
final class CommentResponder
{
    /** Don't answer comments the worker only reaches after this long (e.g. cron was down). */
    public const MAX_AGE_SECONDS = 24 * 3600;

    /** @var \Closure(string, string): string posts a reply, returns the new comment ID */
    private \Closure $poster;

    public function __construct(private readonly ReplyGenerator $generator, ?\Closure $poster = null)
    {
        $this->poster = $poster ?? static function (string $commentId, string $message): string {
            $result = Graph::post(rawurlencode($commentId) . '/comments', ['message' => $message]);
            return (string) ($result['id'] ?? '');
        };
    }

    public static function enqueue(string $commentId): void
    {
        Jobs::push('reply_comment', ['comment_id' => $commentId]);
    }

    /**
     * @throws AiException|GraphException when the job should be retried or failed
     */
    public function handle(string $commentId): void
    {
        $c = Database::one('SELECT * FROM comments WHERE comment_id = ?', [$commentId]);
        if ($c === null || $c['status'] !== 'new') {
            return; // already handled, or recorded as skipped by the webhook
        }

        // A previous attempt got the AI answer but failed to post it: post only.
        if ($c['ai_action'] !== null) {
            $this->publish($c, (string) $c['ai_action'], (string) $c['reply_text']);
            return;
        }

        $skip = $this->skipReason($c);
        if ($skip !== null) {
            self::update($commentId, ['status' => 'skipped', 'note' => $skip]);
            Logger::info('reply', 'Comment skipped', ['comment_id' => $commentId, 'reason' => $skip]);
            return;
        }

        $model = Settings::aiModel();
        $decision = $this->generator->generate((string) $c['message'], $model, Settings::aiEffort());
        $response = $decision['response'];
        $cost = AiModels::costMicros($response->model ?: $model, $response);
        self::recordAiCall('comment', $commentId, $response->model ?: $model, $response, $cost);

        self::update($commentId, [
            'ai_action' => $decision['action'],
            'ai_model' => $response->model ?: $model,
            'reply_text' => $decision['reply'] === '' ? null : $decision['reply'],
            'note' => $decision['reason'],
            'cost_micros' => $cost,
        ]);
        Logger::info('reply', 'AI decided: ' . $decision['action'], [
            'comment_id' => $commentId,
            'model' => $response->model ?: $model,
            'reason' => $decision['reason'],
            'cost_usd' => $cost / 1_000_000,
        ]);

        $c = Database::one('SELECT * FROM comments WHERE comment_id = ?', [$commentId]);
        $this->publish($c, $decision['action'], $decision['reply']);
    }

    /** @param array<string, mixed> $c */
    private function publish(array $c, string $action, string $reply): void
    {
        $commentId = (string) $c['comment_id'];

        if ($action === 'ignore') {
            self::update($commentId, ['status' => 'skipped']);
            return;
        }

        if (Settings::dryRun()) {
            self::update($commentId, ['status' => 'dry_run']);
            Logger::info('reply', 'DRY_RUN: reply not posted', ['comment_id' => $commentId, 'reply' => $reply]);
            return;
        }

        $replyId = ($this->poster)($commentId, $reply);
        self::update($commentId, [
            'status' => $action === 'handoff' ? 'needs_human' : 'replied',
            'reply_comment_id' => $replyId !== '' ? $replyId : null,
            'replied_at' => Database::now(),
        ]);
        Logger::info('reply', $action === 'handoff' ? 'Handoff reply posted' : 'Reply posted', ['comment_id' => $commentId, 'reply_id' => $replyId]);
    }

    /** @param array<string, mixed> $c */
    private function skipReason(array $c): ?string
    {
        if (!Settings::botEnabled()) {
            return 'বট বন্ধ ছিল';
        }
        if (time() - strtotime($c['created_at'] . ' UTC') > self::MAX_AGE_SECONDS) {
            return 'অনেক পুরনো কমেন্ট (২৪ ঘণ্টার বেশি)';
        }
        if (trim((string) $c['message']) === '') {
            return 'লেখা নেই (ছবি বা স্টিকার)';
        }
        if (!KnowledgeBase::isFilled()) {
            return 'Knowledge Base খালি — আগে তথ্য লিখুন';
        }

        if ($c['from_id'] !== null) {
            $hourAgo = gmdate('Y-m-d H:i:s', time() - 3600);
            $recent = (int) Database::one(
                'SELECT COUNT(*) AS n FROM comments WHERE from_id = ? AND ai_action IS NOT NULL AND updated_at >= ?',
                [$c['from_id'], $hourAgo]
            )['n'];
            if ($recent >= Settings::repliesPerUserPerHour()) {
                return 'এই ব্যক্তির ঘণ্টার সীমা পূর্ণ';
            }
        }

        $todayStart = (new \DateTimeImmutable('today', new \DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Dhaka'))))
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $today = (int) Database::one("SELECT COUNT(*) AS n FROM ai_calls WHERE purpose = 'comment' AND created_at >= ?", [$todayStart])['n'];
        if ($today >= Settings::maxRepliesPerDay()) {
            Logger::warning('reply', 'Daily AI reply limit reached', ['limit' => Settings::maxRepliesPerDay()]);
            return 'আজকের সর্বোচ্চ উত্তরের সীমা পূর্ণ';
        }

        return null;
    }

    /** Called by the worker when a job gives up. */
    public static function markFailed(string $commentId, string $error): void
    {
        Database::run(
            "UPDATE comments SET status = 'failed', note = ?, updated_at = ? WHERE comment_id = ? AND status = 'new'",
            [mb_substr(Logger::scrubString($error), 0, 500), Database::now(), $commentId]
        );
    }

    public static function recordAiCall(string $purpose, ?string $commentId, string $model, AiResponse $r, int $cost): void
    {
        Database::run(
            'INSERT INTO ai_calls (purpose, comment_id, model, input_tokens, output_tokens, cache_read_tokens, cache_write_tokens, cost_micros, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$purpose, $commentId, $model, $r->inputTokens, $r->outputTokens, $r->cacheReadTokens, $r->cacheWriteTokens, $cost, Database::now()]
        );
    }

    /** @param array<string, mixed> $fields */
    private static function update(string $commentId, array $fields): void
    {
        $fields['updated_at'] = Database::now();
        $set = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
        Database::run("UPDATE comments SET $set WHERE comment_id = ?", [...array_values($fields), $commentId]);
    }
}

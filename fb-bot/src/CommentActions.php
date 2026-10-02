<?php

declare(strict_types=1);

namespace App;

/**
 * What the owner can do with a comment from the admin panel's queue:
 * approve an AI draft, write their own reply, mark it resolved, or retry.
 * Every action first claims the comment with a status change, so a double
 * click or two open tabs can never post twice.
 */
final class CommentActions
{
    public const MAX_REPLY_CHARS = 2000;

    /** Statuses that appear in the queue. */
    public const QUEUE_STATUSES = ['needs_human', 'dry_run', 'failed'];

    /** @var \Closure(string, string): string */
    private \Closure $poster;

    public function __construct(?\Closure $poster = null)
    {
        $this->poster = $poster ?? static function (string $commentId, string $message): string {
            $result = Graph::post(rawurlencode($commentId) . '/comments', ['message' => $message]);
            return (string) ($result['id'] ?? '');
        };
    }

    public static function queueCount(): int
    {
        return (int) Database::one(
            "SELECT COUNT(*) AS n FROM comments WHERE status IN ('needs_human', 'dry_run', 'failed')"
        )['n'];
    }

    /**
     * Posts a reply under the comment as the Page.
     * $approvedDraft = true when the text is the AI draft (possibly edited) of a DRY_RUN comment.
     *
     * @return string|null error message in Bengali, or null on success
     */
    public function reply(string $commentId, string $text, bool $approvedDraft): ?string
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') {
            return 'উত্তর লিখুন।';
        }
        if (mb_strlen($text) > self::MAX_REPLY_CHARS) {
            return 'উত্তর অনেক বড় (সর্বোচ্চ ' . self::MAX_REPLY_CHARS . ' অক্ষর)।';
        }

        $c = Database::one('SELECT * FROM comments WHERE comment_id = ?', [$commentId]);
        if ($c === null || !in_array($c['status'], self::QUEUE_STATUSES, true)) {
            return 'এই কমেন্টটা ইতিমধ্যে সমাধান হয়ে গেছে।';
        }
        if (!$this->claim($commentId, (string) $c['status'])) {
            return 'এই কমেন্টটা ইতিমধ্যে সমাধান হয়ে গেছে।';
        }

        try {
            $replyId = ($this->poster)($commentId, $text);
        } catch (GraphException $e) {
            $this->release($commentId, (string) $c['status']);
            Logger::error('admin', 'Manual reply failed: ' . $e->getMessage(), ['comment_id' => $commentId]);
            return 'Facebook-এ পোস্ট করা যায়নি: ' . Logger::scrubString($e->getMessage());
        }

        // An approved handoff draft still needs a person to follow up.
        $status = match (true) {
            $approvedDraft && $c['ai_action'] === 'reply' => 'replied',
            $approvedDraft && $c['ai_action'] === 'handoff' => 'needs_human',
            default => 'resolved',
        };

        Database::run(
            'UPDATE comments SET status = ?, reply_text = ?, reply_comment_id = ?, replied_at = ?, replied_by = ?, updated_at = ? WHERE comment_id = ?',
            [$status, $text, $replyId !== '' ? $replyId : null, Database::now(), $approvedDraft ? 'approved' : 'admin', Database::now(), $commentId]
        );
        Logger::info('admin', $approvedDraft ? 'AI draft approved and posted' : 'Manual reply posted', ['comment_id' => $commentId, 'reply_id' => $replyId]);
        return null;
    }

    /** Marks the comment as handled without posting anything. */
    public function resolve(string $commentId): ?string
    {
        $updated = Database::run(
            "UPDATE comments SET status = 'resolved', updated_at = ? WHERE comment_id = ? AND status IN ('needs_human', 'dry_run', 'failed')",
            [Database::now(), $commentId]
        );
        if ($updated === 0) {
            return 'এই কমেন্টটা ইতিমধ্যে সমাধান হয়ে গেছে।';
        }
        Logger::info('admin', 'Comment marked resolved', ['comment_id' => $commentId]);
        return null;
    }

    /** Puts a failed comment back in the worker queue. */
    public function retry(string $commentId): ?string
    {
        $updated = Database::run(
            "UPDATE comments SET status = 'new', note = NULL, updated_at = ? WHERE comment_id = ? AND status = 'failed'",
            [Database::now(), $commentId]
        );
        if ($updated === 0) {
            return 'শুধু ব্যর্থ কমেন্ট আবার চেষ্টা করা যায়।';
        }
        CommentResponder::enqueue($commentId, true);
        Logger::info('admin', 'Failed comment queued again', ['comment_id' => $commentId]);
        return null;
    }

    /** Atomically moves the comment to "posting"; false if someone else got there first. */
    private function claim(string $commentId, string $from): bool
    {
        return Database::run(
            "UPDATE comments SET status = 'posting', updated_at = ? WHERE comment_id = ? AND status = ?",
            [Database::now(), $commentId, $from]
        ) === 1;
    }

    private function release(string $commentId, string $to): void
    {
        Database::run(
            "UPDATE comments SET status = ?, updated_at = ? WHERE comment_id = ? AND status = 'posting'",
            [$to, Database::now(), $commentId]
        );
    }
}

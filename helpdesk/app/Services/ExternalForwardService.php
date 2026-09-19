<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Sanitizer;
use App\Core\Str;
use RuntimeException;

/**
 * সিস্টেমের বাইরে ইমেইলে ফরওয়ার্ড — ভেন্ডর, পার্টনার বা অন্য বিভাগে।
 *
 * সবচেয়ে কঠিন অংশ প্রাপকের উত্তর ফেরত আনা। সমাধান: প্রতিটি ফরওয়ার্ডে
 * একটি ইউনিক টোকেন, যা Reply-To-র প্লাস-অ্যাড্রেসিং ও একটি কাস্টম
 * হেডারে বসে। উত্তর এলে MailFetcher (P4) টোকেন দেখে টিকেট মেলায় এবং
 * উত্তরটি **ইন্টার্নাল নোট** হিসেবে যোগ করে — গ্রাহক তা দেখেন না।
 */
final class ExternalForwardService
{
    /**
     * @param string[] $ccEmails
     * @return array{id:int, token:string}
     */
    public static function forward(
        array $ticket,
        int $byAgentId,
        string $toEmail,
        string $bodyHtml,
        array $ccEmails = [],
        bool $includeHistory = false,
        bool $includeAttachments = false,
    ): array {
        $toEmail = mb_strtolower(trim($toEmail));

        if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('প্রাপকের ইমেইল ঠিকানাটি সঠিক নয়।');
        }

        if (self::isOwnAddress($toEmail)) {
            throw new RuntimeException('নিজের সাপোর্ট ঠিকানায় ফরওয়ার্ড করলে লুপ তৈরি হবে।');
        }

        $cleanCc = [];
        foreach ($ccEmails as $cc) {
            $cc = mb_strtolower(trim((string) $cc));
            if ($cc !== '' && filter_var($cc, FILTER_VALIDATE_EMAIL) !== false && !self::isOwnAddress($cc)) {
                $cleanCc[] = $cc;
            }
        }

        $ticketId = (int) $ticket['id'];
        $note = Sanitizer::clean($bodyHtml);

        if (trim(strip_tags($note)) === '') {
            throw new RuntimeException('ফরওয়ার্ডের বার্তা খালি রাখা যাবে না।');
        }

        return Database::transaction(static function () use (
            $ticket, $ticketId, $byAgentId, $toEmail, $cleanCc, $note, $includeHistory, $includeAttachments
        ): array {
            $token = 'fw_' . $ticketId . '_' . Str::random(16);
            $subject = sprintf('[#%s] %s', $ticket['number'], $ticket['subject']);

            // ফরওয়ার্ডটি থ্রেডে ইন্টার্নাল এন্ট্রি হিসেবে থাকে, তাই কে কাকে
            // কী পাঠিয়েছেন তা টিকেট খুললেই দেখা যায়
            $threadId = QueryBuilder::table('ticket_threads')->insert([
                'ticket_id'   => $ticketId,
                'type'        => 'forward',
                'agent_id'    => $byAgentId,
                'body_html'   => sprintf(
                    '<p><strong>%s</strong> ঠিকানায় ফরওয়ার্ড করা হয়েছে%s</p>%s',
                    e($toEmail),
                    $cleanCc === [] ? '' : ' (অনুলিপি: ' . e(implode(', ', $cleanCc)) . ')',
                    $note
                ),
                'body_text'   => Str::htmlToText($note),
                'is_internal' => 1,
                'source'      => 'web',
                'recipients'  => json_encode(['to' => $toEmail, 'cc' => $cleanCc], JSON_UNESCAPED_UNICODE),
                'created_at'  => now(),
            ]);

            $forwardId = QueryBuilder::table('ticket_external_forwards')->insert([
                'ticket_id'            => $ticketId,
                'thread_id'            => $threadId,
                'to_email'             => $toEmail,
                'cc_emails'            => $cleanCc === [] ? null : implode(',', $cleanCc),
                'subject'              => mb_substr($subject, 0, 250),
                'body_html'            => $note,
                'include_attachments'  => $includeAttachments ? 1 : 0,
                'include_history'      => $includeHistory ? 1 : 0,
                'reply_tracking_token' => $token,
                'by_agent_id'          => $byAgentId,
                'status'               => 'queued',
                'created_at'           => now(),
            ]);

            // মেইলটি এখনই পাঠানো হয় না — কিউতে যায়। P4-এর send_queue.php
            // টোকেনটি Reply-To ও X-HelpDesk-Token হেডারে বসিয়ে পাঠাবে।
            QueryBuilder::table('email_queue')->insert([
                'to_email'      => $toEmail,
                'cc_emails'     => $cleanCc === [] ? null : implode(',', $cleanCc),
                'subject'       => mb_substr($subject, 0, 250),
                'body_html'     => self::composeEmail($ticket, $note, $includeHistory),
                'body_text'     => Str::htmlToText($note),
                'ticket_id'     => $ticketId,
                'thread_id'     => $threadId,
                'template_code' => 'ticket.forwarded',
                'priority'      => 5,
                'status'        => 'pending',
                'scheduled_at'  => now(),
                'created_at'    => now(),
            ]);

            AuditService::log('ticket.forwarded', 'ticket', $ticketId, sprintf('#%s → %s', $ticket['number'], $toEmail));

            return ['id' => $forwardId, 'token' => $token];
        });
    }

    /**
     * টোকেন দেখে ফরওয়ার্ড খুঁজে বের করে — P4-এর MailFetcher ব্যবহার করবে।
     * টোকেন `Reply-To`, `X-HelpDesk-Token` হেডার কিংবা বডির যেকোনো জায়গায় থাকতে পারে।
     */
    public static function findByToken(string $haystack): ?array
    {
        if (preg_match('/fw_(\d+)_([a-f0-9]{32})/i', $haystack, $matches) !== 1) {
            return null;
        }

        return QueryBuilder::table('ticket_external_forwards')
            ->where('reply_tracking_token', $matches[0])
            ->first();
    }

    /** টোকেনসহ যে ঠিকানায় উত্তর আসবে:  support+fw_42_abc@company.com */
    public static function replyToAddress(string $token): string
    {
        $support = (string) setting('support_email', 'support@example.com');

        if (!str_contains($support, '@')) {
            return $support;
        }

        [$local, $domain] = explode('@', $support, 2);

        return $local . '+' . $token . '@' . $domain;
    }

    private static function isOwnAddress(string $email): bool
    {
        if ($email === mb_strtolower((string) setting('support_email', ''))) {
            return true;
        }

        return QueryBuilder::table('email_accounts')->where('email', $email)->exists();
    }

    /** ইমেইলের বডি — চাইলে আগের কথোপকথনসহ। */
    private static function composeEmail(array $ticket, string $note, bool $includeHistory): string
    {
        $html = $note;

        if (!$includeHistory) {
            return $html;
        }

        $html .= '<hr><p><strong>টিকেটের আগের কথোপকথন</strong></p>';

        $threads = QueryBuilder::table('ticket_threads')
            ->select('ticket_threads.type', 'ticket_threads.body_html', 'ticket_threads.created_at',
                'agents.name AS agent_name', 'users.name AS user_name')
            ->leftJoin('agents', 'agents.id', '=', 'ticket_threads.agent_id')
            ->leftJoin('users', 'users.id', '=', 'ticket_threads.user_id')
            ->where('ticket_threads.ticket_id', (int) $ticket['id'])
            ->whereIn('ticket_threads.type', ['message', 'response'])
            ->orderBy('ticket_threads.created_at', 'ASC')
            ->get();

        foreach ($threads as $thread) {
            $author = $thread['agent_name'] ?? $thread['user_name'] ?? 'সিস্টেম';
            $html .= sprintf(
                '<blockquote><p><em>%s · %s</em></p>%s</blockquote>',
                e($author),
                e(format_date($thread['created_at'])),
                $thread['body_html']
            );
        }

        return $html;
    }
}

<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Sanitizer;
use App\Core\Str;
use PDOException;
use RuntimeException;

/**
 * টিকেট তৈরি, উত্তর, নোট ও স্ট্যাটাস পরিবর্তনের কেন্দ্রীয় জায়গা।
 *
 * ওয়েব ফর্ম, এজেন্ট প্যানেল, ইমেইল ও API — সবাই এখান দিয়েই যায়,
 * তাই নিয়মগুলো এক জায়গাতেই থাকে।
 */
final class TicketService
{
    /**
     * নতুন টিকেট তৈরি।
     *
     * @param array{
     *   user_id:int, subject:string, body:string, dept_id?:?int, topic_id?:?int,
     *   priority_id?:?int, source?:string, created_by_agent_id?:?int, ip?:?string
     * } $data
     * @return array টিকেট রো
     */
    public static function create(array $data): array
    {
        return Database::transaction(static function () use ($data): array {
            $topic = isset($data['topic_id']) && $data['topic_id'] !== null
                ? QueryBuilder::table('help_topics')->where('id', (int) $data['topic_id'])->first()
                : null;

            // অগ্রাধিকার: স্পষ্ট মান → টপিকের নিয়ম → ডিফল্ট
            $deptId = (int) ($data['dept_id'] ?? 0)
                ?: (int) ($topic['dept_id'] ?? 0)
                ?: self::defaultDepartmentId();

            $priorityId = (int) ($data['priority_id'] ?? 0)
                ?: (int) ($topic['priority_id'] ?? 0)
                ?: self::defaultPriorityId();

            $statusId = self::defaultStatusId();
            $slaId = (int) ($topic['sla_id'] ?? 0) ?: self::departmentSlaId($deptId);

            $user = QueryBuilder::table('users')->where('id', (int) $data['user_id'])->first();
            if ($user === null) {
                throw new RuntimeException('টিকেটের গ্রাহক খুঁজে পাওয়া যায়নি।');
            }

            [$responseDueAt, $dueAt] = self::computeDeadlines($slaId);

            $ticketId = self::insertWithUniqueNumber([
                'uuid'                => Str::uuid(),
                'user_id'             => (int) $user['id'],
                'org_id'              => $user['org_id'] === null ? null : (int) $user['org_id'],
                'dept_id'             => $deptId,
                'topic_id'            => $topic === null ? null : (int) $topic['id'],
                'status_id'           => $statusId,
                'priority_id'         => $priorityId,
                'sla_id'              => $slaId ?: null,
                'source'              => $data['source'] ?? 'web',
                'subject'             => Str::limit(trim($data['subject']), 250, ''),
                'last_message_at'     => now(),
                'response_due_at'     => $responseDueAt,
                'due_at'              => $dueAt,
                'created_by_agent_id' => $data['created_by_agent_id'] ?? null,
                'ip_address'          => $data['ip'] ?? null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            // টপিকে অটো-অ্যাসাইন ঠিক করা থাকলে সঙ্গে সঙ্গে বসিয়ে দিই
            $assignedAgent = $topic['auto_assign_agent_id'] ?? null;
            $assignedTeam = $topic['auto_assign_team_id'] ?? null;
            if ($assignedAgent !== null || $assignedTeam !== null) {
                QueryBuilder::table('tickets')->where('id', $ticketId)->update([
                    'assigned_agent_id' => $assignedAgent,
                    'assigned_team_id'  => $assignedTeam,
                ]);
            }

            self::addThread($ticketId, [
                'type'    => 'message',
                'user_id' => (int) $user['id'],
                'body'    => $data['body'],
                'source'  => $data['source'] ?? 'web',
                'ip'      => $data['ip'] ?? null,
            ]);

            $ticket = QueryBuilder::table('tickets')->where('id', $ticketId)->first();

            AuditService::log('ticket.created', 'ticket', $ticketId, '#' . $ticket['number'] . ' — ' . $ticket['subject']);

            return $ticket;
        });
    }

    /**
     * থ্রেডে নতুন এন্ট্রি — গ্রাহকের বার্তা, এজেন্টের উত্তর, ইন্টার্নাল নোট
     * অথবা সিস্টেম ইভেন্ট।
     *
     * @param array{type:string, agent_id?:?int, user_id?:?int, body:string, source?:string, ip?:?string, is_html?:bool} $data
     */
    public static function addThread(int $ticketId, array $data): int
    {
        $type = $data['type'];
        $isHtml = $data['is_html'] ?? false;

        $bodyHtml = $isHtml
            ? Sanitizer::clean($data['body'])
            : Sanitizer::fromPlainText($data['body']);

        if (trim(strip_tags($bodyHtml)) === '' && $type !== 'system') {
            throw new RuntimeException('বার্তা খালি রাখা যাবে না।');
        }

        $threadId = QueryBuilder::table('ticket_threads')->insert([
            'ticket_id'   => $ticketId,
            'type'        => $type,
            'agent_id'    => $data['agent_id'] ?? null,
            'user_id'     => $data['user_id'] ?? null,
            'body_html'   => $bodyHtml,
            'body_text'   => Str::htmlToText($bodyHtml),
            'is_internal' => in_array($type, ['note', 'forward'], true) ? 1 : 0,
            'source'      => $data['source'] ?? 'web',
            'ip_address'  => $data['ip'] ?? null,
            'created_at'  => now(),
        ]);

        self::touchAfterThread($ticketId, $type);

        return $threadId;
    }

    /** গ্রাহকের উত্তর — টিকেট আবার "চলমান" হয়, SLA ঘড়ি আবার চলে। */
    public static function clientReply(array $ticket, int $userId, string $body, ?string $ip = null): int
    {
        $threadId = self::addThread((int) $ticket['id'], [
            'type'    => 'message',
            'user_id' => $userId,
            'body'    => $body,
            'ip'      => $ip,
        ]);

        $state = self::stateOf((int) $ticket['status_id']);

        if (in_array($state, ['resolved', 'closed'], true)) {
            self::reopen($ticket);
        } elseif ($state === 'paused') {
            // setStatus() থামানো SLA ঘড়ি আবার চালু করে ও ডেডলাইন পিছিয়ে দেয়
            self::setStatus((int) $ticket['id'], self::statusIdForState('open'), null);
        }

        return $threadId;
    }

    /** এজেন্টের উত্তর — টিকেট গ্রাহকের অপেক্ষায় যায়, SLA ঘড়ি থামে। */
    public static function agentReply(array $ticket, int $agentId, string $body, ?int $statusId = null, bool $isHtml = true): int
    {
        $threadId = self::addThread((int) $ticket['id'], [
            'type'     => 'response',
            'agent_id' => $agentId,
            'body'     => $body,
            'is_html'  => $isHtml,
        ]);

        $update = [
            'is_answered'      => 1,
            'last_response_at' => now(),
            'updated_at'       => now(),
        ];

        if ($ticket['first_response_at'] === null) {
            $update['first_response_at'] = now();
        }

        QueryBuilder::table('tickets')->where('id', (int) $ticket['id'])->update($update);

        // স্ট্যাটাস না বললে ডিফল্ট: গ্রাহকের অপেক্ষায়
        $statusId ??= self::statusIdForState('paused');
        self::setStatus((int) $ticket['id'], $statusId, $agentId);

        return $threadId;
    }

    /** স্ট্যাটাস বদল — SLA ঘড়ি থামানো/চালু ও ক্লোজ সময় এখানেই সামলানো হয়। */
    public static function setStatus(int $ticketId, int $statusId, ?int $agentId): void
    {
        $ticket = QueryBuilder::table('tickets')->where('id', $ticketId)->first();
        if ($ticket === null || (int) $ticket['status_id'] === $statusId) {
            return;
        }

        $oldState = self::stateOf((int) $ticket['status_id']);
        $newState = self::stateOf($statusId);

        $update = ['status_id' => $statusId, 'updated_at' => now()];

        // ঘড়ি থামানো: অপেক্ষার সময় এজেন্টের SLA-তে গোনা হবে না
        if ($oldState === 'open' && in_array($newState, ['paused'], true) && self::slaPauses($ticket)) {
            $update['sla_paused_at'] = now();
        }

        if ($oldState === 'paused' && $newState === 'open' && $ticket['sla_paused_at'] !== null) {
            $paused = time() - strtotime((string) $ticket['sla_paused_at']);
            $update['sla_paused_seconds'] = (int) $ticket['sla_paused_seconds'] + max(0, $paused);
            $update['sla_paused_at'] = null;

            foreach (['response_due_at', 'due_at'] as $field) {
                if ($ticket[$field] !== null) {
                    $update[$field] = date('Y-m-d H:i:s', strtotime((string) $ticket[$field]) + max(0, $paused));
                }
            }
        }

        if (in_array($newState, ['resolved', 'closed'], true)) {
            $update['closed_at'] = now();
            $update['closed_by'] = $agentId;
            $update['is_overdue'] = 0;
        }

        if (in_array($oldState, ['resolved', 'closed'], true) && in_array($newState, ['open', 'paused'], true)) {
            $update['closed_at'] = null;
            $update['closed_by'] = null;
        }

        QueryBuilder::table('tickets')->where('id', $ticketId)->update($update);

        $statusName = QueryBuilder::table('statuses')->where('id', $statusId)->value('name');
        self::addSystemNote($ticketId, 'স্ট্যাটাস পরিবর্তন করা হয়েছে: ' . $statusName, $agentId);
        AuditService::log('ticket.status_changed', 'ticket', $ticketId, (string) $statusName);
    }

    /** বন্ধ টিকেট আবার খোলা। */
    public static function reopen(array $ticket): void
    {
        QueryBuilder::table('tickets')->where('id', (int) $ticket['id'])->update([
            'reopen_count' => (int) $ticket['reopen_count'] + 1,
            'is_answered'  => 0,
            'closed_at'    => null,
            'closed_by'    => null,
            'updated_at'   => now(),
        ]);

        self::setStatus((int) $ticket['id'], self::statusIdForState('open'), null);
        AuditService::log('ticket.reopened', 'ticket', (int) $ticket['id']);
    }

    /** এজেন্ট নিজে টিকেট নিলেন। */
    public static function claim(int $ticketId, int $agentId): void
    {
        $ticket = QueryBuilder::table('tickets')->where('id', $ticketId)->first();
        if ($ticket === null || (int) ($ticket['assigned_agent_id'] ?? 0) === $agentId) {
            return;
        }

        QueryBuilder::table('tickets')->where('id', $ticketId)->update([
            'assigned_agent_id' => $agentId,
            'updated_at'        => now(),
        ]);

        QueryBuilder::table('agents')->where('id', $agentId)->update(['last_assigned_at' => now()]);

        // P3-এ TransferService এই টেবিলটিই ব্যবহার করবে; claim-ও পূর্ণ ইতিহাসের অংশ
        QueryBuilder::table('ticket_transfers')->insert([
            'ticket_id'     => $ticketId,
            'transfer_type' => 'claim',
            'from_dept_id'  => (int) $ticket['dept_id'],
            'to_dept_id'    => (int) $ticket['dept_id'],
            'from_agent_id' => $ticket['assigned_agent_id'],
            'to_agent_id'   => $agentId,
            'by_agent_id'   => $agentId,
            'is_automatic'  => 0,
            'sla_action'    => 'keep',
            'created_at'    => now(),
        ]);

        $name = QueryBuilder::table('agents')->where('id', $agentId)->value('name');
        self::addSystemNote($ticketId, $name . ' টিকেটটি নিজের দায়িত্বে নিয়েছেন।', $agentId);
        AuditService::log('ticket.claimed', 'ticket', $ticketId, (string) $name);
    }

    public static function addSystemNote(int $ticketId, string $message, ?int $agentId = null): void
    {
        QueryBuilder::table('ticket_threads')->insert([
            'ticket_id'   => $ticketId,
            'type'        => 'system',
            'agent_id'    => $agentId,
            'body_html'   => '<p>' . e($message) . '</p>',
            'body_text'   => $message,
            'is_internal' => 1,
            'source'      => 'system',
            'created_at'  => now(),
        ]);
    }

    // ---------- ভেতরের সহায়ক ----------

    /**
     * টিকেট নম্বর: YYMMDD-NNNN।
     *
     * একই মুহূর্তে দুটি টিকেট এলে একই নম্বর তৈরি হতে পারে, তাই UNIQUE
     * ইনডেক্সে ধাক্কা খেলে পরের নম্বর নিয়ে আবার চেষ্টা করি। লক নেওয়ার
     * চেয়ে এটি সস্তা এবং দৌড়ে দুজন থাকলেও সঠিক।
     */
    private static function insertWithUniqueNumber(array $row): int
    {
        $prefix = date('ymd');
        $todayCount = QueryBuilder::table('tickets')
            ->where('number', 'LIKE', $prefix . '-%')
            ->count();

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $row['number'] = $prefix . '-' . str_pad((string) ($todayCount + 1 + $attempt), 4, '0', STR_PAD_LEFT);

            try {
                return QueryBuilder::table('tickets')->insert($row);
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }

        // অসম্ভবের কাছাকাছি; তবু নিঃশব্দে ব্যর্থ না হয়ে স্পষ্ট বার্তা দিই
        throw new RuntimeException('টিকেট নম্বর তৈরি করা যায়নি, আবার চেষ্টা করুন।');
    }

    /** @return array{0:?string,1:?string} [response_due_at, due_at] */
    private static function computeDeadlines(int $slaId): array
    {
        if ($slaId === 0) {
            return [null, null];
        }

        $sla = QueryBuilder::table('sla_plans')->where('id', $slaId)->first();
        if ($sla === null) {
            return [null, null];
        }

        // P1-এ সরল ঘড়ি-সময়। বিজনেস আওয়ার ও ছুটি হিসেবে ধরা হবে P5-এ,
        // যখন SlaService তৈরি হবে — তখন এই মেথডটিই সেখানে সরে যাবে।
        return [
            date('Y-m-d H:i:s', time() + ((int) $sla['first_response_minutes'] * 60)),
            date('Y-m-d H:i:s', time() + ((int) $sla['resolution_minutes'] * 60)),
        ];
    }

    private static function slaPauses(array $ticket): bool
    {
        if ($ticket['sla_id'] === null) {
            return false;
        }

        return (int) QueryBuilder::table('sla_plans')->where('id', (int) $ticket['sla_id'])->value('pause_on_pending') === 1;
    }

    private static function touchAfterThread(int $ticketId, string $type): void
    {
        $update = ['updated_at' => now()];

        if ($type === 'message') {
            $update['last_message_at'] = now();
            $update['is_answered'] = 0;   // গ্রাহক কিছু বলেছেন → উত্তর বাকি
        }

        QueryBuilder::table('tickets')->where('id', $ticketId)->update($update);
    }

    public static function stateOf(int $statusId): string
    {
        return (string) QueryBuilder::table('statuses')->where('id', $statusId)->value('state');
    }

    public static function statusIdForState(string $state): int
    {
        return (int) QueryBuilder::table('statuses')
            ->where('state', $state)
            ->orderBy('sort_order')
            ->value('id');
    }

    private static function defaultStatusId(): int
    {
        $id = QueryBuilder::table('statuses')->where('is_default', 1)->value('id');

        return (int) ($id ?? self::statusIdForState('open'));
    }

    private static function defaultPriorityId(): int
    {
        $id = QueryBuilder::table('priorities')->where('is_default', 1)->value('id');

        return (int) ($id ?? QueryBuilder::table('priorities')->orderBy('level')->value('id'));
    }

    private static function defaultDepartmentId(): int
    {
        $id = QueryBuilder::table('departments')->where('is_default', 1)->value('id');

        return (int) ($id ?? QueryBuilder::table('departments')->where('is_active', 1)->orderBy('sort_order')->value('id'));
    }

    private static function departmentSlaId(int $deptId): int
    {
        $id = QueryBuilder::table('departments')->where('id', $deptId)->value('sla_id');
        $id ??= QueryBuilder::table('sla_plans')->where('is_default', 1)->value('id');

        return (int) ($id ?? 0);
    }

    /** ক্লোজ হওয়া টিকেট এখনো reopen করা যাবে কি না। */
    public static function canReopen(array $ticket): bool
    {
        if ($ticket['closed_at'] === null) {
            return true;
        }

        $days = (int) setting('reopen_window_days', (string) Config::get('app.reopen_window_days', 7));

        return (time() - strtotime((string) $ticket['closed_at'])) <= ($days * 86400);
    }
}

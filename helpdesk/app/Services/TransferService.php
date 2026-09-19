<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\QueryBuilder;
use RuntimeException;

/**
 * টিকেট হস্তান্তর — ডিপার্টমেন্ট, এজেন্ট ও টিমের মধ্যে।
 *
 * চারটি প্রবেশপথ (toDepartment / toAgent / toTeam / release) একই
 * ভেতরের পথে মেশে, তাই অডিট, সিস্টেম এন্ট্রি, SLA হিসাব ও নোটিফিকেশন
 * প্রতিটি ধরনের জন্য একইভাবে ঘটে — কোনোটি বাদ পড়ার সুযোগ নেই।
 */
final class TransferService
{
    /** SLA-র ঘড়ি নিয়ে কী করা হবে। */
    public const SLA_KEEP = 'keep';
    public const SLA_RESET = 'reset';
    public const SLA_EXTEND = 'extend';

    /**
     * অন্য ডিপার্টমেন্টে পাঠানো।
     *
     * অ্যাসাইনমেন্ট ক্লিয়ার হয়ে যায় — নতুন ডিপার্টমেন্টের কেউ না নিলে
     * পুরনো এজেন্টের কিউতে ঝুলে থাকার কোনো মানে নেই। গন্তব্যের কৌশল
     * অটো-অ্যাসাইন হলে সঙ্গে সঙ্গে নতুন একজনকে দেওয়া হয়।
     */
    public static function toDepartment(
        array $ticket,
        int $toDeptId,
        int $byAgentId,
        string $reason = '',
        string $slaAction = self::SLA_KEEP,
    ): void {
        $department = QueryBuilder::table('departments')->where('id', $toDeptId)->first();

        if ($department === null || (int) $department['is_active'] !== 1) {
            throw new RuntimeException('গন্তব্য ডিপার্টমেন্টটি নেই বা নিষ্ক্রিয়।');
        }

        if ((int) $ticket['dept_id'] === $toDeptId) {
            throw new RuntimeException('টিকেটটি ইতিমধ্যে এই ডিপার্টমেন্টেই আছে।');
        }

        if ($slaAction === self::SLA_RESET && !self::mayResetSla($ticket, $toDeptId)) {
            throw new RuntimeException('SLA নতুন করে শুরু করার অনুমতি শুধু ম্যানেজার ও অ্যাডমিনের।');
        }

        self::apply($ticket, $byAgentId, [
            'transfer_type' => 'department',
            'to_dept_id'    => $toDeptId,
            'to_agent_id'   => null,
            'to_team_id'    => null,
            'reason'        => $reason,
            'sla_action'    => $slaAction,
            'message'       => sprintf('%s ডিপার্টমেন্টে স্থানান্তর করা হয়েছে', $department['name']),
            'autoAssign'    => true,
        ]);
    }

    /** নির্দিষ্ট এজেন্টের হাতে দেওয়া। */
    public static function toAgent(array $ticket, int $toAgentId, int $byAgentId, string $reason = ''): void
    {
        $agent = QueryBuilder::table('agents')
            ->where('id', $toAgentId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if ($agent === null) {
            throw new RuntimeException('এই এজেন্টকে পাওয়া যায়নি বা তিনি সক্রিয় নন।');
        }

        if ((int) ($ticket['assigned_agent_id'] ?? 0) === $toAgentId) {
            throw new RuntimeException('টিকেটটি ইতিমধ্যে এই এজেন্টের কাছেই আছে।');
        }

        // যে ডিপার্টমেন্টের টিকেট, এজেন্টকে সেই ডিপার্টমেন্টে থাকতে হবে —
        // নইলে অ্যাসাইন হওয়ার পরেও তিনি টিকেটটি দেখতে পাবেন না
        if (!self::belongsToDepartment($toAgentId, (int) $ticket['dept_id'])) {
            throw new RuntimeException(sprintf(
                '%s এই ডিপার্টমেন্টের সদস্য নন। আগে তাঁকে যুক্ত করুন অথবা টিকেটটি তাঁর ডিপার্টমেন্টে পাঠান।',
                $agent['name']
            ));
        }

        self::apply($ticket, $byAgentId, [
            'transfer_type' => 'agent',
            'to_dept_id'    => (int) $ticket['dept_id'],
            'to_agent_id'   => $toAgentId,
            'to_team_id'    => $ticket['assigned_team_id'] === null ? null : (int) $ticket['assigned_team_id'],
            'reason'        => $reason,
            'sla_action'    => self::SLA_KEEP,
            'message'       => sprintf('%s কে দায়িত্ব দেওয়া হয়েছে', $agent['name']),
            'autoAssign'    => false,
        ]);
    }

    /** টিমের কিউতে পাঠানো — যে আগে claim করবেন তিনি পাবেন। */
    public static function toTeam(array $ticket, int $toTeamId, int $byAgentId, string $reason = ''): void
    {
        $team = QueryBuilder::table('teams')->where('id', $toTeamId)->where('is_active', 1)->first();

        if ($team === null) {
            throw new RuntimeException('টিমটি নেই বা নিষ্ক্রিয়।');
        }

        if ((int) ($ticket['assigned_team_id'] ?? 0) === $toTeamId) {
            throw new RuntimeException('টিকেটটি ইতিমধ্যে এই টিমেরই।');
        }

        self::apply($ticket, $byAgentId, [
            'transfer_type' => 'team',
            'to_dept_id'    => (int) $ticket['dept_id'],
            'to_agent_id'   => null,   // টিমে দিলে ব্যক্তিগত অ্যাসাইনমেন্ট ছেড়ে দিই
            'to_team_id'    => $toTeamId,
            'reason'        => $reason,
            'sla_action'    => self::SLA_KEEP,
            'message'       => sprintf('%s টিমের কিউতে পাঠানো হয়েছে', $team['name']),
            'autoAssign'    => false,
        ]);
    }

    /** দায়িত্ব ছেড়ে দেওয়া — টিকেট আবার আনঅ্যাসাইনড কিউতে। */
    public static function release(array $ticket, int $byAgentId, string $reason = ''): void
    {
        if ($ticket['assigned_agent_id'] === null && $ticket['assigned_team_id'] === null) {
            throw new RuntimeException('টিকেটটি এমনিতেই কারও দায়িত্বে নেই।');
        }

        self::apply($ticket, $byAgentId, [
            'transfer_type' => 'release',
            'to_dept_id'    => (int) $ticket['dept_id'],
            'to_agent_id'   => null,
            'to_team_id'    => null,
            'reason'        => $reason,
            'sla_action'    => self::SLA_KEEP,
            'message'       => 'দায়িত্ব ছেড়ে দেওয়া হয়েছে — টিকেটটি আবার আনঅ্যাসাইনড',
            'autoAssign'    => false,
        ]);
    }

    /**
     * স্বয়ংক্রিয় এস্কেলেশন (P5-এর ক্রন এটি ব্যবহার করবে)।
     * by_agent_id = null, is_automatic = 1 — টাইমলাইনে মানুষ ও সিস্টেমের
     * কাজ পাশাপাশি দেখা যায়।
     */
    public static function escalateToDepartment(array $ticket, int $toDeptId, string $reason): void
    {
        $department = QueryBuilder::table('departments')->where('id', $toDeptId)->first();
        if ($department === null || (int) $ticket['dept_id'] === $toDeptId) {
            return;
        }

        self::apply($ticket, null, [
            'transfer_type' => 'escalation',
            'to_dept_id'    => $toDeptId,
            'to_agent_id'   => null,
            'to_team_id'    => null,
            'reason'        => $reason,
            'sla_action'    => self::SLA_KEEP,
            'message'       => sprintf('স্বয়ংক্রিয়ভাবে %s ডিপার্টমেন্টে এস্কেলেট করা হয়েছে', $department['name']),
            'autoAssign'    => true,
            'automatic'     => true,
        ]);
    }

    // ---------------------------------------------------------------- ভেতরের পথ

    /**
     * সব ধরনের হস্তান্তরের একমাত্র বাস্তবায়ন।
     *
     * ধাপ: লক → পুরনো অবস্থা → আপডেট → SLA → অডিট → সিস্টেম এন্ট্রি →
     * নোটিফিকেশন — পুরোটাই একটি ট্রানজ্যাকশনে।
     */
    private static function apply(array $ticket, ?int $byAgentId, array $options): void
    {
        $ticketId = (int) $ticket['id'];

        Database::transaction(static function () use ($ticketId, $byAgentId, $options): void {
            // লক নিয়ে সবচেয়ে সাম্প্রতিক অবস্থাটাই পড়ি — এর মধ্যে অন্য কেউ
            // ট্রান্সফার করে ফেললে তার উপরেই কাজ হবে, পুরনো কপির উপরে নয়
            $current = Database::lockRow('tickets', $ticketId);
            if ($current === null) {
                throw new RuntimeException('টিকেটটি আর নেই।');
            }

            $fromDeptId  = (int) $current['dept_id'];
            $fromAgentId = $current['assigned_agent_id'] === null ? null : (int) $current['assigned_agent_id'];
            $fromTeamId  = $current['assigned_team_id'] === null ? null : (int) $current['assigned_team_id'];

            $toDeptId  = (int) $options['to_dept_id'];
            $toAgentId = $options['to_agent_id'];
            $toTeamId  = $options['to_team_id'];

            // ডিপার্টমেন্ট বদলালে গন্তব্যের কৌশল অনুযায়ী নতুন একজনকে দেওয়া যায় কি না দেখি
            $autoAssigned = null;
            if (($options['autoAssign'] ?? false) && $toAgentId === null) {
                $autoAssigned = AssignmentService::pick($toDeptId);
                $toAgentId = $autoAssigned;
            }

            [$slaUpdate, $newDueAt] = self::resolveSla($current, $toDeptId, (string) $options['sla_action']);

            $update = [
                'dept_id'           => $toDeptId,
                'assigned_agent_id' => $toAgentId,
                'assigned_team_id'  => $toTeamId,
                'updated_at'        => now(),
            ] + $slaUpdate;

            QueryBuilder::table('tickets')->where('id', $ticketId)->update($update);

            if ($toAgentId !== null) {
                AssignmentService::markAssigned($toAgentId);
            }

            QueryBuilder::table('ticket_transfers')->insert([
                'ticket_id'     => $ticketId,
                'transfer_type' => $options['transfer_type'],
                'from_dept_id'  => $fromDeptId,
                'to_dept_id'    => $toDeptId,
                'from_agent_id' => $fromAgentId,
                'to_agent_id'   => $toAgentId,
                'from_team_id'  => $fromTeamId,
                'to_team_id'    => $toTeamId,
                'reason'        => $options['reason'] === '' ? null : mb_substr((string) $options['reason'], 0, 250),
                'by_agent_id'   => $byAgentId,
                'is_automatic'  => ($options['automatic'] ?? false) ? 1 : 0,
                'sla_action'    => $options['sla_action'],
                'old_due_at'    => $current['due_at'],
                'new_due_at'    => $newDueAt,
                'created_at'    => now(),
            ]);

            $message = (string) $options['message'];
            if ($autoAssigned !== null) {
                $name = QueryBuilder::table('agents')->where('id', $autoAssigned)->value('name');
                $message .= sprintf(' — স্বয়ংক্রিয়ভাবে %s কে দেওয়া হয়েছে', (string) $name);
            }
            if (($options['reason'] ?? '') !== '') {
                $message .= sprintf(' · কারণ: %s', $options['reason']);
            }

            TicketService::addSystemNote($ticketId, $message, $byAgentId);

            self::notify($current, $ticketId, $byAgentId, $fromAgentId, $toAgentId, $toTeamId, $toDeptId, $message);

            AuditService::log(
                ($options['automatic'] ?? false) ? 'ticket.escalated' : 'ticket.transferred',
                'ticket',
                $ticketId,
                '#' . $current['number'] . ' — ' . $message
            );
        });
    }

    /**
     * SLA-র ঘড়ি নিয়ে সিদ্ধান্ত।
     *
     * keep   — কিছুই বদলায় না। গ্রাহকের দৃষ্টিতে এটিই ন্যায্য: অভ্যন্তরীণ
     *          হস্তান্তরের জন্য তাঁর অপেক্ষা বাড়ার কথা নয়।
     * reset  — গন্তব্য ডিপার্টমেন্টের SLA দিয়ে নতুন করে গণনা।
     * extend — বর্তমান ডেডলাইনে গন্তব্যের প্রথম-উত্তর সময় যোগ।
     *
     * @return array{0:array<string,mixed>, 1:?string}
     */
    private static function resolveSla(array $ticket, int $toDeptId, string $action): array
    {
        if ($action === self::SLA_KEEP) {
            return [[], $ticket['due_at']];
        }

        $slaId = QueryBuilder::table('departments')->where('id', $toDeptId)->value('sla_id')
            ?? QueryBuilder::table('sla_plans')->where('is_default', 1)->value('id');

        if ($slaId === null) {
            return [[], $ticket['due_at']];
        }

        $sla = QueryBuilder::table('sla_plans')->where('id', (int) $slaId)->first();
        if ($sla === null) {
            return [[], $ticket['due_at']];
        }

        if ($action === self::SLA_RESET) {
            $responseDue = date('Y-m-d H:i:s', time() + ((int) $sla['first_response_minutes'] * 60));
            $due = date('Y-m-d H:i:s', time() + ((int) $sla['resolution_minutes'] * 60));

            return [[
                'sla_id'             => (int) $slaId,
                'response_due_at'    => $responseDue,
                'due_at'             => $due,
                'sla_paused_at'      => null,
                'sla_paused_seconds' => 0,
                'is_overdue'         => 0,
            ], $due];
        }

        // extend
        $base = $ticket['due_at'] === null ? time() : strtotime((string) $ticket['due_at']);
        $due = date('Y-m-d H:i:s', $base + ((int) $sla['first_response_minutes'] * 60));

        return [['due_at' => $due, 'is_overdue' => 0], $due];
    }

    /** কে কে জানবে: নতুন এজেন্ট · গন্তব্যের ম্যানেজার · পুরনো এজেন্ট · টিম লিড। */
    private static function notify(
        array $ticket,
        int $ticketId,
        ?int $byAgentId,
        ?int $fromAgentId,
        ?int $toAgentId,
        ?int $toTeamId,
        int $toDeptId,
        string $message,
    ): void {
        $url = '/agent/tickets/' . $ticketId;
        $subject = '#' . $ticket['number'] . ' — ' . $ticket['subject'];

        if ($toAgentId !== null) {
            NotificationService::notifyMany(
                [$toAgentId], $byAgentId, 'ticket.assigned',
                'আপনাকে একটি টিকেট দেওয়া হয়েছে', $subject, $url, $ticketId
            );
        } else {
            // কেউ নির্দিষ্টভাবে দায়িত্ব না পেলে ম্যানেজাররা জানুন
            NotificationService::notifyMany(
                NotificationService::departmentManagers($toDeptId), $byAgentId, 'ticket.unassigned',
                'আপনার ডিপার্টমেন্টে একটি আনঅ্যাসাইনড টিকেট এসেছে', $subject, $url, $ticketId
            );
        }

        if ($fromAgentId !== null && $fromAgentId !== $toAgentId) {
            NotificationService::notifyMany(
                [$fromAgentId], $byAgentId, 'ticket.moved',
                'আপনার একটি টিকেট সরানো হয়েছে', $message, $url, $ticketId
            );
        }

        if ($toTeamId !== null) {
            $team = QueryBuilder::table('teams')->where('id', $toTeamId)->first();

            if ($team !== null && (int) $team['notify_lead'] === 1) {
                NotificationService::notifyMany(
                    [$team['lead_agent_id']], $byAgentId, 'ticket.team',
                    'আপনার টিমে একটি টিকেট এসেছে', $subject, $url, $ticketId
                );
            }
        }
    }

    private static function belongsToDepartment(int $agentId, int $deptId): bool
    {
        $inDept = QueryBuilder::table('agent_departments')
            ->where('agent_id', $agentId)
            ->where('dept_id', $deptId)
            ->exists();

        if ($inDept) {
            return true;
        }

        $agent = QueryBuilder::table('agents')->where('id', $agentId)->first();

        // অ্যাডমিন সব ডিপার্টমেন্টেই কাজ করতে পারেন
        return $agent !== null
            && ((int) $agent['is_admin'] === 1 || (int) ($agent['primary_dept_id'] ?? 0) === $deptId);
    }

    /** SLA নতুন করে শুরু করা — শুধু অ্যাডমিন ও সংশ্লিষ্ট ডিপার্টমেন্টের ম্যানেজার। */
    public static function mayResetSla(array $ticket, int $toDeptId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

        $agentId = Auth::agentId();
        if ($agentId === null) {
            return false;
        }

        return QueryBuilder::table('agent_departments')
            ->where('agent_id', $agentId)
            ->where('is_manager', 1)
            ->whereIn('dept_id', [(int) $ticket['dept_id'], $toDeptId])
            ->exists();
    }
}

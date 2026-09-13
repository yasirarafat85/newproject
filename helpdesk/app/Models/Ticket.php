<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\QueryBuilder;

/**
 * টিকেট কোয়েরির সাধারণ অংশগুলো এক জায়গায় — কন্ট্রোলারে বড় JOIN
 * বারবার না লিখতে হয়, আর দৃশ্যমানতার নিয়ম একটিই জায়গায় থাকে।
 */
final class Ticket
{
    /** তালিকা ও ভিউ — দুই জায়গাতেই যে কলামগুলো লাগে। */
    public static function listQuery(): QueryBuilder
    {
        return QueryBuilder::table('tickets')
            ->select(
                'tickets.id', 'tickets.number', 'tickets.subject', 'tickets.created_at',
                'tickets.updated_at', 'tickets.due_at', 'tickets.last_message_at',
                'tickets.is_answered', 'tickets.assigned_agent_id', 'tickets.assigned_team_id',
                'tickets.dept_id', 'tickets.source',
                'users.name AS user_name', 'users.email AS user_email',
                'departments.name AS dept_name',
                'statuses.name AS status_name', 'statuses.color AS status_color', 'statuses.state AS status_state',
                'priorities.name AS priority_name', 'priorities.color AS priority_color', 'priorities.level AS priority_level',
                'agents.name AS agent_name'
            )
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->join('departments', 'departments.id', '=', 'tickets.dept_id')
            ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
            ->join('priorities', 'priorities.id', '=', 'tickets.priority_id')
            ->leftJoin('agents', 'agents.id', '=', 'tickets.assigned_agent_id')
            ->whereNull('tickets.deleted_at');
    }

    /**
     * লগইন করা এজেন্ট যে টিকেটগুলো দেখতে পারেন, কেবল সেগুলোতেই সীমাবদ্ধ করে।
     *
     * ticket.view_all → সব · view_dept → নিজের ডিপার্টমেন্ট ও নিজের/টিমের
     * টিকেট · এর কোনোটিই না থাকলে শুধু নিজের অ্যাসাইনড টিকেট।
     */
    public static function applyVisibility(QueryBuilder $query): QueryBuilder
    {
        if (Auth::isAdmin() || Auth::hasPermission('ticket.view_all')) {
            return $query;
        }

        $agentId = (int) Auth::agentId();
        $deptIds = Auth::departmentIds();
        $teamIds = Auth::teamIds();

        if (!Auth::hasPermission('ticket.view_dept')) {
            return $query->where('tickets.assigned_agent_id', $agentId);
        }

        return $query->whereGroup(static function (QueryBuilder $sub) use ($agentId, $deptIds, $teamIds): void {
            $sub->where('tickets.assigned_agent_id', $agentId);

            if ($deptIds !== []) {
                $sub->orWhereIn('tickets.dept_id', $deptIds);
            }

            if ($teamIds !== []) {
                $sub->orWhereIn('tickets.assigned_team_id', $teamIds);
            }
        });
    }

    /** সম্পূর্ণ টিকেট রো (সব কলাম) — নম্বর দিয়ে। */
    public static function findByNumber(string $number): ?array
    {
        return QueryBuilder::table('tickets')
            ->where('number', $number)
            ->whereNull('deleted_at')
            ->first();
    }

    public static function findById(int $id): ?array
    {
        return QueryBuilder::table('tickets')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();
    }

    /** প্রদর্শনের জন্য সব সম্পর্কিত নামসহ একটি টিকেট। */
    public static function detail(int $id): ?array
    {
        return self::listQuery()
            ->select(
                'tickets.id', 'tickets.uuid', 'tickets.number', 'tickets.subject', 'tickets.created_at',
                'tickets.updated_at', 'tickets.due_at', 'tickets.response_due_at', 'tickets.closed_at',
                'tickets.first_response_at', 'tickets.last_message_at', 'tickets.is_answered',
                'tickets.reopen_count', 'tickets.source', 'tickets.user_id', 'tickets.dept_id',
                'tickets.topic_id', 'tickets.status_id', 'tickets.priority_id', 'tickets.sla_id',
                'tickets.assigned_agent_id', 'tickets.assigned_team_id',
                'users.name AS user_name', 'users.email AS user_email', 'users.phone AS user_phone',
                'departments.name AS dept_name',
                'statuses.name AS status_name', 'statuses.color AS status_color', 'statuses.state AS status_state',
                'priorities.name AS priority_name', 'priorities.color AS priority_color',
                'agents.name AS agent_name'
            )
            ->where('tickets.id', $id)
            ->first();
    }

    /**
     * টিকেটের থ্রেড।
     *
     * @param bool $includeInternal এজেন্টের জন্য true — নোট ও সিস্টেম ইভেন্টও আসে
     */
    public static function threads(int $ticketId, bool $includeInternal): array
    {
        $query = QueryBuilder::table('ticket_threads')
            ->select(
                'ticket_threads.id', 'ticket_threads.type', 'ticket_threads.body_html',
                'ticket_threads.created_at', 'ticket_threads.source', 'ticket_threads.is_internal',
                'ticket_threads.agent_id', 'ticket_threads.user_id',
                'agents.name AS agent_name',
                'users.name AS user_name'
            )
            ->leftJoin('agents', 'agents.id', '=', 'ticket_threads.agent_id')
            ->leftJoin('users', 'users.id', '=', 'ticket_threads.user_id')
            ->where('ticket_threads.ticket_id', $ticketId)
            ->orderBy('ticket_threads.created_at', 'ASC')
            ->orderBy('ticket_threads.id', 'ASC');

        if (!$includeInternal) {
            $query->where('ticket_threads.is_internal', 0)
                  ->whereIn('ticket_threads.type', ['message', 'response']);
        }

        return $query->get();
    }

    /** থ্রেড আইডি → সেই থ্রেডের অ্যাটাচমেন্ট তালিকা। */
    public static function attachmentsByThread(int $ticketId): array
    {
        $rows = QueryBuilder::table('attachments')
            ->select('id', 'uuid', 'thread_id', 'original_name', 'mime_type', 'size_bytes')
            ->where('ticket_id', $ticketId)
            ->orderBy('id', 'ASC')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['thread_id']][] = $row;
        }

        return $grouped;
    }

    /** টিকেটের হস্তান্তর ইতিহাস — টাইমলাইনে দেখানো হয়। */
    public static function transfers(int $ticketId): array
    {
        return QueryBuilder::table('ticket_transfers')
            ->select(
                'ticket_transfers.id', 'ticket_transfers.transfer_type', 'ticket_transfers.reason',
                'ticket_transfers.is_automatic', 'ticket_transfers.created_at',
                'agents.name AS by_name'
            )
            ->leftJoin('agents', 'agents.id', '=', 'ticket_transfers.by_agent_id')
            ->where('ticket_transfers.ticket_id', $ticketId)
            ->orderBy('ticket_transfers.created_at', 'DESC')
            ->limit(20)
            ->get();
    }
}

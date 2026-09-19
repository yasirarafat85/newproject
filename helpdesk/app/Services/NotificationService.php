<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

/**
 * এজেন্টদের ইন-অ্যাপ নোটিফিকেশন।
 *
 * ইমেইল পাঠানো P4-এর email_queue-এর কাজ; এখানে শুধু প্যানেলের
 * ঘণ্টার ব্যাজে যা দেখা যাবে সেটুকু।
 */
final class NotificationService
{
    public static function notify(
        ?int $agentId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?int $ticketId = null,
    ): void {
        if ($agentId === null || $agentId <= 0) {
            return;
        }

        // নিষ্ক্রিয় বা মুছে ফেলা এজেন্টকে জানিয়ে লাভ নেই
        $active = QueryBuilder::table('agents')
            ->where('id', $agentId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();

        if (!$active) {
            return;
        }

        QueryBuilder::table('notifications')->insert([
            'agent_id'   => $agentId,
            'type'       => $type,
            'title'      => mb_substr($title, 0, 190),
            'body'       => $body === null ? null : mb_substr($body, 0, 500),
            'url'        => $url,
            'ticket_id'  => $ticketId,
            'is_read'    => 0,
            'created_at' => now(),
        ]);
    }

    /**
     * একাধিক এজেন্টকে একই বার্তা। কাজটি যিনি করেছেন তাঁকে বাদ দেওয়া হয় —
     * নিজের কাজের নোটিফিকেশন পাওয়া বিরক্তিকর।
     *
     * @param array<int|null> $agentIds
     */
    public static function notifyMany(
        array $agentIds,
        ?int $exceptAgentId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?int $ticketId = null,
    ): void {
        $unique = array_unique(array_filter(
            array_map('intval', array_filter($agentIds, static fn ($id): bool => $id !== null)),
            static fn (int $id): bool => $id > 0 && $id !== $exceptAgentId
        ));

        foreach ($unique as $agentId) {
            self::notify($agentId, $type, $title, $body, $url, $ticketId);
        }
    }

    public static function unreadCount(int $agentId): int
    {
        return QueryBuilder::table('notifications')
            ->where('agent_id', $agentId)
            ->where('is_read', 0)
            ->count();
    }

    public static function markAllRead(int $agentId): void
    {
        QueryBuilder::table('notifications')
            ->where('agent_id', $agentId)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
    }

    /** একটি ডিপার্টমেন্টের ম্যানেজারদের আইডি (প্রধান ম্যানেজার + সদস্য-ম্যানেজার)। */
    public static function departmentManagers(int $deptId): array
    {
        $ids = [];

        $primary = QueryBuilder::table('departments')->where('id', $deptId)->value('manager_id');
        if ($primary !== null) {
            $ids[] = (int) $primary;
        }

        foreach (QueryBuilder::table('agent_departments')
            ->select('agent_id')
            ->where('dept_id', $deptId)
            ->where('is_manager', 1)
            ->get() as $row) {
            $ids[] = (int) $row['agent_id'];
        }

        return array_values(array_unique($ids));
    }
}

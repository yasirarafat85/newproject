<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * অটো-অ্যাসাইনমেন্ট — ডিপার্টমেন্টের কৌশল অনুযায়ী কোন এজেন্ট পাবেন।
 *
 *   manual       কেউ নয় — টিকেট আনঅ্যাসাইনড কিউতে অপেক্ষা করবে
 *   round_robin  সবাইকে পালা করে (যিনি সবচেয়ে আগে পেয়েছিলেন, তিনি আবার)
 *   least_load   যাঁর খোলা টিকেট সবচেয়ে কম, max_open_tickets সীমা মেনে
 */
final class AssignmentService
{
    /** @return ?int অ্যাসাইন করার মতো এজেন্টের আইডি, না পেলে null */
    public static function pick(int $deptId): ?int
    {
        $strategy = (string) QueryBuilder::table('departments')->where('id', $deptId)->value('assignment_strategy');

        return match ($strategy) {
            'round_robin' => self::roundRobin($deptId),
            'least_load'  => self::leastLoad($deptId),
            default       => null,
        };
    }

    /**
     * অ্যাসাইন করার যোগ্য এজেন্ট: ডিপার্টমেন্টের সদস্য, সক্রিয়, এবং
     * অটো-অ্যাসাইনে অন্তর্ভুক্ত (ছুটিতে থাকলে is_available=0)।
     */
    private static function candidates(int $deptId): array
    {
        return QueryBuilder::table('agents')
            ->select('agents.id', 'agents.max_open_tickets', 'agents.last_assigned_at')
            ->join('agent_departments', 'agent_departments.agent_id', '=', 'agents.id')
            ->where('agent_departments.dept_id', $deptId)
            ->where('agents.status', 'active')
            ->where('agents.is_available', 1)
            ->whereNull('agents.deleted_at')
            ->get();
    }

    private static function roundRobin(int $deptId): ?int
    {
        $candidates = self::candidates($deptId);
        if ($candidates === []) {
            return null;
        }

        // যাঁকে কখনো দেওয়া হয়নি তিনি আগে; তারপর সবচেয়ে পুরনো বরাদ্দ
        usort($candidates, static function (array $a, array $b): int {
            $left = $a['last_assigned_at'] === null ? 0 : strtotime((string) $a['last_assigned_at']);
            $right = $b['last_assigned_at'] === null ? 0 : strtotime((string) $b['last_assigned_at']);

            return $left <=> $right ?: ((int) $a['id'] <=> (int) $b['id']);
        });

        foreach ($candidates as $candidate) {
            if (!self::isOverloaded((int) $candidate['id'], $candidate['max_open_tickets'])) {
                return (int) $candidate['id'];
            }
        }

        return null;
    }

    private static function leastLoad(int $deptId): ?int
    {
        $candidates = self::candidates($deptId);
        if ($candidates === []) {
            return null;
        }

        $best = null;
        $bestLoad = PHP_INT_MAX;

        foreach ($candidates as $candidate) {
            $agentId = (int) $candidate['id'];
            $load = self::openTicketCount($agentId);

            $max = $candidate['max_open_tickets'];
            if ($max !== null && $load >= (int) $max) {
                continue;
            }

            if ($load < $bestLoad) {
                $best = $agentId;
                $bestLoad = $load;
            }
        }

        return $best;
    }

    private static function isOverloaded(int $agentId, mixed $maxOpen): bool
    {
        if ($maxOpen === null) {
            return false;
        }

        return self::openTicketCount($agentId) >= (int) $maxOpen;
    }

    /** খোলা ও অপেক্ষমাণ টিকেট — সমাধান হওয়াগুলো বোঝা হিসেবে গোনা হয় না। */
    public static function openTicketCount(int $agentId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM tickets t
             JOIN statuses s ON s.id = t.status_id
             WHERE t.assigned_agent_id = ? AND t.deleted_at IS NULL
               AND s.state IN (?, ?)',
            [$agentId, 'open', 'paused']
        );
    }

    /** অ্যাসাইন করার পর round-robin-এর ক্রম এগিয়ে নেওয়া। */
    public static function markAssigned(int $agentId): void
    {
        QueryBuilder::table('agents')->where('id', $agentId)->update(['last_assigned_at' => now()]);
    }
}

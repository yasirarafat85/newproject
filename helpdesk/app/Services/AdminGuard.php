<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\QueryBuilder;

/**
 * অ্যাডমিন প্যানেলের নিরাপত্তা নিয়ম।
 *
 * মূল উদ্দেশ্য একটাই: কোনো কাজেই যেন সিস্টেমটা নিজের অ্যাডমিন হারিয়ে
 * না ফেলে। শেষ সুপার অ্যাডমিন মুছে ফেলা, নিজেকে নিষ্ক্রিয় করা কিংবা
 * নিজের অ্যাডমিন অধিকার কেড়ে নেওয়া — সবই এখানে আটকানো হয়।
 */
final class AdminGuard
{
    public static function superAdminCount(): int
    {
        return QueryBuilder::table('agents')
            ->where('is_admin', 1)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();
    }

    public static function isLastSuperAdmin(int $agentId): bool
    {
        $agent = QueryBuilder::table('agents')->where('id', $agentId)->first();

        if ($agent === null || (int) $agent['is_admin'] !== 1 || $agent['status'] !== 'active') {
            return false;
        }

        return self::superAdminCount() <= 1;
    }

    public static function isSelf(int $agentId): bool
    {
        return $agentId === (int) Auth::agentId();
    }

    /**
     * এজেন্টকে নিষ্ক্রিয় বা মুছে ফেলা যাবে কি না।
     *
     * @return ?string বাধা থাকলে কারণ, নইলে null
     */
    public static function blockDeactivation(int $agentId): ?string
    {
        if (self::isSelf($agentId)) {
            return 'নিজের অ্যাকাউন্ট নিজে নিষ্ক্রিয় করা যায় না।';
        }

        if (self::isLastSuperAdmin($agentId)) {
            return 'সিস্টেমের শেষ সক্রিয় সুপার অ্যাডমিনকে নিষ্ক্রিয় করা যায় না।';
        }

        return null;
    }

    /** নিজের অ্যাডমিন অধিকার কেড়ে নেওয়া ঠেকায়। */
    public static function blockAdminFlagChange(int $agentId, bool $newIsAdmin): ?string
    {
        if ($newIsAdmin) {
            return null;
        }

        if (self::isSelf($agentId)) {
            return 'নিজের অ্যাডমিন অধিকার নিজে সরানো যায় না।';
        }

        if (self::isLastSuperAdmin($agentId)) {
            return 'সিস্টেমে অন্তত একজন সুপার অ্যাডমিন থাকতেই হবে।';
        }

        return null;
    }

    /** ডিপার্টমেন্ট মুছে ফেলা যাবে কি না — টিকেট থাকলে নয়। */
    public static function blockDepartmentDeletion(int $deptId): ?string
    {
        $department = QueryBuilder::table('departments')->where('id', $deptId)->first();
        if ($department === null) {
            return 'ডিপার্টমেন্টটি পাওয়া যায়নি।';
        }

        if ((int) $department['is_default'] === 1) {
            return 'ডিফল্ট ডিপার্টমেন্ট মুছে ফেলা যায় না। আগে অন্য একটিকে ডিফল্ট করুন।';
        }

        $tickets = QueryBuilder::table('tickets')->where('dept_id', $deptId)->count();
        if ($tickets > 0) {
            return "এই ডিপার্টমেন্টে {$tickets}টি টিকেট আছে। মুছে ফেলার বদলে নিষ্ক্রিয় করুন।";
        }

        $children = QueryBuilder::table('departments')->where('parent_id', $deptId)->count();
        if ($children > 0) {
            return 'এর অধীনে অন্য ডিপার্টমেন্ট আছে। আগে সেগুলো সরান।';
        }

        return null;
    }

    /** রোল মুছে ফেলা যাবে কি না — ব্যবহৃত বা সিস্টেম রোল হলে নয়। */
    public static function blockRoleDeletion(int $roleId): ?string
    {
        $role = QueryBuilder::table('roles')->where('id', $roleId)->first();
        if ($role === null) {
            return 'রোলটি পাওয়া যায়নি।';
        }

        if ((int) $role['is_system'] === 1) {
            return 'সিস্টেম রোল মুছে ফেলা যায় না।';
        }

        $inUse = QueryBuilder::table('agents')->where('role_id', $roleId)->whereNull('deleted_at')->count();
        if ($inUse > 0) {
            return "{$inUse} জন এজেন্ট এই রোলে আছেন। আগে তাঁদের অন্য রোলে সরান।";
        }

        return null;
    }

    /** Super Admin রোলের পারমিশন বদলানো যায় না — নইলে কেউই সব কাজ করতে পারবে না। */
    public static function isProtectedRole(array $role): bool
    {
        return $role['name'] === 'Super Admin';
    }
}

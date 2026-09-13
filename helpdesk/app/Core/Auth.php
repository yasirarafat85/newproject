<?php
declare(strict_types=1);

namespace App\Core;

/**
 * দুটি আলাদা গার্ড: `agent` (স্টাফ) ও `client` (গ্রাহক)।
 *
 * দুটো সম্পূর্ণ আলাদা সেশন কী ব্যবহার করে, তাই একজন গ্রাহক কখনো
 * এজেন্ট প্যানেলের রুটে "লগইন করা" হিসেবে গণ্য হবে না।
 */
final class Auth
{
    private const AGENT_KEY = '_auth_agent_id';
    private const CLIENT_KEY = '_auth_client_id';

    private static ?array $agentCache = null;
    private static ?array $clientCache = null;
    private static ?array $permissionCache = null;

    // ---------- এজেন্ট গার্ড ----------

    public static function loginAgent(int $agentId): void
    {
        Session::regenerate();
        Session::put(self::AGENT_KEY, $agentId);
        self::$agentCache = null;
        self::$permissionCache = null;
    }

    public static function agent(): ?array
    {
        if (self::$agentCache !== null) {
            return self::$agentCache;
        }

        $id = Session::get(self::AGENT_KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $agent = QueryBuilder::table('agents')
            ->where('id', (int) $id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if ($agent === null) {
            // অ্যাকাউন্ট নিষ্ক্রিয়/মুছে ফেলা হলে সেশনও অকেজো
            Session::forget(self::AGENT_KEY);

            return null;
        }

        return self::$agentCache = $agent;
    }

    public static function agentId(): ?int
    {
        $agent = self::agent();

        return $agent === null ? null : (int) $agent['id'];
    }

    public static function isAgent(): bool
    {
        return self::agent() !== null;
    }

    public static function isAdmin(): bool
    {
        $agent = self::agent();

        return $agent !== null && (int) $agent['is_admin'] === 1;
    }

    public static function logoutAgent(): void
    {
        Session::forget(self::AGENT_KEY);
        self::$agentCache = null;
        self::$permissionCache = null;
        Session::regenerate();
    }

    // ---------- ক্লায়েন্ট গার্ড ----------

    public static function loginClient(int $userId): void
    {
        Session::regenerate();
        Session::put(self::CLIENT_KEY, $userId);
        self::$clientCache = null;
    }

    public static function client(): ?array
    {
        if (self::$clientCache !== null) {
            return self::$clientCache;
        }

        $id = Session::get(self::CLIENT_KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $user = QueryBuilder::table('users')
            ->where('id', (int) $id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            Session::forget(self::CLIENT_KEY);

            return null;
        }

        return self::$clientCache = $user;
    }

    public static function clientId(): ?int
    {
        $user = self::client();

        return $user === null ? null : (int) $user['id'];
    }

    public static function isClient(): bool
    {
        return self::client() !== null;
    }

    public static function logoutClient(): void
    {
        Session::forget(self::CLIENT_KEY);
        self::$clientCache = null;
        Session::regenerate();
    }

    // ---------- পারমিশন ----------

    /** লগইন করা এজেন্টের সব পারমিশন কোড (গ্লোবাল রোল + ডিপার্টমেন্ট রোল)। */
    public static function permissions(): array
    {
        if (self::$permissionCache !== null) {
            return self::$permissionCache;
        }

        $agent = self::agent();
        if ($agent === null) {
            return self::$permissionCache = [];
        }

        if ((int) $agent['is_admin'] === 1) {
            return self::$permissionCache = ['*'];
        }

        $roleIds = [(int) $agent['role_id']];

        // ডিপার্টমেন্ট-নির্দিষ্ট রোল থাকলে সেটিও যোগ হয় (পারমিশন যোগ হয়, বাদ যায় না)
        foreach (QueryBuilder::table('agent_departments')
            ->select('role_id')
            ->where('agent_id', (int) $agent['id'])
            ->whereNotNull('role_id')
            ->get() as $row) {
            $roleIds[] = (int) $row['role_id'];
        }

        $codes = QueryBuilder::table('permissions')
            ->select('permissions.code')
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_permissions.role_id', array_values(array_unique($roleIds)))
            ->get();

        return self::$permissionCache = array_column($codes, 'code');
    }

    public static function hasPermission(string $code): bool
    {
        $permissions = self::permissions();

        return in_array('*', $permissions, true) || in_array($code, $permissions, true);
    }

    /**
     * অবজেক্ট-সচেতন অনুমতি যাচাই।
     * টিকেট দেওয়া হলে পারমিশনের পাশাপাশি দৃশ্যমানতার সীমাও দেখা হয়।
     */
    public static function can(string $code, ?array $ticket = null): bool
    {
        if (!self::hasPermission($code)) {
            return false;
        }

        if ($ticket === null || self::isAdmin()) {
            return true;
        }

        return self::canSeeTicket($ticket);
    }

    /** টিকেটটি এই এজেন্টের দেখার কথা কি না। */
    public static function canSeeTicket(array $ticket): bool
    {
        $agent = self::agent();
        if ($agent === null) {
            return false;
        }

        if ((int) $agent['is_admin'] === 1 || self::hasPermission('ticket.view_all')) {
            return true;
        }

        $agentId = (int) $agent['id'];

        if ((int) ($ticket['assigned_agent_id'] ?? 0) === $agentId) {
            return true;
        }

        // টিমে থাকলে টিমের টিকেটও দেখা যায়
        $teamId = (int) ($ticket['assigned_team_id'] ?? 0);
        if ($teamId > 0 && in_array($teamId, self::teamIds(), true)) {
            return true;
        }

        if (self::hasPermission('ticket.view_dept')) {
            return in_array((int) $ticket['dept_id'], self::departmentIds(), true);
        }

        return false;
    }

    /** @return int[] */
    public static function departmentIds(): array
    {
        $agent = self::agent();
        if ($agent === null) {
            return [];
        }

        $ids = array_column(
            QueryBuilder::table('agent_departments')->select('dept_id')->where('agent_id', (int) $agent['id'])->get(),
            'dept_id'
        );

        if ($agent['primary_dept_id'] !== null) {
            $ids[] = $agent['primary_dept_id'];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return int[] */
    public static function teamIds(): array
    {
        $agent = self::agent();
        if ($agent === null) {
            return [];
        }

        return array_map('intval', array_column(
            QueryBuilder::table('team_members')->select('team_id')->where('agent_id', (int) $agent['id'])->get(),
            'team_id'
        ));
    }

    /** টেস্ট ও ব্যাচ কাজের জন্য ক্যাশ পরিষ্কার। */
    public static function flush(): void
    {
        self::$agentCache = null;
        self::$clientCache = null;
        self::$permissionCache = null;
    }
}

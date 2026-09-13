<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Hash;
use App\Core\QueryBuilder;
use App\Core\Str;
use PDO;
use RuntimeException;

/**
 * ইনস্টলেশন: ডেটাবেস তৈরি → স্কিমা → ডিফল্ট ডেটা → সুপার অ্যাডমিন → .env লেখা।
 *
 * পুরো কাজটি idempotent — স্কিমা `CREATE TABLE IF NOT EXISTS` ব্যবহার করে
 * আর সিড ডেটা আগে থেকে থাকলে আবার বসায় না।
 */
final class Installer
{
    /** MySQL সংযোগ ও ডেটাবেসের অস্তিত্ব পরীক্ষা; দরকার হলে ডেটাবেস তৈরি করে। */
    public static function prepareDatabase(array $config): void
    {
        try {
            $pdo = Database::connectServer($config);
        } catch (\PDOException $e) {
            throw new RuntimeException(self::connectionHint($e, $config), 0, $e);
        }

        $name = $config['database'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('ডেটাবেসের নামে শুধু অক্ষর, সংখ্যা ও আন্ডারস্কোর ব্যবহার করুন।');
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");

        Database::useConnection($pdo);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** schema.sql চালায়। স্টেটমেন্টগুলো সেমিকোলনে ভাগ করে একে একে চালানো হয়। */
    public static function runSchema(string $schemaPath): int
    {
        if (!is_file($schemaPath)) {
            throw new RuntimeException('স্কিমা ফাইল পাওয়া যায়নি: ' . $schemaPath);
        }

        $sql = (string) file_get_contents($schemaPath);
        $pdo = Database::pdo();
        $count = 0;

        foreach (self::splitStatements($sql) as $statement) {
            try {
                $pdo->exec($statement);
                $count++;
            } catch (\PDOException $e) {
                // পুনরায় ইনস্টলে ALTER TABLE ... ADD CONSTRAINT আগে থেকেই থাকতে পারে
                if (self::isDuplicateObject($e)) {
                    continue;
                }
                throw new RuntimeException('স্কিমা চালাতে সমস্যা: ' . $e->getMessage(), 0, $e);
            }
        }

        return $count;
    }

    /**
     * সেমিকোলনে ভাগ করে; কমেন্ট ও খালি লাইন বাদ দেয়।
     * এই স্কিমায় স্টোরড প্রোসিজার নেই, তাই DELIMITER সামলানোর দরকার পড়ে না।
     *
     * লাইন ভাঙতে `\R` ব্যবহার করা হয় না: /u ছাড়া PCRE বাইট 0x85-কে NEL
     * লাইন-ব্রেক ধরে, আর বাংলা অক্ষরের UTF-8 বাইটের ভেতরেই সেটি থাকতে পারে
     * (যেমন `অ` = E0 A6 85) — ফলে বাংলা কমেন্টের মাঝখানে ভেঙে যায়।
     */
    private static function splitStatements(string $sql): array
    {
        $lines = [];
        foreach (preg_split("/\r\n|\n|\r/", $sql) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $lines[] = $line;
        }

        $statements = [];
        foreach (explode(';', implode("\n", $lines)) as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    private static function isDuplicateObject(\PDOException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');

        // 1826/1022 = ডুপ্লিকেট ফরেন কী/কী নাম, 1061 = ডুপ্লিকেট ইনডেক্স
        return in_array($code, ['1826', '1022', '1061'], true);
    }

    /** ডিফল্ট রোল, পারমিশন, স্ট্যাটাস, প্রায়োরিটি, ডিপার্টমেন্ট ইত্যাদি। */
    public static function seed(): void
    {
        Database::transaction(static function (): void {
            self::seedPermissionsAndRoles();
            self::seedPriorities();
            self::seedStatuses();
            $businessHoursId = self::seedBusinessHours();
            $slaId = self::seedSlaPlans($businessHoursId);
            $deptId = self::seedDepartment($slaId);
            $formId = self::seedDefaultForm();
            self::seedHelpTopics($deptId, $formId);
            self::seedEmailTemplates();
            self::seedSettings();
            self::seedCronJobs();
        });
    }

    private static function seedPermissionsAndRoles(): void
    {
        $definition = Config::get('permissions');

        // পারমিশন
        $permissionIds = [];
        foreach ($definition['groups'] as $group => $data) {
            foreach ($data['codes'] as $code => $label) {
                $existing = QueryBuilder::table('permissions')->where('code', $code)->first();
                if ($existing !== null) {
                    $permissionIds[$code] = (int) $existing['id'];
                    continue;
                }
                $permissionIds[$code] = QueryBuilder::table('permissions')->insert([
                    'code'       => $code,
                    'group_name' => $group,
                    'label'      => $label,
                ]);
            }
        }

        // রোল ও তাদের পারমিশন
        foreach ($definition['roles'] as $name => $data) {
            $role = QueryBuilder::table('roles')->where('name', $name)->first();
            if ($role !== null) {
                continue;
            }

            $roleId = QueryBuilder::table('roles')->insert([
                'name'        => $name,
                'description' => $data['description'],
                'is_system'   => 1,
                'created_at'  => now(),
            ]);

            $codes = $data['permissions'] === '*' ? array_keys($permissionIds) : $data['permissions'];
            foreach ($codes as $code) {
                if (!isset($permissionIds[$code])) {
                    continue;
                }
                QueryBuilder::table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permissionIds[$code],
                ]);
            }
        }
    }

    private static function seedPriorities(): void
    {
        $rows = [
            ['নিম্ন', '#6B7280', 1, 0, 1],
            ['সাধারণ', '#2B62C8', 2, 1, 2],
            ['উচ্চ', '#B06A06', 3, 0, 3],
            ['জরুরি', '#C93F26', 4, 0, 4],
            ['অতি জরুরি', '#7F1D1D', 5, 0, 5],
        ];

        foreach ($rows as [$name, $color, $level, $isDefault, $sort]) {
            if (QueryBuilder::table('priorities')->where('name', $name)->exists()) {
                continue;
            }
            QueryBuilder::table('priorities')->insert([
                'name'       => $name,
                'color'      => $color,
                'level'      => $level,
                'is_default' => $isDefault,
                'sort_order' => $sort,
            ]);
        }
    }

    private static function seedStatuses(): void
    {
        $rows = [
            ['নতুন', 'open', '#7C3AED', 'star', 1, 1, 1],
            ['চলমান', 'open', '#2B62C8', 'play-circle', 0, 1, 2],
            ['গ্রাহকের অপেক্ষায়', 'paused', '#B06A06', 'hourglass', 0, 1, 3],
            ['স্থগিত', 'paused', '#6B7280', 'pause-circle', 0, 1, 4],
            ['সমাধান হয়েছে', 'resolved', '#0E7A55', 'check-circle', 0, 1, 5],
            ['ক্লোজড', 'closed', '#4B5563', 'lock', 0, 1, 6],
            ['আর্কাইভড', 'archived', '#9CA3AF', 'archive', 0, 0, 7],
        ];

        foreach ($rows as [$name, $state, $color, $icon, $isDefault, $reopen, $sort]) {
            if (QueryBuilder::table('statuses')->where('name', $name)->exists()) {
                continue;
            }
            QueryBuilder::table('statuses')->insert([
                'name'          => $name,
                'state'         => $state,
                'color'         => $color,
                'icon'          => $icon,
                'is_default'    => $isDefault,
                'allows_reopen' => $reopen,
                'sort_order'    => $sort,
            ]);
        }
    }

    private static function seedBusinessHours(): int
    {
        $existing = QueryBuilder::table('business_hours')->where('is_default', 1)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $id = QueryBuilder::table('business_hours')->insert([
            'name'       => 'স্বাভাবিক অফিস সময়',
            'timezone'   => (string) Config::get('app.timezone', 'Asia/Dhaka'),
            'is_default' => 1,
            'created_at' => now(),
        ]);

        // বাংলাদেশের সাধারণ সপ্তাহ: রবি–বৃহস্পতি, ৯টা–৬টা (শুক্র-শনি বন্ধ)
        foreach ([0, 1, 2, 3, 4] as $day) {
            QueryBuilder::table('business_hours_slots')->insert([
                'business_hours_id' => $id,
                'day_of_week'       => $day,
                'open_time'         => '09:00:00',
                'close_time'        => '18:00:00',
            ]);
        }

        return $id;
    }

    private static function seedSlaPlans(int $businessHoursId): int
    {
        $existing = QueryBuilder::table('sla_plans')->where('is_default', 1)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $default = QueryBuilder::table('sla_plans')->insert([
            'name'                   => 'সাধারণ SLA',
            'first_response_minutes' => 240,     // ৪ ঘণ্টা
            'resolution_minutes'     => 2880,    // ২ কর্মদিবস
            'business_hours_id'      => $businessHoursId,
            'pause_on_pending'       => 1,
            'warn_at_percent'        => 75,
            'is_default'             => 1,
            'is_active'              => 1,
            'created_at'             => now(),
        ]);

        QueryBuilder::table('sla_plans')->insert([
            'name'                   => 'জরুরি SLA',
            'first_response_minutes' => 60,
            'resolution_minutes'     => 480,
            'business_hours_id'      => $businessHoursId,
            'pause_on_pending'       => 1,
            'warn_at_percent'        => 75,
            'escalate_on_breach'     => 1,
            'is_default'             => 0,
            'is_active'              => 1,
            'created_at'             => now(),
        ]);

        return $default;
    }

    private static function seedDepartment(int $slaId): int
    {
        $existing = QueryBuilder::table('departments')->where('is_default', 1)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return QueryBuilder::table('departments')->insert([
            'name'                => 'সাধারণ সহায়তা',
            'sla_id'              => $slaId,
            'auto_response'       => 1,
            'is_public'           => 1,
            'assignment_strategy' => 'manual',
            'is_default'          => 1,
            'is_active'           => 1,
            'sort_order'          => 1,
            'created_at'          => now(),
        ]);
    }

    private static function seedDefaultForm(): int
    {
        $existing = QueryBuilder::table('forms')->where('name', 'ডিফল্ট টিকেট ফর্ম')->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $formId = QueryBuilder::table('forms')->insert([
            'name'         => 'ডিফল্ট টিকেট ফর্ম',
            'title'        => 'সমস্যার বিবরণ',
            'instructions' => 'যত বিস্তারিত লিখবেন, তত দ্রুত সমাধান পাবেন।',
            'type'         => 'ticket',
            'is_active'    => 1,
            'created_at'   => now(),
        ]);

        // ডেমো কাস্টম ফিল্ড — অ্যাডমিন চাইলে মুছে বা বদলে নিতে পারবেন
        QueryBuilder::table('form_fields')->insert([
            'form_id'     => $formId,
            'field_key'   => 'serial_no',
            'label'       => 'পণ্যের সিরিয়াল নম্বর',
            'hint'        => 'থাকলে লিখুন — সমাধান দ্রুত হবে',
            'type'        => 'text',
            'is_required' => 0,
            'visibility'  => 'all',
            'sort_order'  => 1,
            'is_active'   => 1,
        ]);

        return $formId;
    }

    private static function seedHelpTopics(int $deptId, int $formId): void
    {
        $topics = [
            'সাধারণ জিজ্ঞাসা',
            'কারিগরি সমস্যা',
            'বিলিং ও পেমেন্ট',
            'অভিযোগ',
            'নতুন সেবার অনুরোধ',
        ];

        foreach ($topics as $index => $name) {
            if (QueryBuilder::table('help_topics')->where('name', $name)->exists()) {
                continue;
            }
            QueryBuilder::table('help_topics')->insert([
                'name'       => $name,
                'dept_id'    => $deptId,
                'form_id'    => $formId,
                'is_public'  => 1,
                'is_active'  => 1,
                'sort_order' => $index + 1,
                'created_at' => now(),
            ]);
        }
    }

    private static function seedEmailTemplates(): void
    {
        $templates = [
            ['ticket', 'ticket.created', 'আপনার টিকেট গৃহীত হয়েছে [#{ticket_number}]',
                '<p>প্রিয় {user_name},</p><p>আপনার অনুরোধটি আমরা পেয়েছি। টিকেট নম্বর <strong>#{ticket_number}</strong>।</p>'
                . '<blockquote>{ticket_subject}</blockquote><p>আমাদের একজন প্রতিনিধি শীঘ্রই যোগাযোগ করবেন।</p><p>{signature}</p>'],
            ['ticket', 'ticket.reply', 'আপনার টিকেটে নতুন উত্তর [#{ticket_number}]',
                '<p>প্রিয় {user_name},</p><p>আপনার টিকেটে নতুন উত্তর এসেছে:</p><div>{message}</div>'
                . '<p><a href="{ticket_url}">টিকেটটি দেখুন</a></p><p>{signature}</p>'],
            ['ticket', 'ticket.assigned', 'আপনাকে একটি টিকেট দেওয়া হয়েছে [#{ticket_number}]',
                '<p>{agent_name},</p><p>আপনাকে <strong>#{ticket_number}</strong> — {ticket_subject} টিকেটটি অ্যাসাইন করা হয়েছে।</p>'
                . '<p><a href="{ticket_url}">টিকেটটি খুলুন</a></p>'],
            ['ticket', 'ticket.transferred', 'টিকেট স্থানান্তরিত হয়েছে [#{ticket_number}]',
                '<p>{agent_name},</p><p><strong>#{ticket_number}</strong> টিকেটটি {from_dept} থেকে {to_dept}-এ স্থানান্তর করা হয়েছে।</p>'
                . '<p>কারণ: {reason}</p><p><a href="{ticket_url}">টিকেটটি খুলুন</a></p>'],
            ['ticket', 'ticket.closed', 'আপনার টিকেট সমাধান হয়েছে [#{ticket_number}]',
                '<p>প্রিয় {user_name},</p><p>আপনার টিকেট <strong>#{ticket_number}</strong> সমাধান হিসেবে চিহ্নিত করা হয়েছে।</p>'
                . '<p>আবার প্রয়োজন হলে একই টিকেটে উত্তর দিন।</p><p>{signature}</p>'],
            ['agent', 'agent.reset_password', 'পাসওয়ার্ড রিসেট করার অনুরোধ',
                '<p>{agent_name},</p><p>পাসওয়ার্ড বদলাতে নিচের লিংকে যান (১ ঘণ্টা বৈধ):</p><p><a href="{reset_url}">{reset_url}</a></p>'
                . '<p>আপনি অনুরোধ না করলে এই বার্তাটি উপেক্ষা করুন।</p>'],
            ['user', 'user.welcome', 'আপনার অ্যাকাউন্ট তৈরি হয়েছে',
                '<p>প্রিয় {user_name},</p><p>সাপোর্ট পোর্টালে আপনার অ্যাকাউন্ট তৈরি হয়েছে।</p><p><a href="{portal_url}">পোর্টালে যান</a></p>'],
        ];

        foreach ($templates as [$group, $code, $subject, $body]) {
            if (QueryBuilder::table('email_templates')->where('code', $code)->where('locale', 'bn')->exists()) {
                continue;
            }
            QueryBuilder::table('email_templates')->insert([
                'group_code' => $group,
                'code'       => $code,
                'locale'     => 'bn',
                'subject'    => $subject,
                'body_html'  => $body,
                'is_active'  => 1,
                'updated_at' => now(),
            ]);
        }
    }

    private static function seedSettings(): void
    {
        $settings = [
            ['company_name', 'আমাদের প্রতিষ্ঠান', 'general', 'string'],
            ['support_email', 'support@example.com', 'general', 'string'],
            ['portal_tagline', 'আমরা সাহায্য করতে প্রস্তুত', 'general', 'string'],
            ['allow_guest_tickets', '1', 'ticket', 'bool'],
            ['allow_registration', '1', 'ticket', 'bool'],
            ['notify_client_on_transfer', '0', 'ticket', 'bool'],
            ['default_page_size', '25', 'ticket', 'int'],
            ['auto_close_resolved_days', '3', 'ticket', 'int'],
            ['reopen_window_days', '7', 'ticket', 'int'],
            ['max_attachment_mb', '25', 'ticket', 'int'],
            ['guest_tickets_per_hour', '5', 'security', 'int'],
            ['require_agent_2fa', '0', 'security', 'bool'],
        ];

        foreach ($settings as [$key, $value, $group, $type]) {
            if (QueryBuilder::table('settings')->where('setting_key', $key)->exists()) {
                continue;
            }
            QueryBuilder::table('settings')->insert([
                'setting_key'   => $key,
                'setting_value' => $value,
                'group_name'    => $group,
                'value_type'    => $type,
                'updated_at'    => now(),
            ]);
        }
    }

    private static function seedCronJobs(): void
    {
        foreach (['fetch_mail', 'send_queue', 'sla_check', 'cleanup'] as $job) {
            if (QueryBuilder::table('cron_locks')->where('job_name', $job)->exists()) {
                continue;
            }
            QueryBuilder::table('cron_locks')->insert(['job_name' => $job]);
        }
    }

    /** সুপার অ্যাডমিন তৈরি এবং ডিফল্ট ডিপার্টমেন্টে যুক্ত করা। */
    public static function createSuperAdmin(string $name, string $username, string $email, string $password): int
    {
        $role = QueryBuilder::table('roles')->where('name', 'Super Admin')->first();
        if ($role === null) {
            throw new RuntimeException('Super Admin রোল পাওয়া যায়নি — সিড চালানো হয়নি।');
        }

        $dept = QueryBuilder::table('departments')->where('is_default', 1)->first();

        $agentId = QueryBuilder::table('agents')->insert([
            'uuid'                => Str::uuid(),
            'name'                => $name,
            'username'            => $username,
            'email'               => $email,
            'password_hash'       => Hash::make($password),
            'role_id'             => (int) $role['id'],
            'primary_dept_id'     => $dept === null ? null : (int) $dept['id'],
            'is_admin'            => 1,
            'is_available'        => 1,
            'status'              => 'active',
            'password_changed_at' => now(),
            'created_at'          => now(),
        ]);

        if ($dept !== null) {
            QueryBuilder::table('agent_departments')->insert([
                'agent_id'   => $agentId,
                'dept_id'    => (int) $dept['id'],
                'is_manager' => 1,
            ]);

            QueryBuilder::table('departments')->where('id', (int) $dept['id'])->update(['manager_id' => $agentId]);
        }

        return $agentId;
    }

    /** ইনস্টল শেষে .env লিখে দেয়। */
    public static function writeEnv(string $path, array $values): void
    {
        $template = is_file($path) ? (string) file_get_contents($path) : (string) file_get_contents($path . '.example');

        foreach ($values as $key => $value) {
            $line = $key . '=' . (str_contains((string) $value, ' ') ? '"' . $value . '"' : $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            $template = preg_match($pattern, $template) === 1
                ? (string) preg_replace($pattern, $line, $template)
                : rtrim($template) . "\n" . $line . "\n";
        }

        if (@file_put_contents($path, $template) === false) {
            throw new RuntimeException('.env ফাইল লেখা যায়নি। ফোল্ডারের লেখার অনুমতি (write permission) পরীক্ষা করুন।');
        }
    }

    /**
     * সংযোগ ব্যর্থ হলে কারণভিত্তিক, কাজে লাগার মতো বার্তা।
     * XAMPP ব্যবহারকারীর প্রথম হোঁচট প্রায় সবসময় এই তিনটির একটি।
     */
    private static function connectionHint(\PDOException $e, array $config): string
    {
        $code = (string) ($e->errorInfo[1] ?? $e->getCode());
        $detail = ' (কারিগরি বিবরণ: ' . $e->getMessage() . ')';

        return match (true) {
            str_contains($e->getMessage(), 'Connection refused'), $code === '2002' =>
                "MySQL সার্ভারে সংযোগ করা যায়নি ({$config['host']}:{$config['port']})। "
                . 'XAMPP কন্ট্রোল প্যানেল খুলে MySQL মডিউলটি Start করা আছে কি না দেখুন।' . $detail,

            $code === '1045' =>
                'ইউজারনেম বা পাসওয়ার্ড ঠিক নয়। XAMPP-এ সাধারণত ইউজারনেম `root` আর পাসওয়ার্ড ফাঁকা থাকে।' . $detail,

            $code === '1044' =>
                "`{$config['username']}` ব্যবহারকারীর এই ডেটাবেস তৈরি বা ব্যবহারের অনুমতি নেই। "
                . 'phpMyAdmin থেকে ডেটাবেসটি আগে তৈরি করে নিন অথবা অনুমতি দিন।' . $detail,

            str_contains($e->getMessage(), 'getaddrinfo'), $code === '2005' =>
                "`{$config['host']}` হোস্টটি খুঁজে পাওয়া যায়নি। `localhost`-এর বদলে `127.0.0.1` লিখে দেখুন।" . $detail,

            default => 'ডেটাবেসে সংযোগ করা যায়নি।' . $detail,
        };
    }

    /** ইনস্টলের আগে সার্ভারের প্রস্তুতি পরীক্ষা। */
    public static function requirements(): array
    {
        $basePath = base_path();

        return [
            ['label' => 'PHP 8.2 বা তার বেশি', 'ok' => PHP_VERSION_ID >= 80200, 'value' => PHP_VERSION],
            ['label' => 'PDO MySQL ড্রাইভার', 'ok' => in_array('mysql', \PDO::getAvailableDrivers(), true), 'value' => implode(', ', \PDO::getAvailableDrivers())],
            ['label' => 'mbstring এক্সটেনশন', 'ok' => extension_loaded('mbstring'), 'value' => extension_loaded('mbstring') ? 'আছে' : 'নেই'],
            ['label' => 'openssl এক্সটেনশন', 'ok' => extension_loaded('openssl'), 'value' => extension_loaded('openssl') ? 'আছে' : 'নেই'],
            ['label' => 'fileinfo এক্সটেনশন', 'ok' => extension_loaded('fileinfo'), 'value' => extension_loaded('fileinfo') ? 'আছে' : 'নেই'],
            ['label' => 'storage/ ফোল্ডারে লেখা যায়', 'ok' => is_writable($basePath . '/storage'), 'value' => is_writable($basePath . '/storage') ? 'হ্যাঁ' : 'না'],
            ['label' => '.env লেখা যায়', 'ok' => is_writable($basePath) || is_writable($basePath . '/.env'), 'value' => is_writable($basePath) ? 'হ্যাঁ' : 'না'],
        ];
    }
}

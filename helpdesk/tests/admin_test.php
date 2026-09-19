<?php
declare(strict_types=1);

/**
 * অ্যাডমিন প্যানেল — CRUD, অনুমতি ও লকআউট-প্রতিরোধ।
 *
 * সবচেয়ে গুরুত্বপূর্ণ যা যাচাই হয়: কোনো পথেই যেন সিস্টেমটা নিজের
 * শেষ অ্যাডমিন হারিয়ে না ফেলে।
 */

require __DIR__ . '/support/bootstrap.php';
require __DIR__ . '/support/sqlite.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Hash;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\Str;
use App\Services\AdminGuard;
use App\Services\Installer;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pass = 0;
$fail = 0;

function check(string $label, mixed $actual, mixed $expected): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "  ✓ {$label}\n";
    } else {
        $fail++;
        echo "  ✗ {$label}\n      পেয়েছি   : " . var_export($actual, true) . "\n      চেয়েছিলাম: " . var_export($expected, true) . "\n";
    }
}

function checkTrue(string $label, bool $value): void
{
    check($label, $value, true);
}

/** @return array{0:int,1:string} */
function call(Router $router, string $method, string $path, array $body = [], array $query = []): array
{
    if ($method !== 'GET' && !array_key_exists('_token', $body)) {
        $body['_token'] = Csrf::token();
    }

    $request = new Request($query, $body, [], [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI'    => $path,
        'SCRIPT_NAME'    => '/index.php',
        'REMOTE_ADDR'    => '127.0.0.1',
        'HTTP_HOST'      => 'helpdesk.test',
    ]);

    try {
        $response = $router->dispatch($request);
    } catch (ValidationException $e) {
        return [422, implode(' | ', $e->errors())];
    } catch (HttpException $e) {
        return [$e->getStatusCode(), $e->getMessage()];
    }

    return [$response->getStatus(), $response->getContent()];
}

// ---------- প্রস্তুতি ----------
sqlite_boot(dirname(__DIR__) . '/database/schema.sql');
Installer::seed();
$adminId = Installer::createSuperAdmin('করিম উদ্দিন', 'karim', 'karim@example.com', 'গোপন-পাসওয়ার্ড-১২৩');

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';

Session::invalidate();
Auth::loginAgent($adminId);
Auth::flush();

echo "\n--- অ্যাডমিন পাতা খোলে ---\n";
foreach ([
    '/admin'             => 'অ্যাডমিন হোম',
    '/admin/agents'      => 'এজেন্ট তালিকা',
    '/admin/departments' => 'ডিপার্টমেন্ট তালিকা',
    '/admin/teams'       => 'টিম তালিকা',
    '/admin/roles'       => 'রোল তালিকা',
    '/admin/topics'      => 'টপিক তালিকা',
    '/admin/settings'    => 'সেটিংস',
    '/admin/logs'        => 'অ্যাক্টিভিটি লগ',
] as $path => $label) {
    check($label, call($router, 'GET', $path)[0], 200);
}

echo "\n--- এজেন্ট তৈরি ও সম্পাদনা ---\n";
$agentRoleId = (int) QueryBuilder::table('roles')->where('name', 'Agent')->value('id');
$deptId = (int) QueryBuilder::table('departments')->where('is_default', 1)->value('id');

[$status] = call($router, 'POST', '/admin/agents', [
    'name' => 'রহিম মিয়া', 'username' => 'rahim', 'email' => 'rahim@example.com',
    'password' => 'নতুন-পাসওয়ার্ড-৮', 'password_confirmation' => 'নতুন-পাসওয়ার্ড-৮',
    'role_id' => $agentRoleId, 'primary_dept_id' => $deptId, 'is_available' => '1', 'status' => 'active',
]);
check('এজেন্ট তৈরি হয়', $status, 302);

$rahim = QueryBuilder::table('agents')->where('username', 'rahim')->first();
checkTrue('ডেটাবেসে যোগ হয়েছে', $rahim !== null);
checkTrue('পাসওয়ার্ড হ্যাশ হয়েছে', Hash::check('নতুন-পাসওয়ার্ড-৮', (string) $rahim['password_hash']));
check('প্রাইমারি ডিপার্টমেন্ট সদস্যপদে যোগ হয়েছে',
    QueryBuilder::table('agent_departments')->where('agent_id', (int) $rahim['id'])->where('dept_id', $deptId)->count(), 1);

check('একই ইউজারনেমে দ্বিতীয়বার আটকায়',
    call($router, 'POST', '/admin/agents', [
        'name' => 'আরেকজন', 'username' => 'rahim', 'email' => 'onno@example.com',
        'password' => 'পাসওয়ার্ড-১২৩৪', 'password_confirmation' => 'পাসওয়ার্ড-১২৩৪',
        'role_id' => $agentRoleId,
    ])[0], 422);

check('ছোট পাসওয়ার্ড আটকায়',
    call($router, 'POST', '/admin/agents', [
        'name' => 'তৃতীয়', 'username' => 'tritiyo', 'email' => 't@example.com',
        'password' => 'ছোট', 'password_confirmation' => 'ছোট', 'role_id' => $agentRoleId,
    ])[0], 422);

$rahimId = (int) $rahim['id'];
$oldHash = (string) $rahim['password_hash'];

[$status] = call($router, 'POST', '/admin/agents/' . $rahimId, [
    'name' => 'রহিম উদ্দিন', 'username' => 'rahim', 'email' => 'rahim@example.com',
    'role_id' => $agentRoleId, 'primary_dept_id' => $deptId, 'status' => 'active',
]);
check('সম্পাদনা সফল', $status, 302);
$updated = QueryBuilder::table('agents')->where('id', $rahimId)->first();
check('নাম বদলেছে', $updated['name'], 'রহিম উদ্দিন');
check('পাসওয়ার্ড ফাঁকা রাখলে আগেরটিই থাকে', $updated['password_hash'], $oldHash);

echo "\n--- লকআউট প্রতিরোধ ---\n";
checkTrue('এখন একজনই সুপার অ্যাডমিন', AdminGuard::superAdminCount() === 1);
checkTrue('শেষ সুপার অ্যাডমিন হিসেবে চিহ্নিত', AdminGuard::isLastSuperAdmin($adminId));

[$status] = call($router, 'POST', '/admin/agents/' . $adminId, [
    'name' => 'করিম উদ্দিন', 'username' => 'karim', 'email' => 'karim@example.com',
    'role_id' => $agentRoleId, 'status' => 'active',   // is_admin পাঠানো হয়নি = বন্ধ
]);
check('নিজের অ্যাডমিন অধিকার সরানো যায় না',
    (int) QueryBuilder::table('agents')->where('id', $adminId)->value('is_admin'), 1);

[$status] = call($router, 'POST', '/admin/agents/' . $adminId . '/delete');
check('নিজেকে মুছে ফেলা যায় না',
    QueryBuilder::table('agents')->where('id', $adminId)->whereNull('deleted_at')->count(), 1);

// দ্বিতীয় অ্যাডমিন বানিয়ে দেখি — তখন প্রথমজনকে সরানো যাবে
QueryBuilder::table('agents')->where('id', $rahimId)->update(['is_admin' => 1]);
checkTrue('দুজন অ্যাডমিন হলে আর "শেষ" নন', !AdminGuard::isLastSuperAdmin($adminId));
check('তবু নিজেকে সরানো যায় না', AdminGuard::blockDeactivation($adminId), 'নিজের অ্যাকাউন্ট নিজে নিষ্ক্রিয় করা যায় না।');
check('অন্য অ্যাডমিনকে সরানো যায়', AdminGuard::blockDeactivation($rahimId), null);

QueryBuilder::table('agents')->where('id', $rahimId)->update(['is_admin' => 0]);

echo "\n--- ডিপার্টমেন্ট ---\n";
[$status] = call($router, 'POST', '/admin/departments', [
    'name' => 'কারিগরি সহায়তা', 'assignment_strategy' => 'least_load',
    'is_active' => '1', 'is_public' => '1', 'sort_order' => '2',
]);
check('ডিপার্টমেন্ট তৈরি', $status, 302);

$tech = QueryBuilder::table('departments')->where('name', 'কারিগরি সহায়তা')->first();
check('অ্যাসাইনমেন্ট কৌশল সংরক্ষিত', $tech['assignment_strategy'], 'least_load');

$techId = (int) $tech['id'];
[$status] = call($router, 'POST', '/admin/departments/' . $techId, [
    'name' => 'কারিগরি সহায়তা', 'assignment_strategy' => 'manual',
    'is_active' => '1', 'is_public' => '1', 'is_default' => '1', 'sort_order' => '2',
]);
check('ডিফল্ট ফ্ল্যাগ সরানো হয়',
    (int) QueryBuilder::table('departments')->where('is_default', 1)->count(), 1);
check('নতুনটিই এখন ডিফল্ট',
    (int) QueryBuilder::table('departments')->where('is_default', 1)->value('id'), $techId);

check('ডিফল্ট ডিপার্টমেন্ট মোছা যায় না',
    AdminGuard::blockDepartmentDeletion($techId),
    'ডিফল্ট ডিপার্টমেন্ট মুছে ফেলা যায় না। আগে অন্য একটিকে ডিফল্ট করুন।');

check('অবৈধ কৌশল ম্যানুয়ালে নেমে আসে',
    (function () use ($router, $techId): string {
        call($router, 'POST', '/admin/departments/' . $techId, [
            'name' => 'কারিগরি সহায়তা', 'assignment_strategy' => 'ভুল-মান',
            'is_active' => '1', 'is_default' => '1',
        ]);

        return (string) QueryBuilder::table('departments')->where('id', $techId)->value('assignment_strategy');
    })(), 'manual');

echo "\n--- টিম ---\n";
[$status] = call($router, 'POST', '/admin/teams', [
    'name' => 'Tier 2', 'lead_agent_id' => $rahimId, 'members' => [], 'is_active' => '1',
]);
check('টিম তৈরি', $status, 302);

$team = QueryBuilder::table('teams')->where('name', 'Tier 2')->first();
check('লিড স্বয়ংক্রিয়ভাবে সদস্য হয়েছেন',
    QueryBuilder::table('team_members')->where('team_id', (int) $team['id'])->where('agent_id', $rahimId)->count(), 1);

echo "\n--- রোল ও পারমিশন ---\n";
[$status] = call($router, 'POST', '/admin/roles', [
    'name' => 'কাস্টম রোল', 'description' => 'পরীক্ষার জন্য',
    'permissions' => ['ticket.view_dept', 'ticket.reply'],
]);
check('রোল তৈরি', $status, 302);

$custom = QueryBuilder::table('roles')->where('name', 'কাস্টম রোল')->first();
check('দুটি পারমিশন যুক্ত হয়েছে',
    QueryBuilder::table('role_permissions')->where('role_id', (int) $custom['id'])->count(), 2);

$superAdmin = QueryBuilder::table('roles')->where('name', 'Super Admin')->first();
$before = QueryBuilder::table('role_permissions')->where('role_id', (int) $superAdmin['id'])->count();

call($router, 'POST', '/admin/roles/' . (int) $superAdmin['id'], [
    'name' => 'Super Admin', 'description' => 'বদলানোর চেষ্টা', 'permissions' => ['kb.view'],
]);
check('Super Admin-এর পারমিশন সুরক্ষিত',
    QueryBuilder::table('role_permissions')->where('role_id', (int) $superAdmin['id'])->count(), $before);
check('তবু নামের বিবরণ বদলেছে',
    QueryBuilder::table('roles')->where('id', (int) $superAdmin['id'])->value('description'), 'বদলানোর চেষ্টা');

check('সিস্টেম রোল মোছা যায় না',
    AdminGuard::blockRoleDeletion((int) $superAdmin['id']), 'সিস্টেম রোল মুছে ফেলা যায় না।');
check('সিস্টেম রোলে ব্যবহারের আগেই সিস্টেম-বার্তা আসে',
    AdminGuard::blockRoleDeletion($agentRoleId), 'সিস্টেম রোল মুছে ফেলা যায় না।');
check('অব্যবহৃত কাস্টম রোল মোছা যায়', AdminGuard::blockRoleDeletion((int) $custom['id']), null);

// কাস্টম রোলে একজন এজেন্ট বসিয়ে "ব্যবহৃত" নিয়মটি আলাদা করে দেখি
QueryBuilder::table('agents')->where('id', $rahimId)->update(['role_id' => (int) $custom['id']]);
check('ব্যবহৃত কাস্টম রোল মোছা যায় না',
    AdminGuard::blockRoleDeletion((int) $custom['id']), '1 জন এজেন্ট এই রোলে আছেন। আগে তাঁদের অন্য রোলে সরান।');
check('মুছে ফেলার চেষ্টাও ব্যর্থ হয়',
    (function () use ($router, $custom): int {
        call($router, 'POST', '/admin/roles/' . (int) $custom['id'] . '/delete');

        return QueryBuilder::table('roles')->where('id', (int) $custom['id'])->count();
    })(), 1);

QueryBuilder::table('agents')->where('id', $rahimId)->update(['role_id' => $agentRoleId]);

echo "\n--- সেটিংস ---\n";
[$status] = call($router, 'POST', '/admin/settings', [
    'settings' => [
        'company_name'        => 'নতুন প্রতিষ্ঠান',
        'default_page_size'   => '50',
        'allow_guest_tickets' => '1',
        // allow_registration পাঠানো হয়নি = চেকবক্স আনচেক
    ],
]);
check('সেটিংস সংরক্ষণ', $status, 302);
check('টেক্সট সেটিং বদলেছে',
    QueryBuilder::table('settings')->where('setting_key', 'company_name')->value('setting_value'), 'নতুন প্রতিষ্ঠান');
check('সংখ্যা সেটিং বদলেছে',
    QueryBuilder::table('settings')->where('setting_key', 'default_page_size')->value('setting_value'), '50');
check('আনচেক করা চেকবক্স বন্ধ হয়েছে',
    QueryBuilder::table('settings')->where('setting_key', 'allow_registration')->value('setting_value'), '0');

call($router, 'POST', '/admin/settings', ['settings' => ['default_page_size' => 'লেখা']]);
check('সংখ্যার ঘরে লেখা দিলে 0 হয়',
    QueryBuilder::table('settings')->where('setting_key', 'default_page_size')->value('setting_value'), '0');

echo "\n--- অনুমতিহীন এজেন্ট ---\n";
Session::invalidate();
$limitedRoleId = (int) QueryBuilder::table('roles')->where('name', 'Limited Agent')->value('id');
$limitedId = QueryBuilder::table('agents')->insert([
    'uuid' => Str::uuid(), 'name' => 'সীমিত', 'username' => 'limited2',
    'email' => 'limited2@example.com', 'password_hash' => 'x', 'role_id' => $limitedRoleId,
    'is_admin' => 0, 'status' => 'active', 'created_at' => now(),
]);

Auth::loginAgent($limitedId);
Auth::flush();

check('অ্যাডমিন হোম বন্ধ', call($router, 'GET', '/admin')[0], 403);
check('এজেন্ট ব্যবস্থাপনা বন্ধ', call($router, 'GET', '/admin/agents')[0], 403);
check('রোল ব্যবস্থাপনা বন্ধ', call($router, 'GET', '/admin/roles')[0], 403);
check('POST দিয়েও এজেন্ট বানানো যায় না',
    call($router, 'POST', '/admin/agents', [
        'name' => 'চোরা', 'username' => 'chora', 'email' => 'c@example.com',
        'password' => 'পাসওয়ার্ড-১২৩৪', 'password_confirmation' => 'পাসওয়ার্ড-১২৩৪', 'role_id' => $agentRoleId,
    ])[0], 403);
check('সত্যিই তৈরি হয়নি', QueryBuilder::table('agents')->where('username', 'chora')->count(), 0);

// admin.logs পারমিশনওয়ালা রোল — শুধু লগ দেখতে পারবেন
$auditorRoleId = QueryBuilder::table('roles')->insert([
    'name' => 'নিরীক্ষক', 'description' => 'শুধু লগ', 'is_system' => 0, 'created_at' => now(),
]);
$logsPermId = (int) QueryBuilder::table('permissions')->where('code', 'admin.logs')->value('id');
QueryBuilder::table('role_permissions')->insert(['role_id' => $auditorRoleId, 'permission_id' => $logsPermId]);
QueryBuilder::table('agents')->where('id', $limitedId)->update(['role_id' => $auditorRoleId]);
Auth::flush();

check('নিরীক্ষক লগ দেখতে পারেন', call($router, 'GET', '/admin/logs')[0], 200);
check('তবু এজেন্ট ব্যবস্থাপনা বন্ধ', call($router, 'GET', '/admin/agents')[0], 403);

echo "\n=======================================\n";
echo "  পাস: {$pass}   ব্যর্থ: {$fail}\n";
echo "=======================================\n";
exit($fail === 0 ? 0 : 1);

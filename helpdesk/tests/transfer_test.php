<?php
declare(strict_types=1);

/**
 * ট্রান্সফার, ফরওয়ার্ডিং, অটো-অ্যাসাইনমেন্ট ও কোলাবোরেটর।
 * আসল ডেটাবেসের বিরুদ্ধে — SQLite-এ অনুবাদ করা স্কিমায়।
 */

require __DIR__ . '/support/bootstrap.php';
require __DIR__ . '/support/sqlite.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\Str;
use App\Models\Ticket;
use App\Services\AssignmentService;
use App\Services\CollaboratorService;
use App\Services\ExternalForwardService;
use App\Services\Installer;
use App\Services\TicketService;
use App\Services\TransferService;

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

function throws(string $label, callable $fn, string $contains = ''): void
{
    global $pass, $fail;
    try {
        $fn();
        $fail++;
        echo "  ✗ {$label} — কোনো exception আসেনি\n";
    } catch (Throwable $e) {
        if ($contains !== '' && !str_contains($e->getMessage(), $contains)) {
            $fail++;
            echo "  ✗ {$label}\n      বার্তা: " . $e->getMessage() . "\n";

            return;
        }
        $pass++;
        echo "  ✓ {$label}\n";
    }
}

/** @return array{0:int,1:string} */
function call(Router $router, string $method, string $path, array $body = []): array
{
    if ($method !== 'GET' && !array_key_exists('_token', $body)) {
        $body['_token'] = Csrf::token();
    }

    $request = new Request([], $body, [], [
        'REQUEST_METHOD' => $method, 'REQUEST_URI' => $path,
        'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'h.test',
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

function makeAgent(string $name, string $username, int $roleId, ?int $deptId, bool $available = true): int
{
    $id = QueryBuilder::table('agents')->insert([
        'uuid' => Str::uuid(), 'name' => $name, 'username' => $username,
        'email' => $username . '@example.com', 'password_hash' => 'x',
        'role_id' => $roleId, 'primary_dept_id' => $deptId, 'is_admin' => 0,
        'is_available' => $available ? 1 : 0, 'status' => 'active', 'created_at' => now(),
    ]);

    if ($deptId !== null) {
        QueryBuilder::table('agent_departments')->insert([
            'agent_id' => $id, 'dept_id' => $deptId, 'is_manager' => 0, 'alerts_enabled' => 1,
        ]);
    }

    return $id;
}

// ---------- প্রস্তুতি ----------
sqlite_boot(dirname(__DIR__) . '/database/schema.sql');
Installer::seed();
$adminId = Installer::createSuperAdmin('করিম', 'karim', 'karim@example.com', 'পাসওয়ার্ড-১২৩৪');

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';

$salesId = (int) QueryBuilder::table('departments')->where('is_default', 1)->value('id');
$techId = QueryBuilder::table('departments')->insert([
    'name' => 'কারিগরি', 'is_active' => 1, 'is_public' => 1,
    'assignment_strategy' => 'manual', 'sort_order' => 2, 'created_at' => now(),
]);

$seniorRoleId = (int) QueryBuilder::table('roles')->where('name', 'Senior Agent')->value('id');
$agentRoleId  = (int) QueryBuilder::table('roles')->where('name', 'Agent')->value('id');

$rahim = makeAgent('রহিম', 'rahim', $seniorRoleId, $salesId);
$sumon = makeAgent('সুমন', 'sumon', $seniorRoleId, $techId);
$nadia = makeAgent('নাদিয়া', 'nadia', $agentRoleId, $techId);

$userId = QueryBuilder::table('users')->insert([
    'uuid' => Str::uuid(), 'name' => 'রহিমা বেগম', 'email' => 'rahima@acme.com',
    'status' => 'active', 'created_at' => now(),
]);

Session::invalidate();
Auth::loginAgent($adminId);
Auth::flush();

$ticket = TicketService::create([
    'user_id' => $userId, 'subject' => 'প্রিন্টার কাজ করছে না',
    'body' => 'ট্রে ২ আটকে যাচ্ছে', 'dept_id' => $salesId,
]);
$ticketId = (int) $ticket['id'];

echo "\n--- ডিপার্টমেন্ট ট্রান্সফার ---\n";
TransferService::toDepartment(Ticket::findById($ticketId), $techId, $adminId, 'হার্ডওয়্যার ইস্যু');
$after = Ticket::findById($ticketId);

check('ডিপার্টমেন্ট বদলেছে', (int) $after['dept_id'], $techId);
check('অ্যাসাইনমেন্ট ক্লিয়ার হয়েছে', $after['assigned_agent_id'], null);
check('অডিট রো লেখা হয়েছে',
    QueryBuilder::table('ticket_transfers')->where('ticket_id', $ticketId)->where('transfer_type', 'department')->count(), 1);

$audit = QueryBuilder::table('ticket_transfers')->where('ticket_id', $ticketId)->orderBy('id', 'DESC')->first();
check('কোথা থেকে সঠিক', (int) $audit['from_dept_id'], $salesId);
check('কোথায় সঠিক', (int) $audit['to_dept_id'], $techId);
check('কারণ সংরক্ষিত', $audit['reason'], 'হার্ডওয়্যার ইস্যু');
check('কে করেছেন সংরক্ষিত', (int) $audit['by_agent_id'], $adminId);
check('স্বয়ংক্রিয় নয়', (int) $audit['is_automatic'], 0);

$systemThreads = QueryBuilder::table('ticket_threads')->where('ticket_id', $ticketId)->where('type', 'system')->get();
checkTrue('থ্রেডে সিস্টেম এন্ট্রি আছে',
    str_contains((string) end($systemThreads)['body_html'], 'কারিগরি'));
checkTrue('কারণও এন্ট্রিতে আছে',
    str_contains((string) end($systemThreads)['body_html'], 'হার্ডওয়্যার ইস্যু'));

throws('একই ডিপার্টমেন্টে আবার পাঠানো যায় না',
    static fn () => TransferService::toDepartment(Ticket::findById($ticketId), $techId, $adminId),
    'ইতিমধ্যে এই ডিপার্টমেন্টেই');

QueryBuilder::table('departments')->insert([
    'name' => 'বন্ধ বিভাগ', 'is_active' => 0, 'sort_order' => 9, 'created_at' => now(),
]);
$closedDeptId = (int) QueryBuilder::table('departments')->where('name', 'বন্ধ বিভাগ')->value('id');
throws('নিষ্ক্রিয় ডিপার্টমেন্টে পাঠানো যায় না',
    static fn () => TransferService::toDepartment(Ticket::findById($ticketId), $closedDeptId, $adminId),
    'নিষ্ক্রিয়');

echo "\n--- এজেন্ট অ্যাসাইনমেন্ট ---\n";
TransferService::toAgent(Ticket::findById($ticketId), $sumon, $adminId, 'সুমন এই যন্ত্র চেনেন');
$after = Ticket::findById($ticketId);
check('এজেন্ট অ্যাসাইন হয়েছে', (int) $after['assigned_agent_id'], $sumon);

throws('ভিন্ন ডিপার্টমেন্টের এজেন্টকে দেওয়া যায় না',
    static fn () => TransferService::toAgent(Ticket::findById($ticketId), $rahim, $adminId),
    'সদস্য নন');

throws('একই এজেন্টকে দুবার দেওয়া যায় না',
    static fn () => TransferService::toAgent(Ticket::findById($ticketId), $sumon, $adminId),
    'ইতিমধ্যে এই এজেন্টের কাছেই');

check('পুরনো এজেন্ট নোটিফিকেশন পেয়েছেন',
    QueryBuilder::table('notifications')->where('agent_id', $sumon)->where('type', 'ticket.assigned')->count(), 1);

echo "\n--- টিম ও ছেড়ে দেওয়া ---\n";
$teamId = QueryBuilder::table('teams')->insert([
    'name' => 'Tier 2', 'lead_agent_id' => $nadia, 'notify_lead' => 1, 'is_active' => 1, 'created_at' => now(),
]);
QueryBuilder::table('team_members')->insert(['team_id' => $teamId, 'agent_id' => $nadia]);

TransferService::toTeam(Ticket::findById($ticketId), $teamId, $adminId, 'টিয়ার ২ দেখুক');
$after = Ticket::findById($ticketId);
check('টিম অ্যাসাইন হয়েছে', (int) $after['assigned_team_id'], $teamId);
check('ব্যক্তিগত অ্যাসাইনমেন্ট ছেড়ে দেওয়া হয়েছে', $after['assigned_agent_id'], null);
check('টিম লিড নোটিফিকেশন পেয়েছেন',
    QueryBuilder::table('notifications')->where('agent_id', $nadia)->where('type', 'ticket.team')->count(), 1);

TransferService::release(Ticket::findById($ticketId), $adminId, 'ভুল করে দেওয়া হয়েছিল');
$after = Ticket::findById($ticketId);
check('ছেড়ে দেওয়ার পর কেউ নেই', [$after['assigned_agent_id'], $after['assigned_team_id']], [null, null]);
check('ডিপার্টমেন্ট অপরিবর্তিত', (int) $after['dept_id'], $techId);

throws('আনঅ্যাসাইনড টিকেট আবার ছাড়া যায় না',
    static fn () => TransferService::release(Ticket::findById($ticketId), $adminId),
    'এমনিতেই কারও দায়িত্বে নেই');

echo "\n--- SLA-র ঘড়ি ---\n";
$before = Ticket::findById($ticketId);
TransferService::toDepartment($before, $salesId, $adminId, '', TransferService::SLA_KEEP);
check('keep — ডেডলাইন অপরিবর্তিত',
    (string) Ticket::findById($ticketId)['due_at'], (string) $before['due_at']);

$before = Ticket::findById($ticketId);
TransferService::toDepartment($before, $techId, $adminId, '', TransferService::SLA_EXTEND);
checkTrue('extend — ডেডলাইন পিছিয়েছে',
    strtotime((string) Ticket::findById($ticketId)['due_at']) > strtotime((string) $before['due_at']));

// ম্যানেজার নন এমন এজেন্ট reset করতে পারবেন না
Auth::logoutAgent();
Auth::loginAgent($sumon);
Auth::flush();
checkTrue('সাধারণ এজেন্ট SLA reset করতে পারেন না',
    !TransferService::mayResetSla(Ticket::findById($ticketId), $salesId));
throws('reset চেষ্টা আটকায়',
    static fn () => TransferService::toDepartment(Ticket::findById($ticketId), $salesId, $sumon, '', TransferService::SLA_RESET),
    'শুধু ম্যানেজার');

QueryBuilder::table('agent_departments')->where('agent_id', $sumon)->where('dept_id', $techId)->update(['is_manager' => 1]);
checkTrue('ম্যানেজার হলে পারেন', TransferService::mayResetSla(Ticket::findById($ticketId), $salesId));

$before = Ticket::findById($ticketId);
TransferService::toDepartment($before, $salesId, $sumon, '', TransferService::SLA_RESET);
$reset = Ticket::findById($ticketId);
check('reset — পজ করা সময় মুছে গেছে', (int) $reset['sla_paused_seconds'], 0);
checkTrue('reset — নতুন ডেডলাইন বসেছে', $reset['due_at'] !== $before['due_at']);
check('অডিটে sla_action লেখা আছে',
    QueryBuilder::table('ticket_transfers')->where('ticket_id', $ticketId)->orderBy('id', 'DESC')->value('sla_action'), 'reset');

Auth::logoutAgent();
Auth::loginAgent($adminId);
Auth::flush();

echo "\n--- অটো-অ্যাসাইনমেন্ট ---\n";
// round_robin: যাঁকে কখনো দেওয়া হয়নি তিনি আগে
QueryBuilder::table('departments')->where('id', $techId)->update(['assignment_strategy' => 'round_robin']);
QueryBuilder::table('agents')->whereIn('id', [$sumon, $nadia])->update(['last_assigned_at' => null]);

$t1 = TicketService::create(['user_id' => $userId, 'subject' => 'RR ১', 'body' => 'x', 'dept_id' => $techId]);
$first = (int) Ticket::findById((int) $t1['id'])['assigned_agent_id'];
checkTrue('round_robin — প্রথম টিকেট কেউ পেয়েছেন', in_array($first, [$sumon, $nadia], true));

$t2 = TicketService::create(['user_id' => $userId, 'subject' => 'RR ২', 'body' => 'x', 'dept_id' => $techId]);
$second = (int) Ticket::findById((int) $t2['id'])['assigned_agent_id'];
check('round_robin — দ্বিতীয়টি অন্যজন পেয়েছেন', $second === $first, false);

// ছুটিতে থাকলে বাদ
QueryBuilder::table('agents')->where('id', $nadia)->update(['is_available' => 0]);
QueryBuilder::table('agents')->whereIn('id', [$sumon, $nadia])->update(['last_assigned_at' => null]);
$t3 = TicketService::create(['user_id' => $userId, 'subject' => 'RR ৩', 'body' => 'x', 'dept_id' => $techId]);
check('অটো-অ্যাসাইনে না থাকলে বাদ পড়েন',
    (int) Ticket::findById((int) $t3['id'])['assigned_agent_id'], $sumon);
QueryBuilder::table('agents')->where('id', $nadia)->update(['is_available' => 1]);

// least_load: যাঁর খোলা টিকেট কম
QueryBuilder::table('departments')->where('id', $techId)->update(['assignment_strategy' => 'least_load']);
$sumonLoad = AssignmentService::openTicketCount($sumon);
$nadiaLoad = AssignmentService::openTicketCount($nadia);
$t4 = TicketService::create(['user_id' => $userId, 'subject' => 'LL ১', 'body' => 'x', 'dept_id' => $techId]);
check('least_load — কম বোঝা যাঁর তিনি পেয়েছেন',
    (int) Ticket::findById((int) $t4['id'])['assigned_agent_id'],
    $sumonLoad <= $nadiaLoad ? $sumon : $nadia);

// max_open_tickets সীমা
QueryBuilder::table('agents')->where('id', $sumon)->update(['max_open_tickets' => 0]);
QueryBuilder::table('agents')->where('id', $nadia)->update(['max_open_tickets' => 0]);
$t5 = TicketService::create(['user_id' => $userId, 'subject' => 'সীমা', 'body' => 'x', 'dept_id' => $techId]);
check('সবাই সীমায় পৌঁছালে আনঅ্যাসাইনড থাকে',
    Ticket::findById((int) $t5['id'])['assigned_agent_id'], null);
QueryBuilder::table('agents')->whereIn('id', [$sumon, $nadia])->update(['max_open_tickets' => null]);

// manual-এ কেউ পায় না
QueryBuilder::table('departments')->where('id', $techId)->update(['assignment_strategy' => 'manual']);
$t6 = TicketService::create(['user_id' => $userId, 'subject' => 'ম্যানুয়াল', 'body' => 'x', 'dept_id' => $techId]);
check('manual — কেউ অ্যাসাইন হয় না', Ticket::findById((int) $t6['id'])['assigned_agent_id'], null);

echo "\n--- বাইরে ফরওয়ার্ড ---\n";
$fresh = Ticket::detail($ticketId);
$result = ExternalForwardService::forward($fresh, $adminId, 'vendor@example.com', '<p>এই যন্ত্রটি দেখে দিন।</p>', ['cc@example.com']);

checkTrue('টোকেন তৈরি হয়েছে', (bool) preg_match('/^fw_\d+_[a-f0-9]{32}$/', $result['token']));
check('ফরওয়ার্ড রো লেখা হয়েছে',
    QueryBuilder::table('ticket_external_forwards')->where('ticket_id', $ticketId)->count(), 1);
check('মেইল কিউতে গেছে',
    QueryBuilder::table('email_queue')->where('ticket_id', $ticketId)->where('status', 'pending')->count(), 1);

$fwdThread = QueryBuilder::table('ticket_threads')->where('ticket_id', $ticketId)->where('type', 'forward')->first();
check('থ্রেডে ফরওয়ার্ড এন্ট্রি', $fwdThread !== null, true);
check('ফরওয়ার্ড এন্ট্রি ইন্টার্নাল', (int) $fwdThread['is_internal'], 1);
checkTrue('প্রাপকের ঠিকানা এন্ট্রিতে আছে', str_contains((string) $fwdThread['body_html'], 'vendor@example.com'));

check('গ্রাহক ফরওয়ার্ড দেখেন না',
    in_array('forward', array_column(Ticket::threads($ticketId, false), 'type'), true), false);

$found = ExternalForwardService::findByToken('Reply-To: support+' . $result['token'] . '@company.com');
checkTrue('হেডার থেকে টোকেন খুঁজে পাওয়া যায়', $found !== null);
check('সঠিক টিকেটে মেলে', (int) $found['ticket_id'], $ticketId);
check('ভুল টোকেনে কিছু মেলে না', ExternalForwardService::findByToken('fw_9_' . str_repeat('a', 32)), null);

checkTrue('reply-to ঠিকানায় টোকেন বসে',
    str_contains(ExternalForwardService::replyToAddress($result['token']), '+' . $result['token'] . '@'));

throws('নিজের সাপোর্ট ঠিকানায় ফরওয়ার্ড আটকায়',
    static fn () => ExternalForwardService::forward(Ticket::detail($ticketId), $adminId, (string) setting('support_email'), '<p>x</p>'),
    'লুপ');
throws('ভুল ইমেইলে ফরওয়ার্ড আটকায়',
    static fn () => ExternalForwardService::forward(Ticket::detail($ticketId), $adminId, 'ভুল-ঠিকানা', '<p>x</p>'),
    'সঠিক নয়');
throws('খালি বার্তায় ফরওয়ার্ড আটকায়',
    static fn () => ExternalForwardService::forward(Ticket::detail($ticketId), $adminId, 'a@b.com', '   '),
    'খালি');

echo "\n--- কোলাবোরেটর (CC) ---\n";
$fresh = Ticket::detail($ticketId);
CollaboratorService::add($fresh, 'boss@acme.com', 'বস সাহেব', $adminId);
check('কোলাবোরেটর যুক্ত হয়েছে', count(CollaboratorService::listFor($ticketId)), 1);
check('নতুন গ্রাহক রেকর্ড তৈরি হয়েছে',
    QueryBuilder::table('users')->where('email', 'boss@acme.com')->count(), 1);

throws('একই জনকে দুবার যোগ করা যায় না',
    static fn () => CollaboratorService::add(Ticket::detail($ticketId), 'boss@acme.com', '', $adminId),
    'ইতিমধ্যে');
throws('মূল গ্রাহককে CC করা যায় না',
    static fn () => CollaboratorService::add(Ticket::detail($ticketId), 'rahima@acme.com', '', $adminId),
    'মূল গ্রাহক');

$cc = CollaboratorService::listFor($ticketId)[0];
CollaboratorService::remove(Ticket::detail($ticketId), (int) $cc['id'], $adminId);
check('সরানোর পর তালিকা খালি', CollaboratorService::listFor($ticketId), []);
check('রেকর্ড মুছে যায়নি, নিষ্ক্রিয় হয়েছে',
    QueryBuilder::table('ticket_collaborators')->where('ticket_id', $ticketId)->count(), 1);

CollaboratorService::add(Ticket::detail($ticketId), 'boss@acme.com', '', $adminId);
check('আবার যোগ করলে পুরনো রেকর্ডই সক্রিয় হয়',
    QueryBuilder::table('ticket_collaborators')->where('ticket_id', $ticketId)->count(), 1);

echo "\n--- HTTP স্তর ও অনুমতি ---\n";
Session::invalidate();
Auth::loginAgent($adminId);
Auth::flush();

[$status, $html] = call($router, 'GET', '/agent/tickets/' . $ticketId);
check('টিকেট ভিউ খোলে', $status, 200);
checkTrue('ট্রান্সফার মডাল রেন্ডার হয়েছে', str_contains($html, 'id="transfer-modal"'));
checkTrue('ফরওয়ার্ড মডাল রেন্ডার হয়েছে', str_contains($html, 'id="forward-modal"'));
checkTrue('টাইমলাইনে হস্তান্তর দেখা যাচ্ছে', str_contains($html, 'timeline-item'));
checkTrue('CC প্যানেল আছে', str_contains($html, 'অনুলিপি (CC)'));

$currentDept = (int) Ticket::findById($ticketId)['dept_id'];
$otherDept = $currentDept === $techId ? $salesId : $techId;
[$status] = call($router, 'POST', '/agent/tickets/' . $ticketId . '/transfer', [
    'target' => 'department', 'dept_id' => $otherDept, 'reason' => 'HTTP পরীক্ষা', 'sla_action' => 'keep',
]);
check('HTTP দিয়ে ট্রান্সফার কাজ করে', $status, 302);
check('সত্যিই বদলেছে', (int) Ticket::findById($ticketId)['dept_id'], $otherDept);

// সীমিত অনুমতির এজেন্ট
$limitedRoleId = (int) QueryBuilder::table('roles')->where('name', 'Limited Agent')->value('id');
$limited = makeAgent('সীমিত', 'limited', $limitedRoleId, $otherDept);
QueryBuilder::table('tickets')->where('id', $ticketId)->update(['assigned_agent_id' => $limited]);

Session::invalidate();
Auth::loginAgent($limited);
Auth::flush();

check('টিকেট দেখতে পারেন', call($router, 'GET', '/agent/tickets/' . $ticketId)[0], 200);
check('তবু ট্রান্সফার করতে পারেন না',
    call($router, 'POST', '/agent/tickets/' . $ticketId . '/transfer', [
        'target' => 'department', 'dept_id' => $currentDept,
    ])[0], 403);
check('ডিপার্টমেন্ট বদলায়নি', (int) Ticket::findById($ticketId)['dept_id'], $otherDept);
check('ফরওয়ার্ডও করতে পারেন না',
    call($router, 'POST', '/agent/tickets/' . $ticketId . '/forward', [
        'to_email' => 'x@y.com', 'body' => 'চেষ্টা',
    ])[0], 403);

[$status] = call($router, 'POST', '/agent/tickets/' . $ticketId . '/transfer', ['target' => 'release']);
check('নিজের টিকেট নিজে ছেড়ে দিতে পারেন', $status, 302);
check('সত্যিই ছেড়েছেন', Ticket::findById($ticketId)['assigned_agent_id'], null);

echo "\n=======================================\n";
echo "  পাস: {$pass}   ব্যর্থ: {$fail}\n";
echo "=======================================\n";
exit($fail === 0 ? 0 : 1);

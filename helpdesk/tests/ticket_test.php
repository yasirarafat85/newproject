<?php
declare(strict_types=1);

/**
 * TicketService-এর পূর্ণ লাইফসাইকেল — সত্যিকারের ডেটাবেসের বিরুদ্ধে।
 *
 * MySQL সার্ভার না থাকায় স্কিমাটি SQLite-এ অনুবাদ করে মেমোরিতে চালানো হয়
 * (tests/support/sqlite.php দেখুন)। লজিক একই কোডপথে চলে, তাই স্ট্যাটাস
 * পরিবর্তন, SLA ঘড়ি ও নম্বর তৈরির নিয়মগুলো সত্যিই যাচাই হয়।
 */

require __DIR__ . '/support/bootstrap.php';
require __DIR__ . '/support/sqlite.php';

use App\Core\Config;
use App\Core\Env;
use App\Core\QueryBuilder;
use App\Core\Str;
use App\Models\Ticket;
use App\Services\Installer;
use App\Services\TicketService;


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

function checkTrue(string $label, bool $condition): void
{
    check($label, $condition, true);
}

// ---------- প্রস্তুতি ----------
sqlite_boot(dirname(__DIR__) . '/database/schema.sql');
Installer::seed();

echo "\n--- সিড ডেটা ---\n";
check('৪৫টি পারমিশন', QueryBuilder::table('permissions')->count(), 45);
check('৬টি রোল', QueryBuilder::table('roles')->count(), 6);
check('৭টি স্ট্যাটাস', QueryBuilder::table('statuses')->count(), 7);
check('৫টি প্রায়োরিটি', QueryBuilder::table('priorities')->count(), 5);
check('২টি SLA প্ল্যান', QueryBuilder::table('sla_plans')->count(), 2);
check('ডিফল্ট ডিপার্টমেন্ট আছে', QueryBuilder::table('departments')->where('is_default', 1)->count(), 1);
check('Super Admin-এর সব পারমিশন আছে',
    QueryBuilder::table('role_permissions')
        ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
        ->where('roles.name', 'Super Admin')->count(),
    45);
check('Agent রোলের পারমিশন সীমিত (৮টি)',
    QueryBuilder::table('role_permissions')
        ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
        ->where('roles.name', 'Agent')->count(),
    8);

$agentId = Installer::createSuperAdmin('করিম উদ্দিন', 'karim', 'karim@example.com', 'গোপন-পাসওয়ার্ড-১২৩');
check('সুপার অ্যাডমিন তৈরি', $agentId > 0, true);

$userId = QueryBuilder::table('users')->insert([
    'uuid' => Str::uuid(), 'name' => 'রহিমা বেগম', 'email' => 'rahima@acme.com',
    'status' => 'active', 'created_at' => now(),
]);

// ---------- টিকেট তৈরি ----------
echo "\n--- টিকেট তৈরি ---\n";
$topicId = (int) QueryBuilder::table('help_topics')->where('name', 'কারিগরি সমস্যা')->value('id');

$ticket = TicketService::create([
    'user_id'  => $userId,
    'subject'  => 'প্রিন্টারে কাগজ আটকে যাচ্ছে',
    'body'     => "ট্রে ২ থেকে ছাপতে গেলেই কাগজ আটকে যায়।\n\nগতকাল থেকে হচ্ছে।",
    'topic_id' => $topicId,
    'source'   => 'web',
    'ip'       => '127.0.0.1',
]);

checkTrue('নম্বরের ফরম্যাট YYMMDD-NNNN', (bool) preg_match('/^\d{6}-\d{4}$/', (string) $ticket['number']));
check('আজকের তারিখ দিয়ে শুরু', substr((string) $ticket['number'], 0, 6), date('ymd'));
check('প্রথম টিকেটের ক্রম 0001', substr((string) $ticket['number'], -4), '0001');
check('টপিক থেকে ডিপার্টমেন্ট বসেছে', (int) $ticket['dept_id'] > 0, true);
check('ডিফল্ট স্ট্যাটাস "নতুন"', TicketService::stateOf((int) $ticket['status_id']), 'open');
check('উত্তর বাকি হিসেবে চিহ্নিত', (int) $ticket['is_answered'], 0);
checkTrue('SLA ডেডলাইন বসেছে', $ticket['due_at'] !== null && $ticket['response_due_at'] !== null);
checkTrue('প্রথম উত্তরের সময়সীমা সমাধানের আগে',
    strtotime((string) $ticket['response_due_at']) < strtotime((string) $ticket['due_at']));
check('প্রথম থ্রেড তৈরি হয়েছে', QueryBuilder::table('ticket_threads')->where('ticket_id', (int) $ticket['id'])->count(), 1);
check('থ্রেডের ধরন message',
    QueryBuilder::table('ticket_threads')->where('ticket_id', (int) $ticket['id'])->value('type'), 'message');
checkTrue('প্লেইন টেক্সট HTML হয়েছে',
    str_contains((string) QueryBuilder::table('ticket_threads')->where('ticket_id', (int) $ticket['id'])->value('body_html'), '<p>'));

$second = TicketService::create(['user_id' => $userId, 'subject' => 'দ্বিতীয় টিকেট', 'body' => 'পরীক্ষা']);
check('দ্বিতীয় টিকেটের ক্রম 0002', substr((string) $second['number'], -4), '0002');
check('নম্বর অনন্য', $ticket['number'] === $second['number'], false);

// ---------- এজেন্টের উত্তর ----------
echo "\n--- এজেন্টের উত্তর ---\n";
$fresh = Ticket::findById((int) $ticket['id']);
TicketService::agentReply($fresh, $agentId, '<p>ট্রে ২ খুলে <strong>রোলারটি</strong> পরিষ্কার করুন।</p>');

$after = Ticket::findById((int) $ticket['id']);
check('উত্তর দেওয়া হয়েছে হিসেবে চিহ্নিত', (int) $after['is_answered'], 1);
checkTrue('প্রথম উত্তরের সময় রেকর্ড হয়েছে', $after['first_response_at'] !== null);
check('স্ট্যাটাস → গ্রাহকের অপেক্ষায়', TicketService::stateOf((int) $after['status_id']), 'paused');
checkTrue('SLA ঘড়ি থেমেছে', $after['sla_paused_at'] !== null);
check('থ্রেড সংখ্যা (বার্তা + উত্তর + সিস্টেম)',
    QueryBuilder::table('ticket_threads')->where('ticket_id', (int) $ticket['id'])->count(), 3);

$html = (string) QueryBuilder::table('ticket_threads')
    ->where('ticket_id', (int) $ticket['id'])->where('type', 'response')->value('body_html');
checkTrue('অনুমোদিত ট্যাগ টিকে আছে', str_contains($html, '<strong>'));

// XSS চেষ্টার উত্তর
TicketService::agentReply(Ticket::findById((int) $second['id']), $agentId, '<p>ঠিক আছে</p><script>alert(1)</script>');
$xssHtml = (string) QueryBuilder::table('ticket_threads')
    ->where('ticket_id', (int) $second['id'])->where('type', 'response')->value('body_html');
check('উত্তরে স্ক্রিপ্ট ঢোকে না', str_contains($xssHtml, '<script'), false);

// ---------- গ্রাহকের উত্তর ----------
echo "\n--- গ্রাহকের উত্তর ---\n";
$pausedDueAt = (string) $after['due_at'];
sleep(1);   // ঘড়ি থামার পর কিছু সময় গেছে বোঝাতে

TicketService::clientReply(Ticket::findById((int) $ticket['id']), $userId, 'পরিষ্কার করেছি, তবু হচ্ছে।');
$reopened = Ticket::findById((int) $ticket['id']);

check('স্ট্যাটাস → আবার চলমান', TicketService::stateOf((int) $reopened['status_id']), 'open');
check('উত্তর আবার বাকি', (int) $reopened['is_answered'], 0);
checkTrue('SLA ঘড়ি আবার চালু', $reopened['sla_paused_at'] === null);
checkTrue('থামার সময় হিসেবে জমা হয়েছে', (int) $reopened['sla_paused_seconds'] >= 1);
checkTrue('অপেক্ষার সময় ডেডলাইনে যোগ হয়েছে',
    strtotime((string) $reopened['due_at']) > strtotime($pausedDueAt));

// ---------- দায়িত্ব নেওয়া ----------
echo "\n--- দায়িত্ব নেওয়া ---\n";
TicketService::claim((int) $ticket['id'], $agentId);
$claimed = Ticket::findById((int) $ticket['id']);

check('এজেন্ট অ্যাসাইন হয়েছে', (int) $claimed['assigned_agent_id'], $agentId);
check('হস্তান্তরের ইতিহাস লেখা হয়েছে',
    QueryBuilder::table('ticket_transfers')->where('ticket_id', (int) $ticket['id'])->where('transfer_type', 'claim')->count(), 1);
TicketService::claim((int) $ticket['id'], $agentId);
check('একই এজেন্ট দুবার নিলে ডুপ্লিকেট হয় না',
    QueryBuilder::table('ticket_transfers')->where('ticket_id', (int) $ticket['id'])->count(), 1);

// ---------- ক্লোজ ও reopen ----------
echo "\n--- ক্লোজ ও পুনরায় খোলা ---\n";
$resolvedId = TicketService::statusIdForState('resolved');
TicketService::setStatus((int) $ticket['id'], $resolvedId, $agentId);
$resolved = Ticket::findById((int) $ticket['id']);

check('সমাধান হিসেবে চিহ্নিত', TicketService::stateOf((int) $resolved['status_id']), 'resolved');
checkTrue('ক্লোজের সময় বসেছে', $resolved['closed_at'] !== null);
check('ক্লোজ করেছেন এজেন্ট', (int) $resolved['closed_by'], $agentId);
checkTrue('reopen উইন্ডোর ভেতরে আছে', TicketService::canReopen($resolved));

TicketService::clientReply($resolved, $userId, 'আবার সমস্যা হচ্ছে।');
$again = Ticket::findById((int) $ticket['id']);
check('গ্রাহকের উত্তরে আবার খুলেছে', TicketService::stateOf((int) $again['status_id']), 'open');
check('reopen গণনা বেড়েছে', (int) $again['reopen_count'], 1);
checkTrue('ক্লোজের সময় মুছে গেছে', $again['closed_at'] === null);

// ---------- থ্রেডের দৃশ্যমানতা ----------
echo "\n--- ইন্টার্নাল নোটের দৃশ্যমানতা ---\n";
TicketService::addThread((int) $ticket['id'], [
    'type' => 'note', 'agent_id' => $agentId,
    'body' => 'ভেন্ডরকে জানানো হয়েছে — গ্রাহককে বলা যাবে না।', 'is_html' => false,
]);

$agentThreads  = Ticket::threads((int) $ticket['id'], true);
$clientThreads = Ticket::threads((int) $ticket['id'], false);

checkTrue('এজেন্ট নোট দেখতে পান',
    in_array('note', array_column($agentThreads, 'type'), true));
check('গ্রাহক নোট দেখেন না',
    in_array('note', array_column($clientThreads, 'type'), true), false);
check('গ্রাহক সিস্টেম ইভেন্টও দেখেন না',
    in_array('system', array_column($clientThreads, 'type'), true), false);
checkTrue('গ্রাহক শুধু বার্তা ও উত্তর দেখেন',
    array_diff(array_unique(array_column($clientThreads, 'type')), ['message', 'response']) === []);
check('নোট is_internal হিসেবে সংরক্ষিত',
    (int) QueryBuilder::table('ticket_threads')->where('ticket_id', (int) $ticket['id'])->where('type', 'note')->value('is_internal'), 1);

// ---------- খালি বার্তা ----------
echo "\n--- ইনপুট যাচাই ---\n";
try {
    TicketService::addThread((int) $ticket['id'], ['type' => 'message', 'user_id' => $userId, 'body' => '   ']);
    check('খালি বার্তা প্রত্যাখ্যাত', false, true);
} catch (RuntimeException) {
    check('খালি বার্তা প্রত্যাখ্যাত', true, true);
}

try {
    TicketService::addThread((int) $ticket['id'], ['type' => 'message', 'user_id' => $userId, 'body' => '<script>alert(1)</script>', 'is_html' => true]);
    check('শুধু স্ক্রিপ্টওয়ালা বার্তা প্রত্যাখ্যাত', false, true);
} catch (RuntimeException) {
    check('শুধু স্ক্রিপ্টওয়ালা বার্তা প্রত্যাখ্যাত', true, true);
}

echo "\n=======================================\n";
echo "  পাস: {$pass}   ব্যর্থ: {$fail}\n";
echo "=======================================\n";
exit($fail === 0 ? 0 : 1);

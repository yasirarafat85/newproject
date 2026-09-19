<?php
declare(strict_types=1);

/**
 * রাউট → মিডলওয়্যার → কন্ট্রোলার → ভিউ — পুরো পথ একসাথে।
 *
 * HTTP সার্ভার ছাড়াই Router-কে সরাসরি Request দিয়ে ডাকা হয়, তাই
 * রাউটের ক্রম, CSRF, অনুমতি ও রেন্ডার হওয়া HTML — সবই সত্যিকারভাবে যাচাই হয়।
 */

require __DIR__ . '/support/bootstrap.php';
require __DIR__ . '/support/sqlite.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Str;
use App\Core\View;
use App\Services\Installer;


// CLI-তে সেশন ফাইল-হ্যান্ডলারেই চলে
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

/** একটি রিকোয়েস্ট চালিয়ে [status, body] ফেরত দেয়। */
function call(Router $router, string $method, string $path, array $body = [], array $query = []): array
{
    // স্পষ্টভাবে টোকেন দেওয়া থাকলে সেটিই থাকুক — CSRF ব্যর্থতা পরীক্ষার জন্য
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

/** রিডাইরেক্টের গন্তব্য বের করে (Response-এ হেডার দেখা যায় না, তাই status-ই যথেষ্ট)। */
function statusOf(array $result): int
{
    return $result[0];
}

// ---------- প্রস্তুতি ----------
sqlite_boot(dirname(__DIR__) . '/database/schema.sql');
Installer::seed();
$adminId = Installer::createSuperAdmin('করিম উদ্দিন', 'karim', 'karim@example.com', 'গোপন-পাসওয়ার্ড-১২৩');

$router = new Router();
require dirname(__DIR__) . '/routes/web.php';

echo "\n--- অতিথি হিসেবে ---\n";
Session::invalidate();

[$status, $html] = call($router, 'GET', '/tickets/new');
check('নতুন টিকেটের ফর্ম খোলে', $status, 200);
check('অতিথির জন্য নাম/ইমেইল মাঠ আছে', str_contains($html, 'name="email"'), true);
check('বিষয়ের ধরন তালিকা এসেছে', str_contains($html, 'কারিগরি সমস্যা'), true);

check('CSRF টোকেন ছাড়া POST আটকায়', statusOf(call($router, 'POST', '/tickets', ['_token' => 'ভুল'])), 419);

[$status] = call($router, 'POST', '/tickets', [
    'name' => 'রহিমা বেগম', 'email' => 'rahima@acme.com',
    'subject' => 'প্রিন্টারে কাগজ আটকে যাচ্ছে', 'body' => 'ট্রে ২ থেকে ছাপতে গেলেই আটকে যায়।',
]);
check('অতিথি টিকেট খুলতে পারেন', $status, 302);
check('টিকেট ডেটাবেসে জমা হয়েছে', QueryBuilder::table('tickets')->count(), 1);
check('গ্রাহক অ্যাকাউন্ট তৈরি হয়েছে', QueryBuilder::table('users')->where('email', 'rahima@acme.com')->count(), 1);

$ticket = QueryBuilder::table('tickets')->first();
$ticketId = (int) $ticket['id'];

[$status, $html] = call($router, 'GET', '/tickets/' . $ticket['number']);
check('নিজের খোলা টিকেট দেখতে পান', $status, 200);
check('বিষয় দেখা যাচ্ছে', str_contains($html, 'প্রিন্টারে কাগজ আটকে যাচ্ছে'), true);

check('ফাঁকা ফর্ম প্রত্যাখ্যাত', statusOf(call($router, 'POST', '/tickets', ['subject' => '', 'body' => ''])), 422);

echo "\n--- অন্যের টিকেট ---\n";
Session::invalidate();   // নতুন দর্শক, আগের অনুমতি নেই
check('সেশন ছাড়া অন্যের টিকেট দেখা যায় না',
    statusOf(call($router, 'GET', '/tickets/' . $ticket['number'])), 403);
check('অস্তিত্বহীন টিকেটে 404',
    statusOf(call($router, 'GET', '/tickets/999999-9999')), 404);

check('ভুল ইমেইলে টিকেট খোঁজা ব্যর্থ',
    statusOf(call($router, 'POST', '/tickets/check', ['number' => $ticket['number'], 'email' => 'keu@na.com'])), 302);
check('ব্যর্থ খোঁজার পরও টিকেট দেখা যায় না',
    statusOf(call($router, 'GET', '/tickets/' . $ticket['number'])), 403);

[$status] = call($router, 'POST', '/tickets/check', ['number' => $ticket['number'], 'email' => 'rahima@acme.com']);
check('সঠিক ইমেইলে খোঁজা সফল', $status, 302);
check('এরপর টিকেট দেখা যায়', statusOf(call($router, 'GET', '/tickets/' . $ticket['number'])), 200);

echo "\n--- এজেন্ট প্যানেল ---\n";
Session::invalidate();
check('লগইন ছাড়া এজেন্ট কিউ বন্ধ', statusOf(call($router, 'GET', '/agent/tickets')), 302);

Auth::loginAgent($adminId);
Auth::flush();

[$status, $html] = call($router, 'GET', '/agent/tickets');
check('কিউ খোলে', $status, 200);
check('কিউতে টিকেটটি আছে', str_contains($html, (string) $ticket['number']), true);
check('ফিল্টার ট্যাব রেন্ডার হয়েছে', str_contains($html, 'আনঅ্যাসাইনড'), true);

[$status, $html] = call($router, 'GET', '/agent/tickets/' . $ticketId);
check('টিকেট ভিউ খোলে', $status, 200);
check('গ্রাহকের নাম দেখা যাচ্ছে', str_contains($html, 'রহিমা বেগম'), true);
check('উত্তর লেখার ঘর আছে', str_contains($html, 'id="reply-body"'), true);

[$status] = call($router, 'POST', '/agent/tickets/' . $ticketId . '/reply', [
    'body' => '<p>ট্রে ২ খুলে রোলার পরিষ্কার করুন।</p>',
]);
check('এজেন্ট উত্তর দিতে পারেন', $status, 302);
check('উত্তর থ্রেডে যোগ হয়েছে',
    QueryBuilder::table('ticket_threads')->where('ticket_id', $ticketId)->where('type', 'response')->count(), 1);

[$status] = call($router, 'POST', '/agent/tickets/' . $ticketId . '/note', ['body' => 'ভেন্ডরকে জানানো হয়েছে।']);
check('ইন্টার্নাল নোট যোগ হয়', $status, 302);

[, $html] = call($router, 'GET', '/agent/tickets/' . $ticketId);
check('এজেন্ট নোট দেখতে পান', str_contains($html, 'ভেন্ডরকে জানানো হয়েছে'), true);

Auth::logoutAgent();
Auth::flush();
Session::put('_guest_tickets', [$ticket['number']]);
[, $html] = call($router, 'GET', '/tickets/' . $ticket['number']);
check('গ্রাহক এজেন্টের উত্তর দেখেন', str_contains($html, 'রোলার পরিষ্কার'), true);
check('গ্রাহক ইন্টার্নাল নোট দেখেন না', str_contains($html, 'ভেন্ডরকে জানানো হয়েছে'), false);

echo "\n--- সীমিত অনুমতির এজেন্ট ---\n";
Session::invalidate();
$limitedRoleId = (int) QueryBuilder::table('roles')->where('name', 'Limited Agent')->value('id');
$limitedId = QueryBuilder::table('agents')->insert([
    'uuid' => Str::uuid(), 'name' => 'সীমিত এজেন্ট', 'username' => 'limited',
    'email' => 'limited@example.com', 'password_hash' => 'x', 'role_id' => $limitedRoleId,
    'is_admin' => 0, 'status' => 'active', 'created_at' => now(),
]);

Auth::loginAgent($limitedId);
Auth::flush();

check('নিজের নয় এমন টিকেট দেখা যায় না', statusOf(call($router, 'GET', '/agent/tickets/' . $ticketId)), 403);
// রুটটি এখন আছে (P2), তাই "নেই" নয় — "অনুমতি নেই"
check('অ্যাডমিন প্যানেল বন্ধ', statusOf(call($router, 'GET', '/admin/agents')), 403);

QueryBuilder::table('tickets')->where('id', $ticketId)->update(['assigned_agent_id' => $limitedId]);
check('নিজের অ্যাসাইনড টিকেট দেখা যায়', statusOf(call($router, 'GET', '/agent/tickets/' . $ticketId)), 200);
check('তবু ক্লোজ করার অনুমতি নেই',
    statusOf(call($router, 'POST', '/agent/tickets/' . $ticketId . '/status', [
        'status_id' => App\Services\TicketService::statusIdForState('closed'),
    ])), 403);

echo "\n=======================================\n";
echo "  পাস: {$pass}   ব্যর্থ: {$fail}\n";
echo "=======================================\n";
exit($fail === 0 ? 0 : 1);

<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__));

use App\Core\Config;
use App\Core\Env;
use App\Core\Hash;
use App\Core\QueryBuilder;
use App\Core\Str;
use App\Core\Validator;
use App\Services\Installer;

Env::load(dirname(__DIR__) . '/.env');
Config::setPath(dirname(__DIR__) . '/config');

$pass = 0; $fail = 0;
function check(string $label, mixed $actual, mixed $expected): void {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "  ✓ {$label}\n"; }
    else { $fail++; echo "  ✗ {$label}\n      পেয়েছি   : " . var_export($actual, true) . "\n      চেয়েছিলাম: " . var_export($expected, true) . "\n"; }
}
function checkThrows(string $label, callable $fn): void {
    global $pass, $fail;
    try { $fn(); $fail++; echo "  ✗ {$label} — কোনো exception আসেনি\n"; }
    catch (Throwable) { $pass++; echo "  ✓ {$label}\n"; }
}

echo "\n--- QueryBuilder: SQL তৈরি ---\n";
$q = QueryBuilder::table('tickets')
    ->select('tickets.id', 'users.name AS user_name')
    ->join('users', 'users.id', '=', 'tickets.user_id')
    ->where('tickets.status_id', 3)
    ->whereIn('tickets.dept_id', [1, 2])
    ->whereNull('tickets.deleted_at')
    ->orderBy('tickets.created_at', 'DESC')
    ->limit(25)->offset(50);

check('SELECT + JOIN + WHERE + IN + NULL + ORDER + LIMIT',
    $q->toSql(),
    'SELECT `tickets`.`id`, `users`.`name` AS `user_name` FROM `tickets` INNER JOIN `users` ON `users`.`id` = `tickets`.`user_id`'
    . ' WHERE `tickets`.`status_id` = ? AND `tickets`.`dept_id` IN (?, ?) AND `tickets`.`deleted_at` IS NULL'
    . ' ORDER BY `tickets`.`created_at` DESC LIMIT 25 OFFSET 50');
check('বাইন্ডিং ক্রম ঠিক আছে', $q->bindings(), [3, 1, 2]);

$grouped = QueryBuilder::table('agents')
    ->whereGroup(static function (QueryBuilder $sub): void {
        $sub->where('username', 'karim')->orWhere('email', 'karim@x.com');
    })
    ->whereNull('deleted_at');
check('whereGroup বন্ধনীতে মোড়ে',
    $grouped->toSql(),
    'SELECT * FROM `agents` WHERE (`username` = ? OR `email` = ?) AND `deleted_at` IS NULL');
check('whereGroup-এর বাইন্ডিং', $grouped->bindings(), ['karim', 'karim@x.com']);

check('খালি whereIn কোনো ফল দেয় না',
    QueryBuilder::table('tickets')->whereIn('id', [])->toSql(),
    'SELECT * FROM `tickets` WHERE 1 = 0');

check('two-arg where-এ = ধরা হয়',
    QueryBuilder::table('users')->where('email', 'a@b.c')->toSql(),
    'SELECT * FROM `users` WHERE `email` = ?');

echo "\n--- QueryBuilder: ইনজেকশন প্রতিরোধ ---\n";
checkThrows('কলাম নামে SQL ঢোকানো যায় না', static fn () => QueryBuilder::table('tickets')->orderBy('id; DROP TABLE tickets', 'ASC'));
checkThrows('টেবিল নামে SQL ঢোকানো যায় না', static fn () => QueryBuilder::table('tickets; DROP TABLE users'));
checkThrows('অজানা অপারেটর প্রত্যাখ্যাত', static fn () => QueryBuilder::table('tickets')->where('id', 'UNION', 1));
check('ORDER BY direction হোয়াইটলিস্টেড',
    QueryBuilder::table('t')->orderBy('id', 'DESC; DROP TABLE t')->toSql(),
    'SELECT * FROM `t` ORDER BY `id` ASC');

echo "\n--- Validator ---\n";
$v = Validator::make(
    ['email' => 'not-an-email', 'password' => 'abc', 'password_confirmation' => 'xyz'],
    ['email' => 'required|email', 'password' => 'required|min:8|confirmed'],
    ['email' => 'ইমেইল', 'password' => 'পাসওয়ার্ড']
);
check('ভুল ইনপুট ধরা পড়ে', $v->passes(), false);
check('ইমেইলের বার্তা', $v->errors()['email'], 'ইমেইল একটি সঠিক ইমেইল ঠিকানা হতে হবে।');
check('প্রতি ফিল্ডে একটিই বার্তা', count($v->errors()), 2);

$ok = Validator::make(['email' => 'a@b.com', 'age' => '30'], ['email' => 'required|email', 'age' => 'integer']);
check('সঠিক ইনপুট পাস করে', $ok->passes(), true);

$optional = Validator::make(['phone' => ''], ['phone' => 'phone|max:20']);
check('ফাঁকা optional ফিল্ড বাদ যায়', $optional->passes(), true);

echo "\n--- Hash ও Str ---\n";
$hash = Hash::make('সঠিক-পাসওয়ার্ড-১২৩');
check('সঠিক পাসওয়ার্ড মেলে', Hash::check('সঠিক-পাসওয়ার্ড-১২৩', $hash), true);
check('ভুল পাসওয়ার্ড মেলে না', Hash::check('ভুল', $hash), false);
check('খালি হ্যাশে মেলে না', Hash::check('x', ''), false);
check('UUID ফরম্যাট', (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', Str::uuid()), true);
check('htmlToText ট্যাগ সরায়', Str::htmlToText('<p>প্রথম</p><p>দ্বিতীয়</p>'), "প্রথম\n\nদ্বিতীয়");
check('humanBytes', Str::humanBytes(2_621_440), '2.5 MB');
check('initials', Str::initials('করিম উদ্দিন আহমেদ'), 'কআ');

echo "\n--- schema.sql পার্সিং ---\n";
$method = new ReflectionMethod(Installer::class, 'splitStatements');
$method->setAccessible(true);
$statements = $method->invoke(null, (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
$creates = array_filter($statements, static fn (string $s): bool => str_starts_with($s, 'CREATE TABLE'));
$alters  = array_filter($statements, static fn (string $s): bool => str_starts_with($s, 'ALTER TABLE'));
check('৫১টি CREATE TABLE পাওয়া গেছে', count($creates), 51);
check('৭টি ALTER TABLE পাওয়া গেছে', count($alters), 7);
check('কোনো স্টেটমেন্ট খালি নয়', count(array_filter($statements, static fn (string $s): bool => trim($s) === '')), 0);
check('কমেন্ট লাইন বাদ পড়েছে', count(array_filter($statements, static fn (string $s): bool => str_starts_with($s, '--'))), 0);

echo "\n--- Env পার্সিং ---\n";
check('বুলিয়ান true', Env::get('APP_DEBUG'), true);
check('ইনলাইন কমেন্ট ছাঁটা হয়', Env::get('APP_ENV'), 'local');
check('উদ্ধৃত মান', Env::get('APP_NAME'), 'HelpDesk');
check('অনুপস্থিত কী → default', Env::get('NO_SUCH_KEY', 'ডিফল্ট'), 'ডিফল্ট');

echo "\n--- Config ---\n";
check('ডট নোটেশন', Config::get('app.timezone'), 'Asia/Dhaka');
check('পারমিশন গ্রুপ সংখ্যা', count(Config::get('permissions.groups')), 5);
$total = 0;
foreach (Config::get('permissions.groups') as $g) { $total += count($g['codes']); }
check('মোট পারমিশন কোড', $total, 45);
check('ডিফল্ট রোল সংখ্যা', count(Config::get('permissions.roles')), 6);

echo "\n=======================================\n";
echo "  পাস: {$pass}   ব্যর্থ: {$fail}\n";
echo "=======================================\n";
exit($fail === 0 ? 0 : 1);

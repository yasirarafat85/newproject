<?php
declare(strict_types=1);

/**
 * অ্যাপের সব রুট। bootstrap.php থেকে `$router` ভেরিয়েবল আসে।
 *
 * গ্রুপ চারটি:
 *   /install   ইনস্টলার (ইনস্টলের পর বন্ধ)
 *   /          ক্লায়েন্ট পোর্টাল
 *   /agent     এজেন্ট প্যানেল
 *   /admin     অ্যাডমিন প্যানেল
 */

use App\Controllers\Agent\AuthController as AgentAuthController;
use App\Controllers\Agent\DashboardController;
use App\Controllers\Client\AuthController as ClientAuthController;
use App\Controllers\Client\HomeController;
use App\Controllers\InstallController;
use App\Core\Router;
use App\Middleware\AuthAgent;
use App\Middleware\RequireAdmin;
use App\Middleware\ShareViewData;
use App\Middleware\VerifyCsrf;

/** @var Router $router */

// ---------------------------------------------------------------- ইনস্টলার
$router->group(['prefix' => '/install', 'middleware' => [VerifyCsrf::class, ShareViewData::class]], function (Router $r): void {
    $r->get('', [InstallController::class, 'requirements'])->name('install.requirements');
    $r->get('/database', [InstallController::class, 'databaseForm'])->name('install.database');
    $r->post('/database', [InstallController::class, 'databaseSubmit']);
    $r->get('/admin', [InstallController::class, 'adminForm'])->name('install.admin');
    $r->post('/admin', [InstallController::class, 'adminSubmit']);
});

// ----------------------------------------------------------- ক্লায়েন্ট পোর্টাল
$router->group(['middleware' => [VerifyCsrf::class, ShareViewData::class]], function (Router $r): void {
    $r->get('/', [HomeController::class, 'index'])->name('home');

    $r->get('/login', [ClientAuthController::class, 'showLogin'])->name('client.login');
    $r->post('/login', [ClientAuthController::class, 'login']);
    $r->get('/register', [ClientAuthController::class, 'showRegister'])->name('client.register');
    $r->post('/register', [ClientAuthController::class, 'register']);
    $r->post('/logout', [ClientAuthController::class, 'logout'])->name('client.logout');
});

// ------------------------------------------------------------- এজেন্ট প্যানেল
$router->group(['prefix' => '/agent', 'middleware' => [VerifyCsrf::class, ShareViewData::class]], function (Router $r): void {
    // লগইন পাতা অথেনটিকেশনের বাইরে — নইলে রিডাইরেক্ট লুপ হবে
    $r->get('/login', [AgentAuthController::class, 'showLogin'])->name('agent.login');
    $r->post('/login', [AgentAuthController::class, 'login']);

    $r->group(['middleware' => [AuthAgent::class]], function (Router $r): void {
        $r->post('/logout', [AgentAuthController::class, 'logout'])->name('agent.logout');
        $r->get('', [DashboardController::class, 'index'])->name('agent.dashboard');
    });
});

// ------------------------------------------------------------- অ্যাডমিন প্যানেল
$router->group([
    'prefix'     => '/admin',
    'middleware' => [VerifyCsrf::class, ShareViewData::class, AuthAgent::class, RequireAdmin::class],
], function (Router $r): void {
    // P2-তে পূরণ হবে
});

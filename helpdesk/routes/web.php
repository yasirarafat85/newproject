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
use App\Controllers\Agent\TicketController as AgentTicketController;
use App\Controllers\Admin\ActivityLogController;
use App\Controllers\Admin\AgentController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Admin\DepartmentController;
use App\Controllers\Admin\HelpTopicController;
use App\Controllers\Admin\RoleController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\TeamController;
use App\Controllers\AttachmentController;
use App\Controllers\Client\AuthController as ClientAuthController;
use App\Controllers\Client\HomeController;
use App\Controllers\Client\TicketController as ClientTicketController;
use App\Controllers\InstallController;
use App\Core\Router;
use App\Middleware\AuthAgent;
use App\Middleware\RequireAdmin;
use App\Middleware\AuthClient;
use App\Middleware\ShareViewData;
use App\Middleware\Throttle;
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

    // টিকেট — নির্দিষ্ট পথগুলো {number} এর আগে, নইলে "new" একটি নম্বর হিসেবে ধরা পড়বে
    $r->get('/tickets/new', [ClientTicketController::class, 'create'])->name('client.tickets.create');
    $r->get('/tickets/check', [ClientTicketController::class, 'checkForm'])->name('client.tickets.check');
    $r->post('/tickets/check', [ClientTicketController::class, 'check']);
    $r->get('/tickets/{number}', [ClientTicketController::class, 'show'])->name('client.tickets.show');

    // গেস্টও টিকেট খুলতে পারেন, তাই অপব্যবহার ঠেকাতে থ্রটল
    $r->group(['middleware' => [new Throttle(10, 3600)]], function (Router $r): void {
        $r->post('/tickets', [ClientTicketController::class, 'store'])->name('client.tickets.store');
        $r->post('/tickets/{number}/reply', [ClientTicketController::class, 'reply']);
    });

    // অ্যাটাচমেন্ট — ভেতরেই এজেন্ট/গ্রাহক/গেস্ট অনুমতি যাচাই হয়
    $r->get('/attachments/{uuid}', [AttachmentController::class, 'download'])->name('attachments.download');
});

// লগইন করা গ্রাহকের নিজস্ব তালিকা
$router->group(['middleware' => [VerifyCsrf::class, ShareViewData::class, AuthClient::class]], function (Router $r): void {
    $r->get('/tickets', [ClientTicketController::class, 'index'])->name('client.tickets.index');
});

// ------------------------------------------------------------- এজেন্ট প্যানেল
$router->group(['prefix' => '/agent', 'middleware' => [VerifyCsrf::class, ShareViewData::class]], function (Router $r): void {
    // লগইন পাতা অথেনটিকেশনের বাইরে — নইলে রিডাইরেক্ট লুপ হবে
    $r->get('/login', [AgentAuthController::class, 'showLogin'])->name('agent.login');
    $r->post('/login', [AgentAuthController::class, 'login']);

    $r->group(['middleware' => [AuthAgent::class]], function (Router $r): void {
        $r->post('/logout', [AgentAuthController::class, 'logout'])->name('agent.logout');
        $r->get('', [DashboardController::class, 'index'])->name('agent.dashboard');

        $r->get('/tickets', [AgentTicketController::class, 'index'])->name('agent.tickets');
        $r->get('/tickets/new', [AgentTicketController::class, 'createForm'])->name('agent.tickets.create');
        $r->post('/tickets', [AgentTicketController::class, 'store']);
        $r->get('/tickets/{id}', [AgentTicketController::class, 'show'])->name('agent.tickets.show');
        $r->post('/tickets/{id}/reply', [AgentTicketController::class, 'reply']);
        $r->post('/tickets/{id}/note', [AgentTicketController::class, 'note']);
        $r->post('/tickets/{id}/claim', [AgentTicketController::class, 'claim']);
        $r->post('/tickets/{id}/status', [AgentTicketController::class, 'changeStatus']);
        $r->post('/tickets/{id}/priority', [AgentTicketController::class, 'changePriority']);
    });
});

// ------------------------------------------------------------- অ্যাডমিন প্যানেল
$router->group([
    'prefix'     => '/admin',
    'middleware' => [VerifyCsrf::class, ShareViewData::class, AuthAgent::class, RequireAdmin::class],
], function (Router $r): void {
    $r->get('', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    // প্রতিটি সম্পদে একই প্যাটার্ন: তালিকা · নতুন · সংরক্ষণ · সম্পাদনা · হালনাগাদ · মুছে ফেলা
    // "/new" সবসময় "/{id}" এর আগে, নইলে "new" একটি আইডি হিসেবে ধরা পড়বে
    $resources = [
        'agents'      => AgentController::class,
        'departments' => DepartmentController::class,
        'teams'       => TeamController::class,
        'roles'       => RoleController::class,
        'topics'      => HelpTopicController::class,
    ];

    foreach ($resources as $slug => $controller) {
        $r->get('/' . $slug, [$controller, 'index'])->name('admin.' . $slug);
        $r->get('/' . $slug . '/new', [$controller, 'createForm']);
        $r->post('/' . $slug, [$controller, 'store']);
        $r->get('/' . $slug . '/{id}/edit', [$controller, 'editForm']);
        $r->post('/' . $slug . '/{id}', [$controller, 'update']);
        $r->post('/' . $slug . '/{id}/delete', [$controller, 'destroy']);
    }

    $r->get('/settings', [SettingController::class, 'index'])->name('admin.settings');
    $r->post('/settings', [SettingController::class, 'update']);

    $r->get('/logs', [ActivityLogController::class, 'index'])->name('admin.logs');
});

<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/dashboard', [
            'counts'   => [
                'agents'      => QueryBuilder::table('agents')->whereNull('deleted_at')->where('status', 'active')->count(),
                'departments' => QueryBuilder::table('departments')->where('is_active', 1)->count(),
                'teams'       => QueryBuilder::table('teams')->where('is_active', 1)->count(),
                'roles'       => QueryBuilder::table('roles')->count(),
                'users'       => QueryBuilder::table('users')->whereNull('deleted_at')->count(),
                'tickets'     => QueryBuilder::table('tickets')->whereNull('deleted_at')->count(),
            ],
            'health'   => $this->health(),
            'recent'   => QueryBuilder::table('activity_log')
                ->select('action', 'description', 'actor_type', 'created_at')
                ->orderBy('created_at', 'DESC')
                ->limit(8)
                ->get(),
            'byDepartment' => $this->ticketsByDepartment(),
        ], 'layouts/agent');
    }

    /** সেটআপে যেসব জিনিস বাকি থাকলে সিস্টেম পুরোপুরি কাজ করে না। */
    private function health(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'ডিবাগ মোড বন্ধ',
            'ok'    => !config('app.debug', false),
            'hint'  => 'প্রোডাকশনে .env-এ APP_DEBUG=false রাখুন',
        ];

        $checks[] = [
            'label' => 'APP_KEY সেট করা আছে',
            'ok'    => strlen((string) config('app.key', '')) >= 32,
            'hint'  => 'ক্রেডেনশিয়াল এনক্রিপশনের জন্য দরকার',
        ];

        $checks[] = [
            'label' => 'storage/ ফোল্ডারে লেখা যায়',
            'ok'    => is_writable(storage_path()),
            'hint'  => 'অ্যাটাচমেন্ট ও লগের জন্য দরকার',
        ];

        $checks[] = [
            'label' => 'ইনস্টলার বন্ধ',
            'ok'    => (bool) config('app.installed', false),
            'hint'  => '.env-এ APP_INSTALLED=true থাকা দরকার',
        ];

        $emailAccounts = QueryBuilder::table('email_accounts')->where('fetch_enabled', 1)->count();
        $checks[] = [
            'label' => 'ইমেইল অ্যাকাউন্ট যুক্ত',
            'ok'    => $emailAccounts > 0,
            'hint'  => 'ইমেইল থেকে টিকেট আনতে P4-এ যুক্ত হবে',
        ];

        $cron = QueryBuilder::table('cron_locks')->whereNotNull('last_run_at')->count();
        $checks[] = [
            'label' => 'ক্রন চলছে',
            'ok'    => $cron > 0,
            'hint'  => 'SLA ও ইমেইলের জন্য cron/run.php প্রতি মিনিটে চালান',
        ];

        return $checks;
    }

    private function ticketsByDepartment(): array
    {
        return Database::select(
            'SELECT d.name, COUNT(t.id) AS total
             FROM departments d
             LEFT JOIN tickets t ON t.dept_id = d.id AND t.deleted_at IS NULL
             WHERE d.is_active = 1
             GROUP BY d.id, d.name
             ORDER BY total DESC
             LIMIT 8'
        );
    }
}

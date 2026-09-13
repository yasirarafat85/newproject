<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Services\Installer;
use Throwable;

/**
 * ব্রাউজার-ভিত্তিক ইনস্টলার — তিন ধাপ:
 *   1. সার্ভার প্রস্তুতি   2. ডেটাবেস তথ্য   3. অ্যাডমিন অ্যাকাউন্ট
 *
 * ইনস্টল হয়ে গেলে (.env-এ APP_INSTALLED=true) সব রুট বন্ধ হয়ে যায়।
 */
final class InstallController extends Controller
{
    private function guard(): void
    {
        if (Config::get('app.installed', false)) {
            throw new HttpException(404, 'ইনস্টলেশন ইতিমধ্যে সম্পন্ন হয়েছে।');
        }
    }

    public function requirements(Request $request): Response
    {
        $this->guard();

        $checks = Installer::requirements();
        $allOk = !in_array(false, array_column($checks, 'ok'), true);

        return $this->view('install/requirements', [
            'checks' => $checks,
            'allOk'  => $allOk,
        ], 'layouts/bare');
    }

    public function databaseForm(Request $request): Response
    {
        $this->guard();

        return $this->view('install/database', [
            'defaults' => Config::get('database'),
        ], 'layouts/bare');
    }

    public function databaseSubmit(Request $request): Response
    {
        $this->guard();

        Validator::make($request->all(), [
            'db_host'     => 'required|max:160',
            'db_port'     => 'required|integer',
            'db_database' => 'required|max:60',
            'db_username' => 'required|max:60',
        ], [
            'db_host'     => 'ডেটাবেস হোস্ট',
            'db_port'     => 'পোর্ট',
            'db_database' => 'ডেটাবেসের নাম',
            'db_username' => 'ইউজারনেম',
        ])->validate();

        $config = [
            'host'     => (string) $request->input('db_host'),
            'port'     => (string) $request->input('db_port'),
            'database' => (string) $request->input('db_database'),
            'username' => (string) $request->input('db_username'),
            'password' => (string) $request->raw('db_password', ''),
            'charset'  => 'utf8mb4',
        ];

        try {
            Installer::prepareDatabase($config);
            Config::set('database', $config);
            Installer::runSchema(base_path('database/schema.sql'));
            Installer::seed();
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());

            return $this->redirect('/install/database');
        }

        // পরের ধাপে আবার সংযোগ করতে হবে, তাই সেশনে রাখি (পাসওয়ার্ড .env-এ গেলে মুছে যাবে)
        \App\Core\Session::put('_install_db', $config);
        flash('success', 'ডেটাবেস ও ডিফল্ট ডেটা তৈরি হয়েছে।');

        return $this->redirect('/install/admin');
    }

    public function adminForm(Request $request): Response
    {
        $this->guard();

        if (!\App\Core\Session::has('_install_db')) {
            return $this->redirect('/install/database');
        }

        return $this->view('install/admin', [], 'layouts/bare');
    }

    public function adminSubmit(Request $request): Response
    {
        $this->guard();

        $config = \App\Core\Session::get('_install_db');
        if (!is_array($config)) {
            return $this->redirect('/install/database');
        }

        Validator::make($request->all(), [
            'name'         => 'required|max:120',
            'username'     => 'required|min:3|max:60',
            'email'        => 'required|email|max:190',
            'password'     => 'required|min:8|max:255|confirmed',
            'company_name' => 'required|max:160',
        ], [
            'name'         => 'পূর্ণ নাম',
            'username'     => 'ইউজারনেম',
            'email'        => 'ইমেইল',
            'password'     => 'পাসওয়ার্ড',
            'company_name' => 'প্রতিষ্ঠানের নাম',
        ])->validate();

        Config::set('database', $config);
        Installer::prepareDatabase($config);

        try {
            $agentId = Installer::createSuperAdmin(
                (string) $request->input('name'),
                (string) $request->input('username'),
                (string) $request->input('email'),
                (string) $request->raw('password')
            );
        } catch (Throwable $e) {
            flash('danger', 'অ্যাডমিন তৈরি করা যায়নি: ' . $e->getMessage());

            return $this->redirect('/install/admin');
        }

        \App\Core\QueryBuilder::table('settings')
            ->where('setting_key', 'company_name')
            ->update(['setting_value' => (string) $request->input('company_name'), 'updated_at' => now()]);

        \App\Core\QueryBuilder::table('settings')
            ->where('setting_key', 'support_email')
            ->update(['setting_value' => (string) $request->input('email'), 'updated_at' => now()]);

        Installer::writeEnv(base_path('.env'), [
            'APP_NAME'      => (string) $request->input('company_name'),
            'APP_URL'       => rtrim((string) ($request->input('app_url') ?: config('app.url')), '/'),
            'APP_KEY'       => Str::random(32),
            'APP_INSTALLED' => 'true',
            'APP_DEBUG'     => 'false',
            'DB_HOST'       => $config['host'],
            'DB_PORT'       => $config['port'],
            'DB_DATABASE'   => $config['database'],
            'DB_USERNAME'   => $config['username'],
            'DB_PASSWORD'   => $config['password'],
        ]);

        \App\Core\Session::forget('_install_db');
        \App\Core\Auth::loginAgent($agentId);

        return $this->view('install/done', [
            'email' => (string) $request->input('email'),
        ], 'layouts/bare');
    }
}

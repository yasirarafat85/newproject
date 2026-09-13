<?php
declare(strict_types=1);

namespace App\Controllers\Agent;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Hash;
use App\Core\QueryBuilder;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuditService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::isAgent()) {
            return $this->redirect('/agent');
        }

        return $this->view('agent/auth/login', [], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        Validator::make($request->all(), [
            'login'    => 'required|max:190',
            'password' => 'required|max:255',
        ], [
            'login'    => 'ইউজারনেম বা ইমেইল',
            'password' => 'পাসওয়ার্ড',
        ])->validate();

        $login = (string) $request->input('login');
        $password = (string) $request->raw('password');
        $ip = $request->ip();

        if (RateLimiter::tooManyAttempts($login, $ip)) {
            $minutes = RateLimiter::availableIn($login);
            flash('danger', "অনেকবার ভুল চেষ্টা হয়েছে। {$minutes} মিনিট পর আবার চেষ্টা করুন।");

            return $this->redirect('/agent/login');
        }

        $agent = QueryBuilder::table('agents')
            ->whereGroup(static function ($query) use ($login): void {
                $query->where('username', $login)->orWhere('email', $login);
            })
            ->whereNull('deleted_at')
            ->first();

        // অ্যাকাউন্ট না থাকলেও হ্যাশ যাচাইয়ের সমান সময় নিই, যাতে
        // সাড়া দেওয়ার সময় দেখে ইউজারনেম আছে কি না বোঝা না যায়
        $hash = $agent['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';

        if ($agent === null || !Hash::check($password, $hash)) {
            RateLimiter::record($login, $ip, false);
            AuditService::log('agent.login_failed', 'agent', $agent === null ? null : (int) $agent['id'], $login);
            flash('danger', 'ইউজারনেম বা পাসওয়ার্ড ঠিক নয়।');

            return $this->redirect('/agent/login');
        }

        if ($agent['status'] !== 'active') {
            RateLimiter::record($login, $ip, false);
            flash('danger', 'আপনার অ্যাকাউন্টটি বর্তমানে নিষ্ক্রিয়। অ্যাডমিনের সঙ্গে যোগাযোগ করুন।');

            return $this->redirect('/agent/login');
        }

        // অ্যালগরিদম/কস্ট বদলে থাকলে চুপচাপ নতুন হ্যাশে সরাই
        if (Hash::needsRehash($agent['password_hash'])) {
            QueryBuilder::table('agents')->where('id', (int) $agent['id'])
                ->update(['password_hash' => Hash::make($password)]);
        }

        RateLimiter::record($login, $ip, true);
        Auth::loginAgent((int) $agent['id']);

        QueryBuilder::table('agents')->where('id', (int) $agent['id'])
            ->update(['last_login_at' => now()]);

        AuditService::log('agent.login', 'agent', (int) $agent['id'], $agent['name']);

        $intended = Session::get('_intended');
        Session::forget('_intended');

        return $this->redirect(is_string($intended) && str_starts_with($intended, '/agent') ? $intended : '/agent');
    }

    public function logout(Request $request): Response
    {
        AuditService::log('agent.logout', 'agent', Auth::agentId());
        Auth::logoutAgent();
        flash('success', 'আপনি লগআউট হয়েছেন।');

        return $this->redirect('/agent/login');
    }
}

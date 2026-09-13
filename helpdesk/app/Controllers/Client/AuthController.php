<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Hash;
use App\Core\QueryBuilder;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Str;
use App\Core\Validator;
use App\Services\AuditService;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if (Auth::isClient()) {
            return $this->redirect('/tickets');
        }

        return $this->view('client/auth/login', [], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        Validator::make($request->all(), [
            'email'    => 'required|email|max:190',
            'password' => 'required|max:255',
        ], ['email' => 'ইমেইল', 'password' => 'পাসওয়ার্ড'])->validate();

        $email = mb_strtolower((string) $request->input('email'));
        $password = (string) $request->raw('password');

        if (RateLimiter::tooManyAttempts($email, $request->ip())) {
            flash('danger', 'অনেকবার ভুল চেষ্টা হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।');

            return $this->redirect('/login');
        }

        $user = QueryBuilder::table('users')->where('email', $email)->whereNull('deleted_at')->first();
        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';

        if ($user === null || $user['password_hash'] === null || !Hash::check($password, (string) $hash)) {
            RateLimiter::record($email, $request->ip(), false);
            flash('danger', 'ইমেইল বা পাসওয়ার্ড ঠিক নয়।');

            return $this->redirect('/login');
        }

        if ($user['status'] !== 'active') {
            flash('danger', 'আপনার অ্যাকাউন্টটি বর্তমানে ব্যবহারযোগ্য নয়।');

            return $this->redirect('/login');
        }

        RateLimiter::record($email, $request->ip(), true);
        Auth::loginClient((int) $user['id']);
        QueryBuilder::table('users')->where('id', (int) $user['id'])->update(['last_login_at' => now()]);
        AuditService::log('user.login', 'user', (int) $user['id'], $user['name']);

        $intended = Session::get('_intended');
        Session::forget('_intended');

        return $this->redirect(is_string($intended) && !str_starts_with($intended, '/agent') ? $intended : '/tickets');
    }

    public function showRegister(Request $request): Response
    {
        if (!setting('allow_registration', '1')) {
            flash('warning', 'নতুন নিবন্ধন বর্তমানে বন্ধ আছে।');

            return $this->redirect('/login');
        }

        return $this->view('client/auth/register', [], 'layouts/auth');
    }

    public function register(Request $request): Response
    {
        if (!setting('allow_registration', '1')) {
            return $this->redirect('/login');
        }

        Validator::make($request->all(), [
            'name'     => 'required|max:120',
            'email'    => 'required|email|max:190',
            'phone'    => 'phone|max:30',
            'password' => 'required|min:8|max:255|confirmed',
        ], [
            'name'     => 'নাম',
            'email'    => 'ইমেইল',
            'phone'    => 'ফোন নম্বর',
            'password' => 'পাসওয়ার্ড',
        ])->validate();

        $email = mb_strtolower((string) $request->input('email'));
        $existing = QueryBuilder::table('users')->where('email', $email)->first();

        if ($existing !== null && $existing['password_hash'] !== null) {
            flash('danger', 'এই ইমেইলে ইতিমধ্যে একটি অ্যাকাউন্ট আছে। লগইন করুন।');

            return $this->redirect('/login');
        }

        if ($existing !== null) {
            // আগে গেস্ট হিসেবে টিকেট করেছিলেন — সেই রেকর্ডেই পাসওয়ার্ড বসাই,
            // তাই পুরনো টিকেটগুলো নতুন অ্যাকাউন্টেই থেকে যায়
            QueryBuilder::table('users')->where('id', (int) $existing['id'])->update([
                'name'          => (string) $request->input('name'),
                'phone'         => (string) $request->input('phone') ?: null,
                'password_hash' => Hash::make((string) $request->raw('password')),
                'updated_at'    => now(),
            ]);
            $userId = (int) $existing['id'];
        } else {
            $userId = QueryBuilder::table('users')->insert([
                'uuid'          => Str::uuid(),
                'org_id'        => self::matchOrganisation($email),
                'name'          => (string) $request->input('name'),
                'email'         => $email,
                'phone'         => (string) $request->input('phone') ?: null,
                'password_hash' => Hash::make((string) $request->raw('password')),
                'status'        => 'active',
                'created_at'    => now(),
            ]);
        }

        Auth::loginClient($userId);
        AuditService::log('user.register', 'user', $userId, $email);
        flash('success', 'স্বাগতম! আপনার অ্যাকাউন্ট তৈরি হয়েছে।');

        return $this->redirect('/tickets');
    }

    public function logout(Request $request): Response
    {
        Auth::logoutClient();
        flash('success', 'আপনি লগআউট হয়েছেন।');

        return $this->redirect('/');
    }

    /** ইমেইল ডোমেইন দেখে প্রতিষ্ঠান স্বয়ংক্রিয়ভাবে যুক্ত করে। */
    public static function matchOrganisation(string $email): ?int
    {
        $domain = substr(strrchr($email, '@') ?: '', 1);
        if ($domain === '') {
            return null;
        }

        $org = QueryBuilder::table('organizations')->where('domain', $domain)->first();

        return $org === null ? null : (int) $org['id'];
    }
}

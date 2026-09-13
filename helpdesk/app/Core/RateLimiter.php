<?php
declare(strict_types=1);

namespace App\Core;

/**
 * login_attempts টেবিলভিত্তিক থ্রটল।
 * IP এবং অ্যাকাউন্ট — দুটোই আলাদাভাবে গোনা হয়, তাই একটি IP থেকে
 * বহু অ্যাকাউন্টে চেষ্টা কিংবা বহু IP থেকে একটি অ্যাকাউন্টে চেষ্টা, দুটোই আটকায়।
 */
final class RateLimiter
{
    public static function tooManyAttempts(string $identifier, string $ip): bool
    {
        $max = (int) Config::get('app.login_max_attempts', 5);
        $window = (int) Config::get('app.login_lockout_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - ($window * 60));

        $byIdentifier = QueryBuilder::table('login_attempts')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('created_at', '>=', $since)
            ->count();

        if ($byIdentifier >= $max) {
            return true;
        }

        // এক IP থেকে অনেক অ্যাকাউন্টে চেষ্টা — সীমা কিছুটা শিথিল
        $byIp = QueryBuilder::table('login_attempts')
            ->where('ip_address', $ip)
            ->where('success', 0)
            ->where('created_at', '>=', $since)
            ->count();

        return $byIp >= ($max * 4);
    }

    public static function record(string $identifier, string $ip, bool $success): void
    {
        QueryBuilder::table('login_attempts')->insert([
            'identifier' => mb_substr($identifier, 0, 190),
            'ip_address' => $ip,
            'success'    => $success ? 1 : 0,
            'created_at' => now(),
        ]);

        if ($success) {
            QueryBuilder::table('login_attempts')
                ->where('identifier', $identifier)
                ->where('success', 0)
                ->delete();
        }
    }

    public static function availableIn(string $identifier): int
    {
        $window = (int) Config::get('app.login_lockout_minutes', 15);
        $last = QueryBuilder::table('login_attempts')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->orderBy('created_at', 'DESC')
            ->value('created_at');

        if ($last === null) {
            return 0;
        }

        $elapsed = time() - strtotime((string) $last);

        return max(0, (int) ceil((($window * 60) - $elapsed) / 60));
    }

    /** সাধারণ কাজের থ্রটল (গেস্ট টিকেট তৈরি, API) — সেশন ভিত্তিক, হালকা। */
    public static function hit(string $key, int $max, int $decaySeconds): bool
    {
        $bucket = Session::get('_throttle', []);
        $now = time();

        $entry = $bucket[$key] ?? ['count' => 0, 'reset' => $now + $decaySeconds];
        if ($entry['reset'] <= $now) {
            $entry = ['count' => 0, 'reset' => $now + $decaySeconds];
        }

        $entry['count']++;
        $bucket[$key] = $entry;
        Session::put('_throttle', $bucket);

        return $entry['count'] > $max;
    }
}

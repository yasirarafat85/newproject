<?php
declare(strict_types=1);

namespace App\Core;

final class Hash
{
    public static function make(string $password): string
    {
        // Argon2id পছন্দ; বিল্ডে না থাকলে bcrypt-এ নেমে আসি
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 2,
            ]);
        }

        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function check(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    /** অ্যালগরিদম/কস্ট বদলালে লগইনের সময় চুপচাপ রিহ্যাশ করা যায়। */
    public static function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 2,
            ]);
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

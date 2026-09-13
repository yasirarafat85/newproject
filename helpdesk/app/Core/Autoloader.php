<?php
declare(strict_types=1);

/**
 * ন্যূনতম PSR-4 অটোলোডার।
 *
 * Composer ইনস্টল করা থাকলে সেটিই ব্যবহার হয় (তখন vendor প্যাকেজও পাওয়া যায়)।
 * না থাকলে এই fallback দিয়ে অ্যাপের নিজের কোড চলে — তাই `composer install`
 * ছাড়াই XAMPP-এ প্রজেক্ট বুট করা যায়, শুধু ইমেইল ফিচারগুলো তখন নিষ্ক্রিয় থাকে।
 */
final class Autoloader
{
    public static function register(string $basePath): void
    {
        $vendor = $basePath . '/vendor/autoload.php';
        if (is_file($vendor)) {
            require_once $vendor;
            return;
        }

        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $basePath . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });

        require_once $basePath . '/app/Helpers/functions.php';
    }

    public static function hasComposer(string $basePath): bool
    {
        return is_file($basePath . '/vendor/autoload.php');
    }
}

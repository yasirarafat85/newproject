<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

if (!function_exists('e')) {
    /** টেমপ্লেটে আউটপুট করার একমাত্র নিরাপদ উপায়। */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** অ্যাপের বেস URL ধরে অভ্যন্তরীণ লিংক তৈরি করে। */
    function url(string $path = ''): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $path = '/' . ltrim($path, '/');

        return $base . ($path === '/' ? '/' : rtrim($path, '/'));
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], ?string $layout = null): Response
    {
        return Response::make(View::render($template, $data, $layout));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(str_starts_with($path, 'http') ? $path : url($path), $status);
    }
}

if (!function_exists('back')) {
    /**
     * আগের পাতায় ফেরত।
     *
     * Referer হেডারের উপর ভরসা করা যায় না (ব্রাউজার ছাঁটতে পারে, কিছু
     * ক্লায়েন্ট পাঠায়ই না), তাই ShareViewData প্রতিটি GET রিকোয়েস্টে
     * পাথটি সেশনে রেখে দেয় — সেটিই প্রথম পছন্দ।
     */
    function back(string $fallback = '/'): Response
    {
        $previous = Session::get('_previous');
        if (is_string($previous) && $previous !== '') {
            return redirect($previous);
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // খোলা রিডাইরেক্ট ঠেকাতে শুধু নিজের হোস্টেই ফেরত যাই
        if ($referer !== '' && $host !== '' && str_contains($referer, $host)) {
            return Response::redirect($referer);
        }

        return redirect($fallback);
    }
}

if (!function_exists('old')) {
    /** ভ্যালিডেশন ব্যর্থ হলে ফর্ম আবার পূরণ করতে। */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::getFlash('_old', []);

        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return Session::getFlash('_errors', []);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): ?string
    {
        return errors()[$field] ?? null;
    }
}

if (!function_exists('title')) {
    /** ভিউ ফাইলের ভেতর থেকে পাতার শিরোনাম নির্ধারণ করে। */
    function title(string $title): void
    {
        View::setTitle($title);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    /** settings টেবিল থেকে রানটাইম কনফিগ (প্রতি রিকোয়েস্টে একবারই লোড হয়)। */
    function setting(string $key, mixed $default = null): mixed
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            try {
                foreach (App\Core\QueryBuilder::table('settings')->get() as $row) {
                    $cache[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Throwable) {
                $cache = []; // ইনস্টলের আগে টেবিলই থাকে না
            }
        }

        return $cache[$key] ?? $default;
    }
}

if (!function_exists('__')) {
    /** অনুবাদ — lang/{locale}.php থেকে; কী না থাকলে কী-ই ফেরত যায়। */
    function __(string $key, array $replace = []): string
    {
        static $lines = null;

        if ($lines === null) {
            $locale = (string) Config::get('app.locale', 'bn');
            $file = dirname(__DIR__, 2) . '/lang/' . $locale . '.php';
            $lines = is_file($file) ? require $file : [];
        }

        $line = $lines[$key] ?? $key;
        foreach ($replace as $search => $value) {
            $line = str_replace(':' . $search, (string) $value, $line);
        }

        return $line;
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $datetime, string $format = 'd M Y, h:i A'): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '—';
        }

        return date($format, strtotime($datetime));
    }
}

if (!function_exists('time_ago')) {
    /** "১৫ মিনিট আগে" ধাঁচের আপেক্ষিক সময়। */
    function time_ago(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }

        $seconds = time() - strtotime($datetime);
        $future = $seconds < 0;
        $seconds = abs($seconds);

        $value = match (true) {
            $seconds < 60      => 'এইমাত্র',
            $seconds < 3600    => intdiv($seconds, 60) . ' মিনিট',
            $seconds < 86400   => intdiv($seconds, 3600) . ' ঘণ্টা',
            $seconds < 2592000 => intdiv($seconds, 86400) . ' দিন',
            $seconds < 31536000 => intdiv($seconds, 2592000) . ' মাস',
            default            => intdiv($seconds, 31536000) . ' বছর',
        };

        if ($value === 'এইমাত্র') {
            return $value;
        }

        return $future ? $value . ' বাকি' : $value . ' আগে';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash('_notice', ['type' => $type, 'message' => $message]);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . '/storage' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}

<?php
declare(strict_types=1);

use App\Core\Env;

return [
    'name'      => Env::get('APP_NAME', 'HelpDesk'),
    'env'       => Env::get('APP_ENV', 'production'),
    'debug'     => (bool) Env::get('APP_DEBUG', false),
    'url'       => rtrim((string) Env::get('APP_URL', 'http://localhost'), '/'),
    'locale'    => Env::get('APP_LOCALE', 'bn'),
    'timezone'  => Env::get('APP_TIMEZONE', 'Asia/Dhaka'),
    'installed' => (bool) Env::get('APP_INSTALLED', false),
    'key'       => Env::get('APP_KEY', ''),

    'session_lifetime' => (int) Env::get('SESSION_LIFETIME', 120),
    'session_secure'   => (bool) Env::get('SESSION_SECURE', false),

    'login_max_attempts'    => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),

    'upload_max_mb'   => (int) Env::get('UPLOAD_MAX_MB', 25),
    'upload_allowed'  => array_map('trim', explode(',', (string) Env::get('UPLOAD_ALLOWED', 'jpg,jpeg,png,pdf,txt,zip'))),

    'per_page' => 25,

    // টিকেট নম্বরের ফরম্যাট: YYMMDD-NNNN (দিনে ৯৯৯৯টি পর্যন্ত)
    'ticket_number_format' => 'ymd',

    'reopen_window_days'  => 7,   // ক্লোজ হওয়ার কত দিন পর্যন্ত reopen চলবে
    'autoclose_days'      => 3,   // Resolved → Closed কত দিন পর
];

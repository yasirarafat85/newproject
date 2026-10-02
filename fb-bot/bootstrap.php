<?php

declare(strict_types=1);

// Shared startup for web entry points, the worker and CLI scripts.

define('BASE_PATH', __DIR__);

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit("PHP 8.1 or newer is required (8.2+ recommended). Current: " . PHP_VERSION . "\n");
}

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = BASE_PATH . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

App\Env::load(getenv('FBBOT_ENV_FILE') ?: BASE_PATH . '/.env');

date_default_timezone_set('UTC');
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    App\Logger::error('app', 'Uncaught exception: ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . App\Logger::scrubString($e->getMessage()) . "\n");
        exit(1);
    }
    http_response_code(500);
    echo 'Internal error. Check the logs.';
});

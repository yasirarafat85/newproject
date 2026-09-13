<?php
declare(strict_types=1);

/**
 * অ্যাপ বুটস্ট্র্যাপ — ওয়েব রিকোয়েস্ট ও ক্রন, দুই জায়গা থেকেই ব্যবহৃত হয়।
 * একটি প্রস্তুত Router ফেরত দেয়।
 */

use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Router;
use App\Core\View;

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/app/Core/Autoloader.php';
Autoloader::register(BASE_PATH);

Env::load(BASE_PATH . '/.env');
Config::setPath(BASE_PATH . '/config');
View::setPath(BASE_PATH . '/app/Views');
Logger::setPath(BASE_PATH . '/storage/logs');

date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));
mb_internal_encoding('UTF-8');

// ডিবাগ বন্ধ থাকলে ব্রাউজারে কোনো ভুল ছাপা হবে না — সব লগে যাবে
$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

/** @var Router $router */
$router = new Router();
require BASE_PATH . '/routes/web.php';

return $router;

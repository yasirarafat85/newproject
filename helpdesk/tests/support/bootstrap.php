<?php
declare(strict_types=1);

/**
 * টেস্টের সাধারণ প্রস্তুতি।
 *
 * .env ফাইল রিপোতে থাকে না (গোপন তথ্য থাকে), তাই না পেলে .env.example
 * থেকেই কনফিগ নেওয়া হয় — টেস্ট চালাতে কোনো সেটআপ লাগে না।
 */

require_once __DIR__ . '/../../app/Core/Autoloader.php';

Autoloader::register(dirname(__DIR__, 2));

use App\Core\Config;
use App\Core\Env;
use App\Core\View;

$basePath = dirname(__DIR__, 2);

Env::load(is_file($basePath . '/.env') ? $basePath . '/.env' : $basePath . '/.env.example');
Config::setPath($basePath . '/config');
View::setPath($basePath . '/app/Views');

date_default_timezone_set('Asia/Dhaka');
mb_internal_encoding('UTF-8');

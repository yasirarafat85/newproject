<?php

declare(strict_types=1);

/*
 * One-time setup (safe to run again):
 *   php bin/setup.php
 * Creates .env from .env.example if missing, creates the database and
 * applies migrations, then prints the tables.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the terminal.\n");
}

$base = dirname(__DIR__);
if (!is_file($base . '/.env') && !getenv('FBBOT_ENV_FILE')) {
    copy($base . '/.env.example', $base . '/.env');
    @chmod($base . '/.env', 0600);
    echo "Created .env from .env.example. Edit it before going live.\n";
}

require $base . '/bootstrap.php';

use App\Database;
use App\Env;
use App\EnvFile;
use App\Migrator;
use App\Settings;

foreach (['storage', 'storage/logs', 'public/media'] as $dir) {
    if (!is_dir(BASE_PATH . "/$dir")) {
        mkdir(BASE_PATH . "/$dir", 0775, true);
    }
}

if (!Env::has('WEBHOOK_VERIFY_TOKEN')) {
    EnvFile::set('WEBHOOK_VERIFY_TOKEN', bin2hex(random_bytes(16)));
    echo "Generated WEBHOOK_VERIFY_TOKEN in .env.\n";
}

$pdo = Database::pdo(); // connecting applies pending migrations
@chmod(Database::path(), 0600);

if (Settings::get('bot_enabled') === null) {
    Settings::set('bot_enabled', '1');
}

$version = $pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn();
echo "Database: " . Database::path() . "\n";
echo "Schema version: $version\n";
echo "Tables:\n";
foreach (Migrator::tables($pdo) as $table) {
    echo "  - $table\n";
}
echo "Setup OK.\n";

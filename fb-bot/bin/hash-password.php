<?php

declare(strict_types=1);

/*
 * Sets the admin panel password:
 *   php bin/hash-password.php
 * Asks for the password twice (hidden) and writes the bcrypt hash into .env.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run this from the terminal.\n");
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Cli;
use App\EnvFile;

$password = Cli::askHidden('New admin password (min 10 characters): ');
if (mb_strlen($password) < 10) {
    exit("Too short. Use at least 10 characters.\n");
}
if (Cli::askHidden('Repeat password: ') !== $password) {
    exit("Passwords do not match.\n");
}

EnvFile::set('ADMIN_PASSWORD_HASH', password_hash($password, PASSWORD_DEFAULT));
echo "Password saved to .env. You can now log in to the admin panel.\n";

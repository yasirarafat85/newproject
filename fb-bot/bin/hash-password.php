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

function ask(string $prompt): string
{
    echo $prompt;
    $hidden = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($hidden) {
        shell_exec('stty -echo');
    }
    $value = rtrim((string) fgets(STDIN), "\r\n");
    if ($hidden) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $value;
}

$password = ask('New admin password (min 10 characters): ');
if (mb_strlen($password) < 10) {
    exit("Too short. Use at least 10 characters.\n");
}
if (ask('Repeat password: ') !== $password) {
    exit("Passwords do not match.\n");
}

$line = "ADMIN_PASSWORD_HASH='" . password_hash($password, PASSWORD_DEFAULT) . "'";
$envFile = dirname(__DIR__) . '/.env';

if (!is_file($envFile)) {
    echo ".env not found. Run php bin/setup.php first, or add this line to .env yourself:\n$line\n";
    exit(1);
}

$env = (string) file_get_contents($envFile);
$env = preg_match('/^ADMIN_PASSWORD_HASH=.*$/m', $env)
    ? preg_replace_callback('/^ADMIN_PASSWORD_HASH=.*$/m', static fn () => $line, $env)
    : rtrim($env) . "\n$line\n";
file_put_contents($envFile, $env);

echo "Password saved to .env. You can now log in to the admin panel.\n";

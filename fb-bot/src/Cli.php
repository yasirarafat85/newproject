<?php

declare(strict_types=1);

namespace App;

/** Terminal input helpers for the bin/ scripts. */
final class Cli
{
    public static function ask(string $prompt): string
    {
        echo $prompt;
        return trim((string) fgets(STDIN));
    }

    /** Reads a line without echoing it (passwords, tokens). */
    public static function askHidden(string $prompt): string
    {
        echo $prompt;
        $hidden = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
        if ($hidden) {
            shell_exec('stty -echo');
        }
        $value = trim((string) fgets(STDIN));
        if ($hidden) {
            shell_exec('stty echo');
            echo "\n";
        }
        return $value;
    }
}

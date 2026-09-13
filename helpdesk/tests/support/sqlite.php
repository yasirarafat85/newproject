<?php
declare(strict_types=1);

/**
 * টেস্টের জন্য MySQL স্কিমাকে SQLite-এ অনুবাদ করে মেমোরিতে ডেটাবেস বানায়।
 *
 * উদ্দেশ্য প্রোডাকশনে SQLite চালানো নয় — উদ্দেশ্য হলো MySQL সার্ভার ছাড়াই
 * TicketService-এর আসল লজিক (স্ট্যাটাস পরিবর্তন, SLA ঘড়ি, নম্বর তৈরি)
 * সত্যিকারের ডেটাবেসের বিরুদ্ধে পরীক্ষা করা। যে পার্থক্যগুলো গুরুত্বপূর্ণ নয়
 * (ENGINE, CHARSET, FULLTEXT, ইনলাইন KEY) সেগুলো বাদ দেওয়া হয়।
 */

use App\Core\Database;
use App\Services\Installer;

require_once __DIR__ . '/ddl.php';

function sqlite_schema_from_mysql(string $mysqlSchema): array
{
    $method = new ReflectionMethod(Installer::class, 'splitStatements');
    $method->setAccessible(true);
    $statements = $method->invoke(null, $mysqlSchema);

    $translated = [];

    foreach ($statements as $statement) {
        if (!str_starts_with($statement, 'CREATE TABLE')) {
            continue;   // SET ও ALTER ... ADD CONSTRAINT — SQLite-এ প্রযোজ্য নয়
        }

        $translated[] = sqlite_translate_create($statement);
    }

    return $translated;
}

function sqlite_translate_create(string $statement): string
{
    preg_match('/^CREATE TABLE IF NOT EXISTS\s+(\w+)\s*\((.*)\)\s*ENGINE.*$/s', $statement, $m);
    [$table, $body] = [$m[1], $m[2]];

    $lines = [];

    foreach (ddl_split_columns($body) as $raw) {
        // বহু লাইনে ছড়ানো সংজ্ঞা এক লাইনে আনি
        $line = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);

        // ইনডেক্স ও ফরেন কী — SQLite-এ ইনলাইন KEY নেই, FK পরীক্ষায় দরকারও নেই
        if (preg_match('/^(KEY|FULLTEXT KEY|CONSTRAINT)\b/i', $line) === 1) {
            continue;
        }

        if (preg_match('/^UNIQUE KEY\s+\w+\s*\((.+)\)$/i', $line, $u) === 1) {
            $lines[] = 'UNIQUE (' . preg_replace('/\(\d+\)/', '', $u[1]) . ')';
            continue;
        }

        if (preg_match('/^PRIMARY KEY\s*\((.+)\)$/i', $line, $p) === 1) {
            // একক `id` PK কলামের সংজ্ঞাতেই চলে গেছে
            if (trim($p[1]) === 'id') {
                continue;
            }
            $lines[] = 'PRIMARY KEY (' . $p[1] . ')';
            continue;
        }

        // COMMENT বাদ
        $line = preg_replace("/\s+COMMENT\s+'(?:[^'\\\\]|\\\\.)*'/i", '', $line) ?? $line;

        // অটো-ইনক্রিমেন্ট PK
        if (preg_match('/^id\s+BIGINT UNSIGNED NOT NULL AUTO_INCREMENT$/i', $line) === 1) {
            $lines[] = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
            continue;
        }

        // টাইপ ম্যাপিং — SQLite-এর affinity নিয়ম যথেষ্ট শিথিল
        $line = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT', $line) ?? $line;
        $line = preg_replace('/\b(BIGINT|INT|SMALLINT|TINYINT|MEDIUMINT)(\s+UNSIGNED)?(\(\d+\))?/i', 'INTEGER', $line) ?? $line;
        $line = preg_replace('/\b(VARCHAR|CHAR)\s*\(\d+\)/i', 'TEXT', $line) ?? $line;
        $line = preg_replace('/\b(MEDIUMTEXT|LONGTEXT|JSON)\b/i', 'TEXT', $line) ?? $line;
        $line = preg_replace('/\b(DATETIME|DATE|TIME|TIMESTAMP)\b/i', 'TEXT', $line) ?? $line;

        $lines[] = $line;
    }

    return 'CREATE TABLE IF NOT EXISTS ' . $table . " (\n  " . implode(",\n  ", $lines) . "\n)";
}

/** মেমোরিতে নতুন ডেটাবেস তৈরি করে Database-এ বসিয়ে দেয়। */
function sqlite_boot(string $schemaPath): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    foreach (sqlite_schema_from_mysql((string) file_get_contents($schemaPath)) as $statement) {
        $pdo->exec($statement);
    }

    Database::useConnection($pdo);

    return $pdo;
}

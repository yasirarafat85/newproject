<?php
declare(strict_types=1);

/**
 * schema.sql-এর স্ট্যাটিক যাচাই — MySQL সার্ভার ছাড়াই।
 *
 * ধরে:  ভুল টেবিল/কলামে ফরেন কী · কলাম না থাকা · ইনডেক্সহীন FK ·
 *        ভুল ক্রমে টেবিল তৈরি · ডুপ্লিকেট কনস্ট্রেইন্ট নাম।
 */

require __DIR__ . '/../app/Core/Autoloader.php';
Autoloader::register(dirname(__DIR__));

use App\Core\Config;
use App\Core\Env;
use App\Services\Installer;

Env::load(dirname(__DIR__) . '/.env');
Config::setPath(dirname(__DIR__) . '/config');

$sql = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$method = new ReflectionMethod(Installer::class, 'splitStatements');
$method->setAccessible(true);
/** @var string[] $statements */
$statements = $method->invoke(null, $sql);

$errors = [];
$tables = [];          // table => [column => true]
$order = [];           // যে ক্রমে টেবিল তৈরি হচ্ছে
$constraintNames = [];
$foreignKeys = [];     // [table, column, refTable, refColumn, atIndex]
$indexed = [];         // table => [column => true]  (PK / KEY / UNIQUE-এর প্রথম কলাম)

foreach ($statements as $index => $statement) {
    if (preg_match('/^CREATE TABLE IF NOT EXISTS\s+(\w+)\s*\((.*)\)\s*ENGINE/s', $statement, $m) !== 1) {
        continue;
    }

    [$table, $body] = [$m[1], $m[2]];

    if (isset($tables[$table])) {
        $errors[] = "ডুপ্লিকেট টেবিল: {$table}";
    }
    $tables[$table] = [];
    $indexed[$table] = [];
    $order[$table] = $index;

    foreach (preg_split("/,\n/", $body) ?: [] as $line) {
        $line = trim($line);

        if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY|FULLTEXT KEY|CONSTRAINT)\b/i', $line) === 1) {
            continue;   // কলাম নয়, ইনডেক্স/কনস্ট্রেইন্ট
        }
        if (preg_match('/^(\w+)\s+/', $line, $c) === 1) {
            $tables[$table][$c[1]] = true;
        }
    }

    // ইনডেক্সের প্রথম কলাম
    if (preg_match_all('/(?:PRIMARY KEY|UNIQUE KEY\s+\w+|KEY\s+\w+|FULLTEXT KEY\s+\w+)\s*\(\s*`?(\w+)`?/i', $body, $idx) !== false) {
        foreach ($idx[1] as $column) {
            $indexed[$table][$column] = true;
        }
    }

    // এই টেবিলের ভেতরের FK
    if (preg_match_all('/CONSTRAINT\s+(\w+)\s+FOREIGN KEY\s*\(\s*(\w+)\s*\)\s*REFERENCES\s+(\w+)\s*\(\s*(\w+)\s*\)/i', $body, $fks, PREG_SET_ORDER) !== false) {
        foreach ($fks as $fk) {
            $constraintNames[] = $fk[1];
            $foreignKeys[] = [$table, $fk[2], $fk[3], $fk[4], $index, 'inline'];
        }
    }
}

// ALTER TABLE দিয়ে যোগ করা FK
foreach ($statements as $index => $statement) {
    if (preg_match('/^ALTER TABLE\s+(\w+)/i', $statement, $m) !== 1) {
        continue;
    }
    $table = $m[1];
    if (preg_match_all('/CONSTRAINT\s+(\w+)\s+FOREIGN KEY\s*\(\s*(\w+)\s*\)\s*REFERENCES\s+(\w+)\s*\(\s*(\w+)\s*\)/i', $statement, $fks, PREG_SET_ORDER) !== false) {
        foreach ($fks as $fk) {
            $constraintNames[] = $fk[1];
            $foreignKeys[] = [$table, $fk[2], $fk[3], $fk[4], $index, 'alter'];
        }
    }
}

// ---- যাচাই ----
foreach ($foreignKeys as [$table, $column, $refTable, $refColumn, $at, $kind]) {
    if (!isset($tables[$table])) {
        $errors[] = "FK-র টেবিল নেই: {$table}";
        continue;
    }
    if (!isset($tables[$table][$column])) {
        $errors[] = "{$table}.{$column} কলামটি নেই, অথচ তাতে FK আছে";
    }
    if (!isset($tables[$refTable])) {
        $errors[] = "{$table}.{$column} → অজানা টেবিল {$refTable}";
        continue;
    }
    if (!isset($tables[$refTable][$refColumn])) {
        $errors[] = "{$table}.{$column} → {$refTable}.{$refColumn} কলামটি নেই";
    }
    // ইনলাইন FK হলে রেফারেন্স করা টেবিল আগেই তৈরি হতে হবে
    if ($kind === 'inline' && $refTable !== $table && ($order[$refTable] ?? PHP_INT_MAX) > $at) {
        $errors[] = "ক্রম ভুল: {$table} তৈরির সময় {$refTable} এখনো তৈরি হয়নি (ALTER TABLE-এ সরান)";
    }
}

$duplicates = array_filter(array_count_values($constraintNames), static fn (int $n): bool => $n > 1);
foreach (array_keys($duplicates) as $name) {
    $errors[] = "ডুপ্লিকেট কনস্ট্রেইন্ট নাম: {$name} (MySQL-এ নাম ডেটাবেস-জুড়ে অনন্য হতে হয়)";
}

// InnoDB প্রতিটি FK কলামে ইনডেক্স চায়; না থাকলে নিজে বানায়, তবু স্পষ্ট করাই ভালো
$missingIndex = [];
foreach ($foreignKeys as [$table, $column]) {
    if (isset($tables[$table]) && !isset($indexed[$table][$column])) {
        $missingIndex[] = "{$table}.{$column}";
    }
}

// ---- ফলাফল ----
echo "\n--- স্কিমা যাচাই ---\n";
printf("  টেবিল: %d · ফরেন কী: %d · কনস্ট্রেইন্ট নাম: %d\n", count($tables), count($foreignKeys), count($constraintNames));

if ($missingIndex !== []) {
    echo "  ℹ স্পষ্ট ইনডেক্স ছাড়া FK কলাম (InnoDB নিজে তৈরি করে নেবে): " . count($missingIndex) . "টি\n";
    foreach (array_slice($missingIndex, 0, 8) as $item) {
        echo "      · {$item}\n";
    }
}

if ($errors === []) {
    echo "  ✓ সব ফরেন কী বৈধ টেবিল ও কলামে যাচ্ছে\n";
    echo "  ✓ টেবিল তৈরির ক্রম সঠিক\n";
    echo "  ✓ কনস্ট্রেইন্টের নাম অনন্য\n";
    echo "\n  স্কিমা যাচাই সফল।\n\n";
    exit(0);
}

echo "\n  ✗ " . count($errors) . "টি সমস্যা:\n";
foreach ($errors as $error) {
    echo "      · {$error}\n";
}
echo "\n";
exit(1);

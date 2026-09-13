<?php
declare(strict_types=1);

/**
 * সব টেস্ট চালায়:  php tests/run.php
 *
 * MySQL সার্ভার লাগে না — স্কিমাটি SQLite-এ অনুবাদ করে মেমোরিতে চালানো হয়,
 * তাই সার্ভিস ও কন্ট্রোলারের আসল কোডপথই যাচাই হয়।
 */

$suites = [
    'core_test.php'   => 'কোর ফ্রেমওয়ার্ক',
    'schema_test.php' => 'ডেটাবেস স্কিমা',
    'sanitizer_test.php' => 'HTML স্যানিটাইজার',
    'ticket_test.php' => 'টিকেট লাইফসাইকেল',
    'http_test.php'   => 'রাউট ও অনুমতি',
];

$failed = [];

foreach ($suites as $file => $label) {
    echo "\n======  {$label}  ({$file})  ======\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file), $status);
    if ($status !== 0) {
        $failed[] = $label;
    }
}

if ($failed === []) {
    echo "\n✓ সব টেস্ট পাস করেছে।\n\n";
    exit(0);
}

echo "\n✗ ব্যর্থ: " . implode(', ', $failed) . "\n\n";
exit(1);

<?php
declare(strict_types=1);

/**
 * সব টেস্ট চালায়:  php tests/run.php
 * ডেটাবেস সার্ভার লাগে না — কোর লজিক ও স্কিমা স্ট্যাটিকভাবে যাচাই হয়।
 */

$suites = ['core_test.php', 'schema_test.php'];
$failed = 0;

foreach ($suites as $suite) {
    echo "\n======  {$suite}  ======\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $suite), $status);
    if ($status !== 0) {
        $failed++;
    }
}

echo $failed === 0 ? "\n✓ সব টেস্ট পাস করেছে।\n\n" : "\n✗ {$failed}টি টেস্ট স্যুট ব্যর্থ।\n\n";
exit($failed === 0 ? 0 : 1);

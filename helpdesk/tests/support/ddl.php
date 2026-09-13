<?php
declare(strict_types=1);

/**
 * CREATE TABLE-এর ভেতরের অংশকে কলাম/কনস্ট্রেইন্ট অনুযায়ী ভাগ করে।
 *
 * সরল `,\n` দিয়ে ভাগ করা যায় না — ENUM('a','b') এর মান একাধিক লাইনে
 * ছড়াতে পারে, আর COMMENT-এর ভেতরেও কমা থাকতে পারে। তাই বন্ধনীর
 * গভীরতা ও উদ্ধৃতি হিসেব করে কেবল উপরের স্তরের কমাতেই ভাগ করি।
 *
 * @return string[]
 */
function ddl_split_columns(string $body): array
{
    $parts = [];
    $current = '';
    $depth = 0;
    $quote = null;
    $length = strlen($body);

    for ($i = 0; $i < $length; $i++) {
        $char = $body[$i];

        if ($quote !== null) {
            $current .= $char;

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $body[++$i];   // এস্কেপ করা অক্ষর
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $current .= $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
            continue;
        }

        $current .= $char;
    }

    if (trim($current) !== '') {
        $parts[] = trim($current);
    }

    return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
}

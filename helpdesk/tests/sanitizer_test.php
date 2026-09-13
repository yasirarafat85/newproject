<?php
declare(strict_types=1);

/**
 * HTML স্যানিটাইজার — XSS পেলোডের বিরুদ্ধে।
 *
 * গ্রাহক, এজেন্ট ও ইমেইল — তিন উৎস থেকেই HTML আসে, তাই এই স্তরটি
 * ভাঙলে পুরো সিস্টেম ভাঙে। প্রতিটি নিয়ম আলাদা করে যাচাই করা হয়।
 */

require __DIR__ . '/support/bootstrap.php';

use App\Core\Sanitizer;

$pass = 0;
$fail = 0;

/** পেলোড পরিষ্কার করার পর নিষিদ্ধ অংশটি আর থাকা চলবে না। */
function blocks(string $label, string $payload, string $mustNotContain): void
{
    global $pass, $fail;
    $result = Sanitizer::clean($payload);

    if (stripos($result, $mustNotContain) === false) {
        $pass++;
        echo "  ✓ {$label}\n";
    } else {
        $fail++;
        echo "  ✗ {$label}\n      ফলাফলে রয়ে গেছে: " . $result . "\n";
    }
}

function keeps(string $label, string $payload, string $mustContain): void
{
    global $pass, $fail;
    $result = Sanitizer::clean($payload);

    if (str_contains($result, $mustContain)) {
        $pass++;
        echo "  ✓ {$label}\n";
    } else {
        $fail++;
        echo "  ✗ {$label}\n      পেয়েছি: " . $result . "\n";
    }
}

function equals(string $label, mixed $actual, mixed $expected): void
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "  ✓ {$label}\n";
    } else {
        $fail++;
        echo "  ✗ {$label}\n      পেয়েছি   : " . var_export($actual, true) . "\n      চেয়েছিলাম: " . var_export($expected, true) . "\n";
    }
}

echo "\n--- স্ক্রিপ্ট চালানোর চেষ্টা ---\n";
blocks('script ট্যাগ',            '<p>ক</p><script>alert(1)</script>', '<script');
blocks('script-এর ভেতরের কোডও যায়', '<script>alert("গোপন")</script>', 'alert');
blocks('onerror হ্যান্ডলার',       '<img src=x onerror=alert(1)>', 'onerror');
blocks('onclick হ্যান্ডলার',       '<p onclick="steal()">চাপুন</p>', 'onclick');
blocks('onload হ্যান্ডলার',        '<body onload="alert(1)">লেখা</body>', 'onload');
blocks('javascript: URL',          '<a href="javascript:alert(1)">ক্লিক</a>', 'javascript');
blocks('ট্যাব দিয়ে ফাঁকি',         "<a href=\"java\tscript:alert(1)\">ক্লিক</a>", 'script');
blocks('নতুন লাইন দিয়ে ফাঁকি',     "<a href=\"java\nscript:alert(1)\">ক্লিক</a>", 'script');
blocks('vbscript: URL',            '<a href="vbscript:msgbox(1)">ক্লিক</a>', 'vbscript');
blocks('svg ভেক্টর',               '<svg onload="alert(1)"></svg>', 'svg');
blocks('iframe',                   '<iframe src="//evil.example"></iframe>', 'iframe');
blocks('object এম্বেড',            '<object data="evil.swf"></object>', 'object');
blocks('meta রিফ্রেশ',             '<meta http-equiv="refresh" content="0;url=//evil">', 'meta');
blocks('base ট্যাগ',               '<base href="//evil.example/">', 'base');

echo "\n--- ফিশিং ও ইন্টারফেস দখল ---\n";
blocks('form ইনজেকশন',            '<form action="//evil"><input name="pw"></form>', '<form');
blocks('পাসওয়ার্ড ইনপুট',          '<input type="password" name="pw">', '<input');
blocks('style অ্যাট্রিবিউট',       '<p style="position:fixed;inset:0">ঢাকনা</p>', 'style=');
blocks('style ট্যাগ',              '<style>body{display:none}</style>', '<style');
blocks('data:text/html',           '<img src="data:text/html,<script>alert(1)</script>">', 'data:text/html');
blocks('file: প্রোটোকল',           '<a href="file:///etc/passwd">ফাইল</a>', 'file:');

echo "\n--- বৈধ কনটেন্ট টিকে থাকে ---\n";
keeps('অনুচ্ছেদ',                  '<p>প্রিন্টার কাজ করছে না</p>', '<p>প্রিন্টার কাজ করছে না</p>');
keeps('গাঢ় ও তির্যক',              '<p><strong>জরুরি</strong> ও <em>দ্রুত</em></p>', '<strong>জরুরি</strong>');
keeps('তালিকা',                    '<ul><li>এক</li><li>দুই</li></ul>', '<li>এক</li>');
keeps('টেবিল',                     '<table><tr><td colspan="2">ঘর</td></tr></table>', 'colspan="2"');
keeps('কোড ব্লক',                  '<pre><code>SELECT 1</code></pre>', '<code>SELECT 1</code>');
keeps('উদ্ধৃতি',                   '<blockquote>তিনি বললেন</blockquote>', '<blockquote>');
keeps('বৈধ https লিংক',            '<a href="https://example.com">সাইট</a>', 'href="https://example.com"');
keeps('mailto লিংক',               '<a href="mailto:a@b.com">মেইল</a>', 'mailto:a@b.com');
keeps('আপেক্ষিক পথ',               '<a href="/attachments/abc">ফাইল</a>', 'href="/attachments/abc"');
keeps('data:image অনুমোদিত',        '<img src="data:image/png;base64,iVBORw0KGgo=">', 'data:image/png');
keeps('বাংলা অক্ষর অক্ষত',          '<p>অগ্রাধিকার — ৮৫% সম্পন্ন</p>', 'অগ্রাধিকার — ৮৫% সম্পন্ন');
keeps('অজানা ট্যাগের লেখা থাকে',    '<marquee>গুরুত্বপূর্ণ</marquee>', 'গুরুত্বপূর্ণ');

echo "\n--- লিংকের নিরাপত্তা ---\n";
keeps('বাইরের লিংকে noopener',      '<a href="https://example.com">ক</a>', 'rel="noopener noreferrer nofollow"');
keeps('বাইরের লিংক নতুন ট্যাবে',     '<a href="https://example.com">ক</a>', 'target="_blank"');

echo "\n--- প্লেইন টেক্সট রূপান্তর ---\n";
equals('ট্যাগ এস্কেপ হয়',
    Sanitizer::fromPlainText('<script>alert(1)</script>'),
    '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');
equals('দুই অনুচ্ছেদ আলাদা হয়',
    Sanitizer::fromPlainText("প্রথম\n\nদ্বিতীয়"),
    '<p>প্রথম</p><p>দ্বিতীয়</p>');
equals('একক নতুন লাইন <br> হয়',
    Sanitizer::fromPlainText("প্রথম\nদ্বিতীয়"),
    "<p>প্রথম<br>\nদ্বিতীয়</p>");
equals('খালি ইনপুটে খালি ফল', Sanitizer::fromPlainText('   '), '');
equals('null নিরাপদে সামলায়', Sanitizer::clean(null), '');

echo "\n=======================================\n";
echo "  পাস: {$pass}   ব্যর্থ: {$fail}\n";
echo "=======================================\n";
exit($fail === 0 ? 0 : 1);

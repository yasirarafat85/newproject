<?php
declare(strict_types=1);

namespace App\Core;

final class Str
{
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function random(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $value): string
    {
        $value = trim($value);
        // বাংলা অক্ষর ধরে রাখি — শুধু বিপজ্জনক/পাথ অক্ষর বাদ দিই
        $value = preg_replace('/[^\p{L}\p{N}\s\-]+/u', '', $value) ?? '';
        $value = preg_replace('/[\s\-]+/u', '-', $value) ?? '';

        return trim(mb_strtolower($value, 'UTF-8'), '-');
    }

    public static function limit(string $value, int $length = 100, string $end = '…'): string
    {
        return mb_strlen($value, 'UTF-8') <= $length
            ? $value
            : rtrim(mb_substr($value, 0, $length, 'UTF-8')) . $end;
    }

    /** HTML থেকে পড়ার উপযোগী প্লেইন টেক্সট — ইমেইলের text/plain অংশে ব্যবহৃত। */
    public static function htmlToText(string $html): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        // ব্লক শেষে ফাঁকা লাইন, তালিকা/সারির শেষে একটিই নতুন লাইন
        $text = preg_replace('#</(p|div|blockquote|h[1-6])>#i', "\n\n", $text) ?? $text;
        $text = preg_replace('#</(li|tr)>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1, 'UTF-8');
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8') : '';

        return mb_strtoupper($first . $last, 'UTF-8');
    }

    public static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $size : number_format($size, 1)) . ' ' . $units[$i];
    }
}

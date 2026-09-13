<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\QueryBuilder;
use App\Core\Str;
use RuntimeException;

/**
 * ফাইল আপলোড যাচাই, সংরক্ষণ ও সরবরাহ।
 *
 * ফাইল কখনো ওয়েব রুটে যায় না — storage/attachments/YYYY/MM/ এ
 * র‍্যান্ডম নামে থাকে, আর শুধু permission যাচাইয়ের পর স্ট্রিম হয়।
 */
final class AttachmentService
{
    /** এই টাইপগুলো ব্রাউজারে চালানো যায়, তাই নাম বদলে দিলেও গ্রহণ করি না। */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'inc',
        'exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'dll',
        'sh', 'bash', 'ps1', 'vbs', 'js', 'jar', 'htaccess',
        'html', 'htm', 'xhtml', 'svg',
    ];

    /**
     * একটি আপলোড যাচাই করে সংরক্ষণ করে।
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @return int attachment id
     */
    public static function store(array $file, int $ticketId, ?int $threadId, string $uploaderType, ?int $uploaderId): int
    {
        self::assertUploadOk($file);

        $originalName = self::safeName($file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        self::assertExtensionAllowed($extension);
        self::assertSizeAllowed((int) $file['size']);

        // ঘোষিত MIME নয়, ফাইলের ভেতরের ম্যাজিক বাইট দেখি
        $mime = self::detectMime($file['tmp_name']);
        self::assertMimeMatchesExtension($mime, $extension);

        $relativeDir = date('Y/m');
        $absoluteDir = storage_path('attachments/' . $relativeDir);

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0750, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('অ্যাটাচমেন্ট ফোল্ডার তৈরি করা যায়নি।');
        }

        $storedName = Str::random(20) . '.bin';
        $absolutePath = $absoluteDir . '/' . $storedName;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $absolutePath)
            : rename($file['tmp_name'], $absolutePath);

        if ($moved === false) {
            throw new RuntimeException('ফাইলটি সংরক্ষণ করা যায়নি।');
        }

        @chmod($absolutePath, 0640);

        return QueryBuilder::table('attachments')->insert([
            'uuid'             => Str::uuid(),
            'ticket_id'        => $ticketId,
            'thread_id'        => $threadId,
            'original_name'    => $originalName,
            'stored_path'      => $relativeDir . '/' . $storedName,
            'mime_type'        => $mime,
            'size_bytes'       => (int) $file['size'],
            'checksum_sha256'  => hash_file('sha256', $absolutePath) ?: null,
            'uploaded_by_type' => $uploaderType,
            'uploaded_by_id'   => $uploaderId,
            'created_at'       => now(),
        ]);
    }

    /**
     * একাধিক ফাইল সংরক্ষণ। কোনো একটি ব্যর্থ হলে বাকিগুলো এগিয়ে যায়;
     * ব্যর্থ ফাইলের বার্তা ফেরত আসে যাতে ব্যবহারকারীকে জানানো যায়।
     *
     * @return array{ids:int[], errors:string[]}
     */
    public static function storeMany(array $files, int $ticketId, ?int $threadId, string $uploaderType, ?int $uploaderId): array
    {
        $ids = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $ids[] = self::store($file, $ticketId, $threadId, $uploaderType, $uploaderId);
            } catch (RuntimeException $e) {
                $errors[] = self::safeName($file['name'] ?? 'ফাইল') . ': ' . $e->getMessage();
            }
        }

        return ['ids' => $ids, 'errors' => $errors];
    }

    public static function absolutePath(array $attachment): string
    {
        return storage_path('attachments/' . $attachment['stored_path']);
    }

    public static function deleteFor(int $ticketId): void
    {
        foreach (QueryBuilder::table('attachments')->where('ticket_id', $ticketId)->get() as $attachment) {
            @unlink(self::absolutePath($attachment));
        }
    }

    public static function humanLimit(): string
    {
        return (string) Config::get('app.upload_max_mb', 25) . ' MB';
    }

    // ---------- যাচাই ----------

    private static function assertUploadOk(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        throw new RuntimeException(match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ফাইলটি অনুমোদিত আকারের চেয়ে বড়।',
            UPLOAD_ERR_PARTIAL                        => 'ফাইলটি সম্পূর্ণ আপলোড হয়নি, আবার চেষ্টা করুন।',
            UPLOAD_ERR_NO_FILE                        => 'কোনো ফাইল পাওয়া যায়নি।',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'সার্ভারে ফাইল লেখা যায়নি।',
            default                                   => 'ফাইল আপলোডে সমস্যা হয়েছে।',
        });
    }

    private static function assertExtensionAllowed(string $extension): void
    {
        if ($extension === '') {
            throw new RuntimeException('ফাইলের এক্সটেনশন নেই।');
        }

        if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new RuntimeException('নিরাপত্তার কারণে এই ধরনের ফাইল গ্রহণ করা হয় না।');
        }

        $allowed = (array) Config::get('app.upload_allowed', []);
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('এই ধরনের ফাইল অনুমোদিত নয় (' . implode(', ', $allowed) . ')।');
        }
    }

    private static function assertSizeAllowed(int $bytes): void
    {
        $max = (int) Config::get('app.upload_max_mb', 25) * 1024 * 1024;

        if ($bytes <= 0) {
            throw new RuntimeException('ফাইলটি খালি।');
        }

        if ($bytes > $max) {
            throw new RuntimeException('ফাইলটি ' . self::humanLimit() . ' এর চেয়ে বড়।');
        }
    }

    private static function detectMime(string $path): string
    {
        if (!function_exists('finfo_open')) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) && $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * এক্সটেনশন আর আসল কনটেন্ট মেলে কি না।
     *
     * মূল লক্ষ্য: `.jpg` নাম দিয়ে HTML/স্ক্রিপ্ট পাঠানো ঠেকানো। অফিস ও
     * আর্কাইভ ফাইলের MIME সিস্টেমভেদে বদলায়, তাই সেগুলোতে কঠোর নই —
     * বিপজ্জনক টাইপগুলোই স্পষ্টভাবে আটকাই।
     */
    private static function assertMimeMatchesExtension(string $mime, string $extension): void
    {
        $dangerous = ['text/html', 'application/xhtml+xml', 'image/svg+xml', 'application/x-httpd-php', 'text/x-php'];
        if (in_array($mime, $dangerous, true)) {
            throw new RuntimeException('ফাইলের ভেতরের কনটেন্ট নিরাপদ নয়।');
        }

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extension, $imageExtensions, true) && !str_starts_with($mime, 'image/')) {
            throw new RuntimeException('ফাইলটি আসলে ছবি নয়।');
        }

        if ($extension === 'pdf' && $mime !== 'application/pdf') {
            throw new RuntimeException('ফাইলটি আসলে PDF নয়।');
        }
    }

    /** পাথ ট্রাভার্সাল ও কন্ট্রোল ক্যারেক্টার সরিয়ে নিরাপদ প্রদর্শনযোগ্য নাম। */
    private static function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        return mb_substr(trim($name), 0, 200, 'UTF-8') ?: 'ফাইল';
    }
}

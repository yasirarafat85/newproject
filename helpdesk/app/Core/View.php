<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * নেটিভ PHP টেমপ্লেট রেন্ডারার।
 *
 * ভিউ ফাইল একটি লেআউটে মোড়ানো হয়; লেআউট `$content` ভেরিয়েবলে
 * রেন্ডার করা ভিউ পায়। ভিউতে পাঠানো সব ডেটা extract() করা হয়।
 */
final class View
{
    private static string $path = '';
    private static array $shared = [];
    private static ?string $title = null;

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    /** সব ভিউতে পাওয়া যাবে এমন ডেটা (যেমন লগইন করা এজেন্ট, নোটিফিকেশন সংখ্যা)। */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * ভিউ রেন্ডার করে, দরকার হলে লেআউটে মুড়ে দেয়।
     *
     * ভিউ ফাইল `title('…')` ডেকে পাতার শিরোনাম ঠিক করতে পারে; ভিউয়ের
     * স্কোপ আলাদা বলে সেটি static-এ রেখে লেআউটে পাঠানো হয়।
     */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        self::$title = $data['title'] ?? null;

        $content = self::capture($template, $data);

        if ($layout !== null) {
            $content = self::capture($layout, array_merge($data, [
                'content' => $content,
                'title'   => self::$title ?? '',
            ]));
        }

        return $content;
    }

    /** ভিউ ফাইলের ভেতর থেকে পাতার শিরোনাম দেওয়ার জন্য (title() হেল্পার ব্যবহার করে)। */
    public static function setTitle(string $title): void
    {
        self::$title = $title;
    }

    public static function getTitle(): ?string
    {
        return self::$title;
    }

    public static function exists(string $template): bool
    {
        return is_file(self::$path . '/' . $template . '.php');
    }

    /** লেআউট/ভিউয়ের ভেতর থেকে ছোট অংশ বসাতে:  <?= View::partial('partials/badge', [...]) ?> */
    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data);
    }

    private static function capture(string $template, array $data): string
    {
        $file = self::$path . '/' . str_replace('..', '', $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("ভিউ ফাইল পাওয়া যায়নি: {$template}");
        }

        extract(array_merge(self::$shared, $data), EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}

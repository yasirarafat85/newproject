<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

/**
 * HTTP-স্তরের ত্রুটি (404, 403, 419…)।
 *
 * ইচ্ছে করেই RuntimeException থেকে নয় — কন্ট্রোলাররা সার্ভিসের ডোমেইন
 * ত্রুটি ধরতে `catch (RuntimeException)` ব্যবহার করে, আর তখন অনুমতি
 * অস্বীকৃতিও সেখানে আটকে গিয়ে বন্ধুত্বপূর্ণ বার্তায় পরিণত হতো — ব্যবহারকারী
 * 403-এর বদলে একটি রিডাইরেক্ট পেতেন।
 */
class HttpException extends Exception
{
    public function __construct(private readonly int $statusCode, string $message = '')
    {
        parent::__construct($message === '' ? self::defaultMessage($statusCode) : $message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'অনুরোধটি সঠিক নয়।',
            401 => 'আগে লগইন করুন।',
            403 => 'এই কাজটি করার অনুমতি আপনার নেই।',
            404 => 'পৃষ্ঠাটি খুঁজে পাওয়া যায়নি।',
            405 => 'এই মেথড সমর্থিত নয়।',
            419 => 'নিরাপত্তা টোকেনের মেয়াদ শেষ। পৃষ্ঠাটি রিফ্রেশ করে আবার চেষ্টা করুন।',
            422 => 'দেওয়া তথ্যে ভুল আছে।',
            429 => 'অনেক বেশি চেষ্টা করা হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।',
            default => 'সার্ভারে সমস্যা হয়েছে।',
        };
    }
}

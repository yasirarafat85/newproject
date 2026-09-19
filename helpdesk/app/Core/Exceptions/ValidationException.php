<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

/** ফর্ম ভ্যালিডেশন ব্যর্থতা — HttpException-এর মতোই ফ্রেমওয়ার্কের কন্ট্রোল-ফ্লো। */
class ValidationException extends Exception
{
    public function __construct(private readonly array $errors, private readonly array $old = [])
    {
        parent::__construct('দেওয়া তথ্যে ভুল আছে।');
    }

    /** @return array<string, string> ফিল্ড → প্রথম বার্তা */
    public function errors(): array
    {
        return $this->errors;
    }

    public function old(): array
    {
        return $this->old;
    }
}

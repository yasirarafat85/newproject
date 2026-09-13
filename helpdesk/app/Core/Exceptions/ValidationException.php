<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

class ValidationException extends RuntimeException
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

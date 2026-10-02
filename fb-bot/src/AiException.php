<?php

declare(strict_types=1);

namespace App;

final class AiException extends \RuntimeException
{
    /** @param bool $retryable true for rate limits, overload, server and network errors */
    public function __construct(string $message, public readonly bool $retryable, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

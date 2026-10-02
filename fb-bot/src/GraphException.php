<?php

declare(strict_types=1);

namespace App;

final class GraphException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus, public readonly int $graphCode = 0)
    {
        parent::__construct($message);
    }
}

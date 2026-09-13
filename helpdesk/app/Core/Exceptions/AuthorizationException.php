<?php
declare(strict_types=1);

namespace App\Core\Exceptions;

class AuthorizationException extends HttpException
{
    public function __construct(string $message = '')
    {
        parent::__construct(403, $message);
    }
}

<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/** সব state পরিবর্তনকারী রিকোয়েস্টে টোকেন যাচাই। */
final class VerifyCsrf
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // API রুট টোকেনের বদলে API কী দিয়ে প্রমাণিত হয়
        if (str_starts_with($request->path(), '/api/')) {
            return $next($request);
        }

        $token = $request->raw('_token') ?? $request->header('X-CSRF-Token');
        if (!Csrf::check(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }

        return $next($request);
    }
}

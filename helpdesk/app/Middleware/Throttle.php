<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/** গেস্ট ফর্মে অপব্যবহার ঠেকাতে সেশন-ভিত্তিক হালকা থ্রটল। */
final class Throttle
{
    public function __construct(
        private readonly int $max = 20,
        private readonly int $decaySeconds = 60,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($request->method() === 'GET') {
            return $next($request);
        }

        if (RateLimiter::hit('route:' . $request->path(), $this->max, $this->decaySeconds)) {
            throw new HttpException(429);
        }

        return $next($request);
    }
}

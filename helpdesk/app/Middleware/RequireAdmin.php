<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Request;
use App\Core\Response;

/**
 * অ্যাডমিন প্যানেলের দরজা। সুপার অ্যাডমিন সবসময় ঢোকে;
 * অন্যদের অন্তত একটি admin.* পারমিশন থাকতে হবে।
 */
final class RequireAdmin
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::isAdmin()) {
            return $next($request);
        }

        foreach (Auth::permissions() as $code) {
            if (str_starts_with($code, 'admin.')) {
                return $next($request);
            }
        }

        throw new AuthorizationException('অ্যাডমিন প্যানেলে ঢোকার অনুমতি আপনার নেই।');
    }
}

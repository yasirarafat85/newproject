<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthAgent
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::isAgent()) {
            if ($request->wantsJson()) {
                return Response::apiError('আগে লগইন করুন।', 401);
            }

            // লগইনের পর যেখানে যেতে চেয়েছিলেন সেখানেই ফেরত পাঠাতে
            Session::put('_intended', $request->path());

            return Response::redirect(url('/agent/login'));
        }

        return $next($request);
    }
}

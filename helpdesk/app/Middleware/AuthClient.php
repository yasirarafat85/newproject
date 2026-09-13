<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthClient
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::isClient()) {
            if ($request->wantsJson()) {
                return Response::apiError('আগে লগইন করুন।', 401);
            }

            Session::put('_intended', $request->path());
            flash('warning', 'এই পাতাটি দেখতে লগইন করুন।');

            return Response::redirect(url('/login'));
        }

        return $next($request);
    }
}

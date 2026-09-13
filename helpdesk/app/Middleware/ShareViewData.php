<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/**
 * প্রতিটি পাতায় দরকারি সাধারণ ডেটা ভিউতে পাঠায় —
 * লেআউট যাতে কন্ট্রোলারের উপর নির্ভর না করে।
 */
final class ShareViewData
{
    public function handle(Request $request, callable $next): Response
    {
        // ভ্যালিডেশন ব্যর্থ হলে back() যেন সঠিক পাতায় ফেরাতে পারে
        if ($request->method() === 'GET' && !$request->wantsJson()) {
            Session::put('_previous', $request->path());
        }

        View::share('currentPath', $request->path());
        View::share('notice', Session::getFlash('_notice'));
        View::share('appName', (string) config('app.name', 'HelpDesk'));
        View::share('companyName', (string) setting('company_name', config('app.name', 'HelpDesk')));

        $agent = Auth::agent();
        View::share('authAgent', $agent);
        View::share('authClient', Auth::client());

        View::share('unreadCount', $agent === null ? 0 : QueryBuilder::table('notifications')
            ->where('agent_id', (int) $agent['id'])
            ->where('is_read', 0)
            ->count());

        return $next($request);
    }
}

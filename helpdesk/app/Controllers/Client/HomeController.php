<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('client/home', [
            'topics'      => QueryBuilder::table('help_topics')
                ->where('is_public', 1)->where('is_active', 1)
                ->orderBy('sort_order')->limit(6)->get(),
            'articles'    => QueryBuilder::table('kb_articles')
                ->select('title', 'slug', 'excerpt')
                ->where('is_public', 1)
                ->orderBy('is_featured', 'DESC')->orderBy('views', 'DESC')
                ->limit(5)->get(),
            'openCount'   => Auth::isClient()
                ? QueryBuilder::table('tickets')
                    ->join('statuses', 'statuses.id', '=', 'tickets.status_id')
                    ->where('tickets.user_id', (int) Auth::clientId())
                    ->whereIn('statuses.state', ['open', 'paused'])
                    ->whereNull('tickets.deleted_at')
                    ->count()
                : 0,
        ], 'layouts/client');
    }
}

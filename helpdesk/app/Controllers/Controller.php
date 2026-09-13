<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Response;
use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = null): Response
    {
        return Response::make(View::render($template, $data, $layout));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(str_starts_with($path, 'http') ? $path : url($path));
    }

    /** অনুমতি না থাকলে সঙ্গে সঙ্গে 403 — কন্ট্রোলারে if/else ছড়ায় না। */
    protected function authorize(string $permission, ?array $ticket = null): void
    {
        if (!Auth::can($permission, $ticket)) {
            throw new AuthorizationException();
        }
    }
}

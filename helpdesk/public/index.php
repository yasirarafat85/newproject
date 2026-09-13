<?php
declare(strict_types=1);

/**
 * একমাত্র এন্ট্রি পয়েন্ট। Apache/nginx-এর DocumentRoot এই public/ ফোল্ডার।
 */

use App\Core\Config;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/** @var App\Core\Router $router */
$router = require dirname(__DIR__) . '/bootstrap.php';

Session::start();

$request = Request::capture();

// ইনস্টল না হয়ে থাকলে সব রুট ইনস্টলারে পাঠাই
if (!Config::get('app.installed', false) && !str_starts_with($request->path(), '/install')) {
    Response::redirect(url('/install'))->send();
    exit;
}

try {
    $response = $router->dispatch($request);
} catch (ValidationException $e) {
    // ফর্ম ভুল → পুরনো মান ও বার্তা নিয়ে আগের পাতায় ফেরত
    Session::flash('_errors', $e->errors());
    Session::flash('_old', $e->old());

    $response = $request->wantsJson()
        ? Response::apiError($e->getMessage(), 422, $e->errors())
        : back();
} catch (HttpException $e) {
    $response = renderError($request, $e->getStatusCode(), $e->getMessage());
} catch (\Throwable $e) {
    Logger::exception($e);

    $message = Config::get('app.debug', false)
        ? $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine()
        : 'সার্ভারে একটি সমস্যা হয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।';

    $response = renderError($request, 500, $message);
}

$response->send();

function renderError(Request $request, int $status, string $message): Response
{
    if ($request->wantsJson()) {
        return Response::apiError($message, $status);
    }

    if (View::exists('errors/error')) {
        return Response::make(
            View::render('errors/error', ['status' => $status, 'message' => $message], 'layouts/bare'),
            $status
        );
    }

    return Response::make('<h1>' . $status . '</h1><p>' . e($message) . '</p>', $status);
}

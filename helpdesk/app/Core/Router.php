<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;

/**
 * রুট রেজিস্ট্রি ও ডিসপ্যাচার।
 *
 * প্যাটার্ন সিনট্যাক্স:  /agent/tickets/{id}   →  $request->param('id')
 * গ্রুপ prefix ও middleware উত্তরাধিকারসূত্রে নেস্টেড রুটে যায়।
 */
final class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, params:array, action:mixed, middleware:array, name:?string}>> */
    private array $routes = [];
    private array $groupStack = [];
    private array $namedRoutes = [];

    public function get(string $pattern, mixed $action): self
    {
        return $this->add('GET', $pattern, $action);
    }

    public function post(string $pattern, mixed $action): self
    {
        return $this->add('POST', $pattern, $action);
    }

    public function put(string $pattern, mixed $action): self
    {
        return $this->add('PUT', $pattern, $action);
    }

    public function patch(string $pattern, mixed $action): self
    {
        return $this->add('PATCH', $pattern, $action);
    }

    public function delete(string $pattern, mixed $action): self
    {
        return $this->add('DELETE', $pattern, $action);
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    /** সর্বশেষ যোগ করা রুটকে নাম দেয়:  $router->get(...)->name('agent.tickets'); */
    public function name(string $name): self
    {
        $method = array_key_last($this->routes);
        if ($method === null) {
            return $this;
        }
        $index = array_key_last($this->routes[$method]);
        $this->routes[$method][$index]['name'] = $name;
        $this->namedRoutes[$name] = $this->routes[$method][$index]['pattern'];

        return $this;
    }

    public function urlFor(string $name, array $params = []): string
    {
        $pattern = $this->namedRoutes[$name] ?? '/';
        foreach ($params as $key => $value) {
            $pattern = str_replace('{' . $key . '}', rawurlencode((string) $value), $pattern);
        }

        return $pattern;
    }

    private function add(string $method, string $pattern, mixed $action): self
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
            $middleware = array_merge($middleware, (array) ($group['middleware'] ?? []));
        }

        $pattern = '/' . trim($prefix . $pattern, '/');
        if ($pattern === '/') {
            $pattern = '/';
        }

        $params = [];
        $regex = preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];

                return '([^/]+)';
            },
            $pattern
        );

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#u',
            'params'     => $params,
            'action'     => $action,
            'middleware' => $middleware,
            'name'       => null,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            array_shift($matches);
            $request->setRouteParams(array_combine($route['params'], array_map('rawurldecode', $matches)) ?: []);

            return $this->runWithMiddleware($route, $request);
        }

        // পাথ মেলে কিন্তু মেথড মেলে না → 405, যাতে ডিবাগ করা সহজ হয়
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path)) {
                    throw new HttpException(405, 'এই ঠিকানায় ' . $method . ' মেথড সমর্থিত নয়।');
                }
            }
        }

        throw new HttpException(404, 'পৃষ্ঠাটি খুঁজে পাওয়া যায়নি।');
    }

    private function runWithMiddleware(array $route, Request $request): Response
    {
        // পাইপলাইন ভেতর থেকে বাইরে গড়ি, তাই প্রথম middleware সবার আগে চলে
        $destination = fn (Request $req): Response => $this->callAction($route['action'], $req);

        foreach (array_reverse($route['middleware']) as $middleware) {
            $next = $destination;
            $destination = static function (Request $req) use ($middleware, $next): Response {
                $instance = is_string($middleware) ? new $middleware() : $middleware;

                return $instance->handle($req, $next);
            };
        }

        return $destination($request);
    }

    private function callAction(mixed $action, Request $request): Response
    {
        if (is_callable($action)) {
            return $action($request);
        }

        [$class, $method] = $action;
        $controller = new $class();

        return $controller->{$method}($request);
    }
}

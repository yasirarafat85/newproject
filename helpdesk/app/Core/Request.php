<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $routeParams = [];

    public function __construct(array $query, array $body, array $files, array $server)
    {
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
    }

    public static function capture(): self
    {
        $body = $_POST;

        // JSON বডি (API ও fetch কল) — form-encoded না হলে ডিকোড করি
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self($_GET, $body, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        // HTML ফর্ম শুধু GET/POST পাঠাতে পারে — _method দিয়ে PUT/DELETE নকল করা হয়
        if ($method === 'POST' && isset($this->body['_method'])) {
            $override = strtoupper((string) $this->body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // সাব-ডিরেক্টরিতে বসানো থাকলে (htdocs/helpdesk/public) স্ক্রিপ্টের বেস বাদ দিই
        $base = rtrim(str_replace('\\', '/', dirname($this->server['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return '/' . trim($path, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key): bool
    {
        return in_array($this->input($key), ['1', 'true', 'on', 'yes', true, 1], true);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(string ...$keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }

        return $result;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    /** একাধিক ফাইলের `name[]` ইনপুটকে প্রতি-ফাইল অ্যারেতে রূপান্তর করে। */
    public function fileList(string $key): array
    {
        $file = $this->files[$key] ?? null;
        if ($file === null || !isset($file['name'])) {
            return [];
        }

        if (!is_array($file['name'])) {
            return $file['error'] === UPLOAD_ERR_NO_FILE ? [] : [$file];
        }

        $list = [];
        foreach (array_keys($file['name']) as $index) {
            if (($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name'     => $file['name'][$index],
                'type'     => $file['type'][$index],
                'tmp_name' => $file['tmp_name'][$index],
                'error'    => $file['error'][$index],
                'size'     => $file['size'][$index],
            ];
        }

        return $list;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? $default;
    }

    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '') ?? '';

        return str_contains($accept, 'application/json')
            || $this->header('X-Requested-With') === 'XMLHttpRequest'
            || str_starts_with($this->path(), '/api/');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * URL পথ থেকে আসা প্যারামিটার (যেমน /agent/tickets/{id})।
     *
     * ইচ্ছে করেই input()/query() থেকে আলাদা রাখা হয়েছে: query string
     * দিয়ে যাতে কেউ `?id=...` পাঠিয়ে পথের আসল আইডি ঢেকে দিতে না পারে।
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function paramInt(string $key, int $default = 0): int
    {
        $value = $this->routeParams[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function paramString(string $key, string $default = ''): string
    {
        $value = $this->routeParams[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    public function params(): array
    {
        return $this->routeParams;
    }
}

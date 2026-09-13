<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private int $status = 200;
    private array $headers = [];
    private string $content = '';
    private ?string $filePath = null;

    public static function make(string $content, int $status = 200): self
    {
        $response = new self();
        $response->content = $content;
        $response->status = $status;
        $response->headers['Content-Type'] = 'text/html; charset=UTF-8';

        return $response;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $response = new self();
        $response->content = (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->status = $status;
        $response->headers['Content-Type'] = 'application/json; charset=UTF-8';

        return $response;
    }

    /** API-র সব রেসপন্স একই খামে: { data, meta, error }. */
    public static function apiSuccess(mixed $data, array $meta = [], int $status = 200): self
    {
        return self::json(['data' => $data, 'meta' => $meta, 'error' => null], $status);
    }

    public static function apiError(string $message, int $status = 400, array $details = []): self
    {
        return self::json([
            'data'  => null,
            'meta'  => [],
            'error' => ['message' => $message, 'details' => $details],
        ], $status);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = new self();
        $response->status = $status;
        $response->headers['Location'] = $url;

        return $response;
    }

    public static function download(string $path, string $filename, string $mime): self
    {
        $response = new self();
        $response->filePath = $path;
        $response->headers = [
            // inline নয় — ব্রাউজার যেন আপলোড করা HTML/SVG আমাদের অরিজিনে না চালায়
            'Content-Type'           => $mime,
            'Content-Disposition'    => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length'         => (string) filesize($path),
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $response;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        if ($this->filePath !== null) {
            readfile($this->filePath);

            return;
        }

        echo $this->content;
    }
}

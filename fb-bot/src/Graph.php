<?php

declare(strict_types=1);

namespace App;

/**
 * Small Facebook Graph API client over cURL. Every call is logged
 * (with tokens scrubbed by the Logger).
 */
final class Graph
{
    private const BASE_URL = 'https://graph.facebook.com/';
    private const TIMEOUT_SECONDS = 20;

    public static function version(): string
    {
        return Env::get('GRAPH_API_VERSION', 'v26.0');
    }

    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed> decoded JSON
     */
    public static function get(string $path, array $params = [], ?string $token = null): array
    {
        return self::request('GET', $path, $params, $token);
    }

    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed> decoded JSON
     */
    public static function post(string $path, array $params = [], ?string $token = null): array
    {
        return self::request('POST', $path, $params, $token);
    }

    /**
     * @param array<string, scalar> $params
     * @return array<string, mixed>
     * @throws GraphException on HTTP or API errors
     */
    private static function request(string $method, string $path, array $params, ?string $token): array
    {
        // null = use the Page token from .env; '' = send no token
        $token ??= Env::get('PAGE_ACCESS_TOKEN');
        if ($token !== '') {
            $params['access_token'] = $token;
        }
        $url = self::BASE_URL . self::version() . '/' . ltrim($path, '/');

        $ch = curl_init();
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        if ($method === 'GET') {
            $options[CURLOPT_URL] = $url . '?' . http_build_query($params);
        } else {
            $options[CURLOPT_URL] = $url;
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $logContext = ['method' => $method, 'path' => $path, 'status' => $status];

        if ($body === false) {
            Logger::error('graph', "Graph request failed: $curlError", $logContext);
            throw new GraphException("Network error: $curlError", 0);
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            Logger::error('graph', 'Graph returned non-JSON response', $logContext + ['body' => mb_substr((string) $body, 0, 300)]);
            throw new GraphException('Invalid response from Facebook', $status);
        }

        if ($status >= 400 || isset($data['error'])) {
            $error = $data['error'] ?? [];
            $message = (string) ($error['message'] ?? 'Unknown error');
            Logger::error('graph', "Graph error: $message", $logContext + [
                'code' => $error['code'] ?? null,
                'subcode' => $error['error_subcode'] ?? null,
                'fbtrace_id' => $error['fbtrace_id'] ?? null,
            ]);
            throw new GraphException($message, $status, (int) ($error['code'] ?? 0));
        }

        Logger::info('graph', "$method $path", $logContext);
        return $data;
    }
}

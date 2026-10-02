<?php

declare(strict_types=1);

namespace App;

/**
 * The Claude models the admin panel offers, with prices used for cost
 * estimates (USD per million tokens, Anthropic first-party rates,
 * checked 2026-09-25). Update here when prices change.
 */
final class AiModels
{
    public const DEFAULT_MODEL = 'claude-opus-5-5';
    public const EFFORTS = ['low', 'medium', 'high'];

    /**
     * @return array<string, array{label: string, note: string, input: float, output: float, cache_read: float, cache_write: float, effort: bool, fallbacks: bool}>
     */
    public static function all(): array
    {
        return [
            'claude-opus-5-5' => [
                'label' => 'Claude Opus 5.5',
                'note' => 'সবচেয়ে ভালো মান · $4 / $20',
                'input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00,
                'effort' => true, 'fallbacks' => true,
            ],
            'claude-sonnet-5-5' => [
                'label' => 'Claude Sonnet 5.5',
                'note' => 'ভালো মান, অর্ধেক দাম · $2 / $10',
                'input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50,
                'effort' => true, 'fallbacks' => true,
            ],
            'claude-haiku-4-5' => [
                'label' => 'Claude Haiku 4.5',
                'note' => 'সবচেয়ে সস্তা ও দ্রুত · $1 / $5',
                'input' => 1.00, 'output' => 5.00, 'cache_read' => 0.10, 'cache_write' => 1.25,
                'effort' => false, 'fallbacks' => false,
            ],
        ];
    }

    /** @return array{label: string, note: string, input: float, output: float, cache_read: float, cache_write: float, effort: bool, fallbacks: bool}|null */
    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function label(?string $id): string
    {
        return $id === null ? '—' : (self::get($id)['label'] ?? $id);
    }

    /** Estimated cost in USD millionths. Unknown models are priced as the default model. */
    public static function costMicros(string $model, AiResponse $r): int
    {
        $p = self::get($model) ?? self::get(self::DEFAULT_MODEL);
        $usd = ($r->inputTokens * $p['input']
            + $r->outputTokens * $p['output']
            + $r->cacheReadTokens * $p['cache_read']
            + $r->cacheWriteTokens * $p['cache_write']) / 1_000_000;
        return (int) round($usd * 1_000_000);
    }

    public static function formatCost(int $micros): string
    {
        $usd = $micros / 1_000_000;
        // Single replies cost fractions of a cent, so show more decimals below $1.
        return '$' . number_format($usd, $usd > 0 && $usd < 1 ? 4 : 2);
    }
}

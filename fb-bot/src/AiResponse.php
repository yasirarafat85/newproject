<?php

declare(strict_types=1);

namespace App;

/** Provider-neutral result of one AI call. */
final class AiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $stopReason,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWriteTokens = 0,
    ) {
    }
}

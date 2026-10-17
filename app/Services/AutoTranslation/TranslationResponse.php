<?php

namespace App\Services\AutoTranslation;

/** Результат одного запиту до Claude API: текст відповіді та використання токенів. */
final class TranslationResponse
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $stopReason,
        public readonly string $servedModel,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWriteTokens = 0,
        public readonly ?string $refusal = null,
    ) {
    }
}

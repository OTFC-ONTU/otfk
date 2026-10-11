<?php

namespace App\Services\AutoTranslation;

use Anthropic\Client;
use Anthropic\Models\ModelInfo;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;

/**
 * Тонка обгортка офіційного Anthropic PHP SDK для автоперекладу: список моделей
 * і один запит перекладу зі структурованою JSON-відповіддю.
 *
 * HTTP-транспорт — Guzzle з довгим тайм-аутом (сторінки перекладаються хвилинами);
 * у тестах його підміняють через контейнер (ключ self::HTTP_BINDING) — так тести
 * проходять реальний код SDK без мережі.
 */
class ClaudeClient
{
    public const HTTP_BINDING = 'auto-translate.http';

    private Client $client;

    public function __construct(string $apiKey)
    {
        $this->client = new Client(
            apiKey: $apiKey,
            // Явна адреса: змінні середовища ANTHROPIC_* хостингу не мають підміняти ключ чи хост.
            baseUrl: 'https://api.anthropic.com',
            requestOptions: [
                'transporter' => self::http(),
                'maxRetries' => 2,
                'timeout' => (float) config('auto_translate.timeout', 600),
            ],
        );
    }

    private static function http(): ClientInterface
    {
        if (app()->bound(self::HTTP_BINDING)) {
            return app(self::HTTP_BINDING);
        }

        return new GuzzleClient([
            'timeout' => (int) config('auto_translate.timeout', 600),
            'connect_timeout' => 15,
            'http_errors' => false,
        ]);
    }

    /**
     * Моделі, доступні за ключем (Models API, усі сторінки).
     *
     * @return list<array{id: string, display_name: string, max_tokens: ?int, supports_effort: ?bool, supports_structured_outputs: ?bool, released_at: ?\DateTimeInterface}>
     */
    public function listModels(): array
    {
        $models = [];
        foreach ($this->client->models->list(limit: 100)->pagingEachItem() as $model) {
            /** @var ModelInfo $model */
            $capabilities = $model->capabilities ?? null;
            $models[] = [
                'id' => $model->id,
                'display_name' => $model->displayName,
                'max_tokens' => $model->maxTokens ?? null,
                'supports_effort' => $capabilities ? (bool) ($capabilities->effort->low->supported ?? false) : null,
                'supports_structured_outputs' => $capabilities ? (bool) $capabilities->structuredOutputs->supported : null,
                'released_at' => $model->createdAt ?? null,
            ];
        }

        return $models;
    }

    /**
     * Один запит перекладу. Відповідь — JSON за схемою $schema (structured outputs).
     * Для моделей із серверним запасним варіантом вмикається fallbacks: "default" —
     * при відмові з міркувань безпеки той самий запит виконує інша модель.
     */
    public function translate(string $model, string $system, string $prompt, array $schema, int $maxTokens, bool $lowEffort): TranslationResponse
    {
        $outputConfig = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        if ($lowEffort) {
            // Переклад не потребує глибоких міркувань: менше токенів «мислення» — дешевше.
            $outputConfig['effort'] = 'low';
        }

        $messages = [['role' => 'user', 'content' => $prompt]];

        $message = in_array($model, (array) config('auto_translate.fallback_capable', []), true)
            ? $this->client->beta->messages->create(
                maxTokens: $maxTokens,
                messages: $messages,
                model: $model,
                system: $system,
                outputConfig: $outputConfig,
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            )
            : $this->client->messages->create(
                maxTokens: $maxTokens,
                messages: $messages,
                model: $model,
                system: $system,
                outputConfig: $outputConfig,
            );

        // Після блоку fallback текст пише запасна модель — беремо лише його.
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'fallback') {
                $text = '';
            } elseif ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return new TranslationResponse(
            text: $text,
            stopReason: $message->stopReason,
            servedModel: $message->model,
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
            cacheReadTokens: (int) ($message->usage->cacheReadInputTokens ?? 0),
            cacheWriteTokens: (int) ($message->usage->cacheCreationInputTokens ?? 0),
            refusal: $message->stopReason === 'refusal' ? ($message->stopDetails->explanation ?? null) : null,
        );
    }
}

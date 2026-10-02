<?php

declare(strict_types=1);

namespace App;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Psr\Http\Client\ClientInterface;

/**
 * Claude via the official Anthropic PHP SDK (composer: anthropic-ai/sdk).
 */
final class AnthropicAiClient implements AiClient
{
    private const MAX_TOKENS = 4000; // includes thinking; replies themselves are short
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private Client $client;

    /** @param ClientInterface|null $transporter PSR-18 HTTP client; tests pass a fake */
    public function __construct(string $apiKey, private readonly ?ClientInterface $transporter = null)
    {
        $this->client = new Client(apiKey: $apiKey);
    }

    public static function fromEnv(): self
    {
        if (!class_exists(Client::class)) {
            throw new AiException('Anthropic SDK not installed. Run: composer install --no-dev', false);
        }
        $key = Env::get('AI_API_KEY');
        if ($key === '') {
            throw new AiException('AI_API_KEY is empty in .env', false);
        }
        return new self($key);
    }

    public function complete(string $system, string $user, array $schema, string $model, ?string $effort): AiResponse
    {
        $info = AiModels::get($model);
        $outputConfig = ['format' => ['type' => 'json_schema', 'schema' => $schema]];
        if ($effort !== null && ($info['effort'] ?? false)) {
            $outputConfig['effort'] = $effort;
        }
        // Server-side fallback: if the model declines for policy reasons, the API
        // retries on its default fallback model inside the same call.
        $useFallbacks = $info['fallbacks'] ?? false;

        try {
            $message = $this->client->beta->messages->create(
                maxTokens: self::MAX_TOKENS,
                messages: [['role' => 'user', 'content' => $user]],
                model: $model,
                system: [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
                outputConfig: $outputConfig,
                fallbacks: $useFallbacks ? 'default' : null,
                betas: $useFallbacks ? [self::FALLBACK_BETA] : null,
                requestOptions: [
                    'transporter' => $this->transporter ?? new \GuzzleHttp\Client(['timeout' => 90, 'connect_timeout' => 10]),
                    'maxRetries' => 2,
                ],
            );
        } catch (AuthenticationException | PermissionDeniedException $e) {
            throw new AiException('API key rejected: ' . $e->getMessage(), false, $e);
        } catch (RateLimitException $e) {
            throw new AiException('Rate limited: ' . $e->getMessage(), true, $e);
        } catch (BadRequestException $e) {
            throw new AiException('Bad request: ' . $e->getMessage(), false, $e);
        } catch (APIStatusException $e) {
            throw new AiException('API error ' . $e->status . ': ' . $e->getMessage(), ($e->status ?? 0) >= 500, $e);
        } catch (APIConnectionException $e) {
            throw new AiException('Connection error: ' . $e->getMessage(), true, $e);
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $usage = $message->usage;
        return new AiResponse(
            text: $text,
            stopReason: $message->stopReason,
            model: $message->model,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadInputTokens ?? 0,
            cacheWriteTokens: $usage->cacheCreationInputTokens ?? 0,
        );
    }
}

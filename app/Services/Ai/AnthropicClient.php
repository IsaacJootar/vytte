<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A thin client over the Anthropic Messages API.
 *
 * Deliberately small: one method, one endpoint. It knows how to send a system + user prompt
 * and return the text back. Everything about what to say and how to guard it lives in the
 * services above; this only carries the request.
 */
class AnthropicClient
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $version,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            apiKey: config('services.anthropic.api_key'),
            model: (string) config('services.anthropic.model', 'claude-sonnet-4-5'),
            version: (string) config('services.anthropic.version', '2023-06-01'),
            baseUrl: rtrim((string) config('services.anthropic.base_url', 'https://api.anthropic.com'), '/'),
        );
    }

    /**
     * Whether the integration is configured at all. When false, callers degrade gracefully
     * rather than erroring — the AI narrative is optional, the report is not.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Send a single-turn message and return the assistant's text.
     *
     * @throws RuntimeException on transport or API failure — callers must catch and degrade.
     */
    public function message(string $system, string $user, int $maxTokens = 1024): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('The Anthropic API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])
            ->timeout(30)
            ->post($this->baseUrl.'/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API request failed with status '.$response->status().'.');
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if (trim($text) === '') {
            throw new RuntimeException('Anthropic API returned no text.');
        }

        return trim($text);
    }

    public function model(): string
    {
        return $this->model;
    }
}

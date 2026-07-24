<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * A single-turn chat client, provider-agnostic.
 *
 * The narrative layer depends on this contract, not on any one vendor, so the AI provider
 * can be swapped in the container without touching the reporting engine.
 */
interface AiChatClient
{
    /**
     * Whether the integration is configured (has an API key). When false, callers degrade
     * gracefully — the AI narrative is optional, the report is not.
     */
    public function isConfigured(): bool;

    /**
     * Send a system + user prompt and return the assistant's text.
     *
     * @throws RuntimeException on transport or API failure — callers must catch and degrade.
     */
    public function message(string $system, string $user, int $maxTokens = 1024): string;

    /**
     * The model identifier, stored alongside generated narratives.
     */
    public function model(): string;
}

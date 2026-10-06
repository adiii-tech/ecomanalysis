<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Anthropic\Client;
use App\Models\AiSetting;
use App\Support\TenantContext;
use RuntimeException;

/**
 * Thin wrapper over the Anthropic SDK so the rest of the app can ask whether
 * AI is available without catching exceptions, and so the model, effort and
 * token ceilings live in config rather than scattered through call sites.
 *
 * A tenant may bring their own Anthropic key (see AiSetting); when they have,
 * it and their model override take priority over the app-wide config, which
 * remains the fallback for tenants who haven't set one.
 */
class ClaudeClient
{
    private ?Client $client = null;

    private bool $settingLoaded = false;

    private ?AiSetting $setting = null;

    public function __construct(private readonly TenantContext $context) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function client(): Client
    {
        $apiKey = $this->apiKey();

        if (blank($apiKey)) {
            throw new RuntimeException('No Anthropic API key configured.');
        }

        return $this->client ??= new Client(apiKey: $apiKey);
    }

    public function model(): string
    {
        return $this->tenantSetting()?->model ?: (string) config('ai.model', 'claude-opus-5');
    }

    private function apiKey(): ?string
    {
        $tenantKey = $this->tenantSetting()?->api_key;

        return filled($tenantKey) ? $tenantKey : config('ai.api_key');
    }

    /**
     * Guarded by TenantContext::has() because AiSetting's tenant scope
     * silently returns unfiltered rows when no tenant is resolved (console,
     * queue workers outside a tenant run) — querying unguarded here could
     * hand one tenant's key to another's request.
     */
    private function tenantSetting(): ?AiSetting
    {
        if (! $this->settingLoaded) {
            $this->setting = $this->context->has() ? AiSetting::query()->first() : null;
            $this->settingLoaded = true;
        }

        return $this->setting;
    }

    public function maxTokens(string $purpose): int
    {
        return (int) config("ai.max_tokens.{$purpose}", 2048);
    }

    public function effort(string $purpose): string
    {
        return (string) config("ai.effort.{$purpose}", 'medium');
    }

    /**
     * Concatenates the text blocks of a response, skipping thinking and
     * tool-use blocks.
     *
     * @param  iterable<object>  $content
     */
    public function textOf(iterable $content): string
    {
        $text = '';

        foreach ($content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}

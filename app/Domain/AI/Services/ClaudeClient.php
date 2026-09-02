<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Anthropic\Client;
use RuntimeException;

/**
 * Thin wrapper over the Anthropic SDK so the rest of the app can ask whether
 * AI is available without catching exceptions, and so the model, effort and
 * token ceilings live in config rather than scattered through call sites.
 */
class ClaudeClient
{
    private ?Client $client = null;

    public function isConfigured(): bool
    {
        return filled(config('ai.api_key'));
    }

    public function client(): Client
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('No Anthropic API key configured.');
        }

        return $this->client ??= new Client(apiKey: (string) config('ai.api_key'));
    }

    public function model(): string
    {
        return (string) config('ai.model', 'claude-opus-5');
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

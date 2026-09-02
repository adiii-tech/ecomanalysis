<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Models\AiInsightCache;
use App\Support\Facades\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The ✨ button on every widget: two or three sentences explaining what the
 * chart actually says.
 *
 * Cached per (widget, date range, tenant) for 24 hours — the underlying numbers
 * only change when a sync lands, and paying for the same sentence on every page
 * load would be absurd.
 */
class InsightWriter
{
    public function __construct(private readonly ClaudeClient $claude) {}

    /**
     * @param  array<string, mixed>  $payload  the exact data the widget rendered
     */
    public function forWidget(string $widgetKey, string $title, array $payload, string $cacheKey): string
    {
        $cached = AiInsightCache::query()
            ->where('cache_key', $cacheKey)
            ->where('expires_at', '>', now())
            ->first();

        if ($cached !== null) {
            return $cached->content;
        }

        $content = $this->generate($title, $payload);

        AiInsightCache::query()->updateOrCreate(
            ['tenant_id' => Tenant::id(), 'cache_key' => $cacheKey],
            [
                'widget_key' => $widgetKey,
                'content' => $content,
                'payload_digest' => ['hash' => md5(json_encode($payload) ?: '')],
                'expires_at' => now()->addHours((int) config('ai.insight_ttl_hours', 24)),
            ],
        );

        return $content;
    }

    public function forget(string $cacheKey): void
    {
        AiInsightCache::query()->where('cache_key', $cacheKey)->delete();
    }

    /** @param array<string, mixed> $payload */
    private function generate(string $title, array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $response = $this->claude->client()->messages->create(
            model: $this->claude->model(),
            maxTokens: $this->claude->maxTokens('insight'),
            system: <<<'PROMPT'
            You explain one chart from an Indian D2C analytics dashboard to the brand's owner.

            Write two or three sentences. Say what the data actually shows, then what it means for the business, then what to do if there is something worth doing. No preamble, no restating the chart title, no bullet points, no markdown.

            Only use numbers present in the data given to you. Money is Indian rupees written like ₹1,23,456. Percentages to one decimal place. If the data is too thin to say anything useful, say exactly that in one sentence instead of padding.
            PROMPT,
            messages: [[
                'role' => 'user',
                'content' => "Widget: {$title}\n\nData it is rendering:\n{$json}",
            ]],
        );

        return $this->claude->textOf($response->content);
    }

    /** @param array<string, mixed> $payload */
    public function safely(string $widgetKey, string $title, array $payload, string $cacheKey): ?string
    {
        try {
            return $this->forWidget($widgetKey, $title, $payload, $cacheKey);
        } catch (Throwable $e) {
            Log::warning('Chart insight failed', ['widget' => $widgetKey, 'error' => $e->getMessage()]);

            return null;
        }
    }
}

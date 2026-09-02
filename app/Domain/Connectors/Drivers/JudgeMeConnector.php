<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Drivers;

use App\Domain\Connectors\DTOs\HealthResult;
use App\Domain\Connectors\DTOs\SyncContext;
use App\Domain\Connectors\DTOs\SyncReport;
use App\Domain\Connectors\Support\AbstractConnector;
use App\Enums\AuthType;
use App\Models\Sku;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/**
 * Judge.me reviews. Sentiment is deliberately left NULL here — it is filled in
 * later by the LLM analysis job, never guessed from the star rating.
 */
class JudgeMeConnector extends AbstractConnector
{
    public function id(): string
    {
        return 'judgeme';
    }

    public function label(): string
    {
        return 'Judge.me';
    }

    public function summary(): string
    {
        return 'Product reviews, ratings, reviewer details, photo reviews and product mapping.';
    }

    public function authType(): AuthType
    {
        return AuthType::Token;
    }

    /** @return array<string, array{label: string, type: string, required: bool, help?: string}> */
    public function credentialFields(): array
    {
        return [
            'shop_domain' => ['label' => 'Shop domain', 'type' => 'text', 'required' => true, 'help' => 'yourbrand.myshopify.com'],
            'api_token' => ['label' => 'Judge.me private API token', 'type' => 'password', 'required' => true],
        ];
    }

    /** @return list<string> */
    public function syncableEntities(): array
    {
        return ['reviews'];
    }

    /** @return array<string, int> */
    public function syncCadence(): array
    {
        return ['reviews' => 360];
    }

    public function testConnection(): HealthResult
    {
        try {
            $response = $this->client()->get('reviews/count');
        } catch (Throwable $e) {
            return HealthResult::fail('Judge.me unreachable: '.$e->getMessage());
        }

        return $response->successful()
            ? HealthResult::ok('Judge.me responding.', ['reviews' => $response->json('count')])
            : HealthResult::fail('Judge.me returned HTTP '.$response->status().'.');
    }

    protected function syncReviews(SyncContext $ctx): SyncReport
    {
        $skus = Sku::query()->where('tenant_id', $ctx->tenant->id)->pluck('id', 'sku_code');
        $page = 1;
        $fetched = 0;
        $rows = [];

        do {
            $response = $this->client()->get('reviews', ['page' => $page, 'per_page' => 100]);

            if ($response->failed()) {
                return SyncReport::failed('reviews', 'Judge.me returned HTTP '.$response->status().'.');
            }

            $reviews = $response->json('reviews', []);

            foreach ($reviews as $review) {
                $fetched++;
                $sku = $review['product_external_id'] ?? null;

                $rows[] = [
                    'tenant_id' => $ctx->tenant->id,
                    'source' => 'judgeme',
                    'external_id' => (string) $review['id'],
                    'sku_id' => $skus[$review['product_handle'] ?? ''] ?? null,
                    'product_sku' => $sku !== null ? (string) $sku : ($review['product_title'] ?? null),
                    'rating' => (int) ($review['rating'] ?? 0),
                    'title' => $review['title'] ? mb_substr((string) $review['title'], 0, 190) : null,
                    'body' => $review['body'] ?? null,
                    'reviewer' => $review['reviewer']['name'] ?? null,
                    'verified' => (bool) ($review['verified'] ?? false),
                    'has_photos' => ! empty($review['pictures']),
                    'reviewed_at' => $review['created_at'],
                    // sentiment stays NULL until the LLM analysis job runs.
                ];
            }

            $page++;
        } while (count($reviews) === 100 && $page < 200);

        $upserted = $this->upsert('reviews', $rows, ['tenant_id', 'source', 'external_id'],
            ['rating', 'title', 'body', 'reviewer', 'verified', 'has_photos', 'sku_id', 'product_sku', 'updated_at']);

        return SyncReport::of('reviews', $fetched, $upserted, now()->toIso8601String());
    }

    private function client(): PendingRequest
    {
        return $this->http('https://judge.me/api/v1/')
            ->withQueryParameters([
                'api_token' => (string) $this->credential('api_token'),
                'shop_domain' => (string) $this->credential('shop_domain'),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Connectors\Jobs;

use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Domain\Sales\Actions\UpsertShopifyOrder;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessShopifyWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $webhookEventId)
    {
        $this->onQueue('webhooks');
    }

    public function handle(TenantContext $context, UpsertShopifyOrder $upsert): void
    {
        $event = $context->withoutScope(fn (): ?WebhookEvent => WebhookEvent::query()->find($this->webhookEventId));

        if ($event === null || $event->status === 'processed') {
            return;
        }

        $tenant = Tenant::query()->findOrFail($event->tenant_id);

        $context->runAs($tenant, function () use ($event, $tenant, $upsert): void {
            try {
                match ($event->topic) {
                    // A refunds/create body is the refund, not the order, so upserting it would invent an
                    // order. Shopify follows it with orders/updated, which carries the refund on the real order.
                    'orders/create', 'orders/updated', 'orders/cancelled' => $upsert->handle($tenant, $event->payload),
                    default => null,
                };

                $event->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();

                RebuildRollups::dispatch($tenant->id)->onQueue('rollups');
            } catch (Throwable $e) {
                $event->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();

                throw $e;
            }
        });
    }
}

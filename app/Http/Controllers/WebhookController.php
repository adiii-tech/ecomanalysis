<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Connectors\Jobs\ProcessShopifyWebhook;
use App\Models\Connector;
use App\Models\WebhookEvent;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shopify webhook receiver. Orders arrive here within seconds instead of
 * waiting for the next scheduled sync.
 *
 * The payload is verified against the shop's HMAC secret, recorded, then
 * handed to a queued job — Shopify retries anything that does not answer
 * quickly, so no parsing happens in the request.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function shopify(Request $request): JsonResponse
    {
        $topic = (string) $request->header('X-Shopify-Topic', '');
        $domain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $signature = (string) $request->header('X-Shopify-Hmac-Sha256', '');

        $connector = $this->context->withoutScope(fn (): ?Connector => Connector::query()
            ->where('connector_id', 'shopify')
            ->get()
            ->first(fn (Connector $row): bool => data_get($row->credentials, 'shop_domain') === $domain));

        if ($connector === null) {
            return response()->json(['message' => 'Unknown shop.'], 404);
        }

        $secret = data_get($connector->credentials, 'webhook_secret');

        if (blank($secret) || ! $this->signatureIsValid($request->getContent(), $signature, (string) $secret)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $this->context->withoutScope(fn (): WebhookEvent => WebhookEvent::query()->create([
            'tenant_id' => $connector->tenant_id,
            'connector_id' => 'shopify',
            'topic' => $topic,
            'external_id' => (string) $request->input('id', ''),
            'payload' => $request->all(),
            'status' => 'pending',
        ]));

        ProcessShopifyWebhook::dispatch($event->id);

        // Answer immediately; Shopify treats a slow response as a failure and retries.
        return response()->json(['received' => true]);
    }

    private function signatureIsValid(string $body, string $signature, string $secret): bool
    {
        return hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\Drivers\ShopifyConnector;
use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Domain\Sales\Actions\ComputeOrderEconomics;
use App\Domain\Sales\Support\PaymentModeResolver;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Re-reads how each Shopify order was paid for and corrects the stored mode.
 *
 * The old rule only recognised "cash on delivery" and "cod", so a checkout
 * reporting `cash_on_delivery` landed in prepaid — taking the COD charge, the
 * gateway fee and every RTO number with it. Only the payment fields are
 * re-read, so a whole store takes minutes instead of a full re-sync.
 */
class RefreshShopifyPaymentModes extends Command
{
    protected $signature = 'shopify:refresh-payment-modes
        {tenant : Tenant id or slug}
        {--since= : Only orders created on or after this date (YYYY-MM-DD)}';

    protected $description = 'Re-read Shopify payment gateways and correct the payment mode on orders already synced.';

    public function handle(TenantContext $context, ConnectorRegistry $registry, ComputeOrderEconomics $economics): int
    {
        $key = (string) $this->argument('tenant');

        $tenant = $context->withoutScope(fn (): ?Tenant => Tenant::query()
            ->when(
                ctype_digit($key),
                fn ($query) => $query->whereKey((int) $key),
                fn ($query) => $query->where('slug', $key),
            )
            ->first());

        if ($tenant === null) {
            $this->error("No tenant matches [{$key}].");

            return self::FAILURE;
        }

        return $context->runAs($tenant, function () use ($tenant, $registry, $economics): int {
            $driver = $registry->forTenant('shopify');

            if (! $driver instanceof ShopifyConnector) {
                $this->error("Shopify is not connected for {$tenant->name}.");

                return self::FAILURE;
            }

            $since = filled($this->option('since'))
                ? CarbonImmutable::parse((string) $this->option('since'), $tenant->timezone)
                : null;

            $stored = Order::query()
                ->where('source', 'shopify')
                ->get(['id', 'external_id', 'payment_mode', 'payment_gateway'])
                ->keyBy('external_id');

            $scanned = 0;
            $corrected = 0;

            foreach ($driver->paymentGateways($since) as $row) {
                $scanned++;
                $current = $stored->get($row['id']);

                if ($current === null) {
                    continue;
                }

                $mode = PaymentModeResolver::fromGateways($row['gateways']);
                $label = PaymentModeResolver::label($row['gateways']);

                if ($current->payment_mode === $mode && $current->payment_gateway === $label) {
                    continue;
                }

                $order = Order::query()->findOrFail($current->id);
                $order->forceFill(['payment_mode' => $mode, 'payment_gateway' => $label])->save();

                // COD carries its own charge and skips the gateway fee, so the margin has to resolve again.
                $economics->handle($order);
                $corrected++;
            }

            RebuildRollups::dispatch($tenant->id, full: true);

            $this->info(sprintf(
                'Checked %s orders from Shopify, corrected %s. A full rollup rebuild is queued.',
                number_format($scanned),
                number_format($corrected),
            ));

            return self::SUCCESS;
        });
    }
}

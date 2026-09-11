<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Connector;
use App\Models\Tenant;
use App\Support\MetricCache;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Empties a tenant of its business data so a real store can sync into a clean
 * account — typically the seeded demo tenant once Shopify is connected.
 *
 * Everything a sync or a rollup would produce is deleted. What a person set up
 * is kept: users and roles, connectors (so nothing needs re-authorising), cost
 * settings, targets, alert rules, schedules and saved views. Connector cursors
 * are reset so the next sync backfills from scratch rather than resuming from
 * where the old data left off.
 */
class ClearTenantData extends Command
{
    /**
     * Deleted, children before their parents.
     *
     * @var list<string>
     */
    public const DATA_TABLES = [
        'order_discounts', 'order_items', 'marketplace_fees', 'returns', 'shipments', 'transactions', 'settlements', 'orders',
        'abandoned_checkouts', 'discount_codes', 'customers',
        'inventory', 'stock_movements', 'stock_count_items', 'stock_counts', 'stock_batches',
        'purchase_order_items', 'purchase_orders', 'sku_components', 'sku_cost_history', 'reviews', 'skus', 'products',
        'suppliers', 'locations', 'channels',
        'ad_insights_daily', 'ad_creatives', 'ads', 'ad_sets', 'campaigns', 'ad_accounts',
        'analytics_daily', 'analytics_cities', 'analytics_demographics', 'analytics_pages', 'analytics_products', 'analytics_realtime',
        'social_posts', 'social_account_daily', 'social_audience_daily', 'social_accounts',
        'daily_metrics_rollup', 'sku_daily_rollup', 'state_daily_rollup', 'ad_spend_rollup', 'cohort_snapshots', 'pincode_risk',
        'metric_anomalies', 'alert_events', 'ai_insights', 'ai_insight_cache', 'ai_chat_messages', 'ai_chat_sessions',
        'sync_runs', 'webhook_events',
    ];

    /**
     * Tenant-scoped tables that hold setup rather than data, left untouched.
     *
     * @var list<string>
     */
    public const KEPT_TABLES = [
        'users', 'roles', 'model_has_roles', 'model_has_permissions', 'invitations',
        'connectors', 'cost_settings', 'benchmarks', 'notification_settings', 'alert_rules',
        'report_schedules', 'report_favourites', 'report_shares', 'saved_views', 'customer_segments',
        'ai_usage_logs', 'activity_log',
    ];

    protected $signature = 'tenant:clear-data
        {tenant : Tenant id or slug}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete a tenant\'s orders, catalog, marketing and derived data while keeping its users, connectors and settings.';

    public function handle(TenantContext $context, MetricCache $cache): int
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
            $this->table(['ID', 'Slug', 'Name'], $context->withoutScope(
                fn (): array => Tenant::query()->orderBy('id')->get(['id', 'slug', 'name'])->toArray(),
            ));

            return self::FAILURE;
        }

        $counts = collect(self::DATA_TABLES)
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->where('tenant_id', $tenant->id)->count()])
            ->filter();

        $this->table(['Table', 'Rows'], $counts->map(fn (int $rows, string $table): array => [$table, number_format($rows)])->values()->all());

        $question = sprintf('Permanently delete %s rows for %s? Users, connectors and settings are kept.', number_format($counts->sum()), $tenant->name);

        if (! $this->option('force') && ! $this->confirm($question)) {
            $this->warn('Nothing was deleted.');

            return self::FAILURE;
        }

        $context->runAs($tenant, function () use ($tenant, $counts): void {
            DB::transaction(function () use ($tenant, $counts): void {
                foreach (self::DATA_TABLES as $table) {
                    DB::table($table)->where('tenant_id', $tenant->id)->delete();
                }

                Connector::query()->get()->each(
                    fn (Connector $connector): bool => $connector->forceFill(['sync_cursor' => [], 'last_synced_at' => null])->save(),
                );

                $tenant->forceFill(['is_demo' => false])->save();

                activity('tenant')
                    ->performedOn($tenant)
                    ->withProperties(['rows_deleted' => $counts->all()])
                    ->log('tenant.data_cleared');
            });
        });

        $cache->bust($tenant->id);

        $this->info(sprintf('Cleared %s rows from %s. Connector cursors were reset, so the next sync backfills from scratch.', number_format($counts->sum()), $tenant->name));

        return self::SUCCESS;
    }
}

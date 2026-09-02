<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

/**
 * Plan gating, expressed as Pennant features scoped to the tenant.
 *
 * The plan enum stays the single source of the numbers; Pennant is how the
 * rest of the app asks the question, so a gate reads the same in a controller,
 * a job and a Blade view.
 */
class PlanFeatureServiceProvider extends ServiceProvider
{
    /**
     * Rich-value features carry the limit itself, so a caller can both check
     * and report it: "your plan allows 3 connectors".
     */
    public function boot(): void
    {
        Feature::resolveScopeUsing(fn (): ?Tenant => app(TenantContext::class)->get());

        Feature::define('connector-limit', fn (?Tenant $tenant): int => $tenant?->plan->connectorLimit() ?? 0);
        Feature::define('seat-limit', fn (?Tenant $tenant): int => $tenant?->plan->seatLimit() ?? 1);
        Feature::define('ai-credits', fn (?Tenant $tenant): int => $tenant?->plan->aiCredits() ?? 0);
        Feature::define('history-days', fn (?Tenant $tenant): int => $tenant?->plan->historyWindowDays() ?? 30);
        Feature::define('alert-rule-limit', fn (?Tenant $tenant): int => $tenant?->plan->alertRuleLimit() ?? 0);

        // Boolean gates. Charts always render; it is the interpretation that
        // is paid for, which is the whole monetisation idea.
        Feature::define('chart-insights', fn (?Tenant $tenant): bool => $tenant?->plan->hasChartInsights() ?? false);
        Feature::define('ai-chat', fn (?Tenant $tenant): bool => ($tenant?->plan->aiCredits() ?? 0) > 0);
        Feature::define('alerts', fn (?Tenant $tenant): bool => ($tenant?->plan->alertRuleLimit() ?? 0) > 0);
        Feature::define('advanced-reports', fn (?Tenant $tenant): bool => $tenant?->plan->hasAdvancedReports() ?? false);
        Feature::define('scheduled-delivery', fn (?Tenant $tenant): bool => $tenant?->plan->hasScheduledDelivery() ?? false);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Connectors\ConnectorRegistry;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Benchmark;
use App\Models\Connector;
use App\Models\CostSetting;
use App\Models\Invitation;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\Facades\Tenant;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The setup wizard.
 *
 * Steps are marked complete from what is actually true in the database — a
 * connector that has synced, cost settings that are no longer zero — never from
 * a checkbox the user ticked. A wizard that lies about being finished is worse
 * than no wizard.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    public function show(): JsonResponse
    {
        $tenant = Tenant::current();
        $state = $tenant->onboarding_state ?? [];

        $connectors = Connector::query()->where('status', '!=', 'disconnected')->get();
        $synced = SyncRun::query()->where('status', SyncStatus::Success)->exists();
        $costs = CostSetting::query()->first();
        $benchmarks = Benchmark::query()->first();

        $steps = [
            [
                'key' => 'business',
                'title' => 'Tell us about your business',
                'summary' => 'Categories, typical order value and the channels you sell on — so the benchmarks start somewhere sensible.',
                'complete' => filled($state['business'] ?? null),
                'evidence' => filled($state['business'] ?? null) ? 'Saved.' : null,
            ],
            [
                'key' => 'connect',
                'title' => 'Connect your store',
                'summary' => 'Shopify or a marketplace. Everything downstream is built from this.',
                'complete' => $connectors->isNotEmpty(),
                'evidence' => $connectors->isEmpty()
                    ? null
                    : $connectors->map(fn (Connector $c): string => $this->registry->has($c->connector_id)
                        ? $this->registry->make($c->connector_id)->label()
                        : $c->connector_id)->implode(', '),
            ],
            [
                'key' => 'sync',
                'title' => 'Pull your first data',
                'summary' => 'The first sync backfills orders, then the rollups rebuild and the dashboard fills in.',
                'complete' => $synced,
                'evidence' => $synced ? 'At least one sync has completed.' : null,
                'blocked_by' => $connectors->isEmpty() ? 'connect' : null,
            ],
            [
                'key' => 'costs',
                'title' => 'Enter your costs',
                'summary' => 'Packaging, shipping, COD charges and gateway fees. Without these, margin is a guess.',
                'complete' => $costs !== null && $this->costsLookReal($costs),
                'evidence' => $costs === null ? null : sprintf(
                    'Packaging %s · shipping %s · gateway %.2f%%',
                    Money::format((int) $costs->packaging_cost),
                    Money::format((int) $costs->default_shipping_cost),
                    (float) $costs->gateway_fee_pct,
                ),
            ],
            [
                'key' => 'benchmarks',
                'title' => 'Set your targets',
                'summary' => 'Target margin, ROAS and SLAs. These are what every verdict in the product judges against.',
                'complete' => $benchmarks !== null && (int) $benchmarks->monthly_revenue_target > 0,
                'evidence' => $benchmarks === null || (int) $benchmarks->monthly_revenue_target === 0
                    ? null
                    : sprintf('Monthly target %s at %.0f%% margin', Money::format((int) $benchmarks->monthly_revenue_target), (float) $benchmarks->target_margin_pct),
            ],
            [
                'key' => 'team',
                'title' => 'Invite your team',
                'summary' => 'Finance, marketing and ops each see only what their role needs.',
                'complete' => User::query()->count() > 1 || Invitation::query()->whereNull('revoked_at')->exists(),
                'evidence' => sprintf('%d user(s), %d pending invite(s)', User::query()->count(), Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->count()),
                'optional' => true,
            ],
        ];

        $required = array_values(array_filter($steps, static fn (array $step): bool => ! ($step['optional'] ?? false)));
        $done = count(array_filter($required, static fn (array $step): bool => $step['complete']));

        return ApiResponse::ok([
            'steps' => $steps,
            'business' => $state['business'] ?? null,
            'progress' => [
                'done' => $done,
                'total' => count($required),
                'pct' => (int) round($done / max(1, count($required)) * 100),
            ],
            'is_demo' => $tenant->is_demo,
            'dismissed' => (bool) ($state['dismissed'] ?? false),
            'connectors' => collect($this->registry->live())->map(static fn ($driver): array => [
                'id' => $driver->id(),
                'label' => $driver->label(),
                'summary' => $driver->summary(),
            ])->values()->all(),
        ]);
    }

    public function saveBusiness(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'categories' => ['array', 'max:10'],
            'categories.*' => ['string', 'max:60'],
            'channels' => ['array', 'max:20'],
            'channels.*' => ['string', 'max:40'],
            'monthly_orders' => ['nullable', 'integer', 'min:0'],
            'average_order_value' => ['nullable', 'numeric', 'min:0'],
            'gst_state' => ['nullable', 'string', 'max:64'],
        ]);

        $tenant = Tenant::current();
        $state = $tenant->onboarding_state ?? [];
        $state['business'] = $validated;

        $tenant->forceFill([
            'onboarding_state' => $state,
            'name' => $validated['brand_name'] ?: $tenant->name,
            'gst_state' => $validated['gst_state'] ?? $tenant->gst_state,
        ])->save();

        // A stated monthly revenue is a far better starting target than zero.
        if (($validated['monthly_orders'] ?? 0) > 0 && ($validated['average_order_value'] ?? 0) > 0) {
            $target = Money::fromRupees($validated['monthly_orders'] * $validated['average_order_value']);

            Benchmark::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                ['monthly_revenue_target' => $target],
            );
        }

        activity('onboarding')->withProperties($validated)->log('onboarding.business_saved');

        return ApiResponse::ok(null, message: 'Saved.');
    }

    /**
     * Leaving the wizard is allowed at any point; the banner stays until real
     * data has actually landed, so dismissing it cannot fake progress.
     */
    public function dismiss(): JsonResponse
    {
        $tenant = Tenant::current();
        $state = $tenant->onboarding_state ?? [];
        $state['dismissed'] = true;

        $tenant->forceFill(['onboarding_state' => $state])->save();

        return ApiResponse::ok(null, message: 'You can pick this up again from the banner at any time.');
    }

    private function costsLookReal(CostSetting $costs): bool
    {
        // Every cost at zero means the defaults were never touched.
        return (int) $costs->packaging_cost > 0
            || (int) $costs->default_shipping_cost > 0
            || (int) $costs->per_order_fixed_cost > 0;
    }
}

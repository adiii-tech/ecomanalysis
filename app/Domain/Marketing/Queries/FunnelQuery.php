<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Queries;

use App\Domain\Connectors\Queries\SyncHealthQuery;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * GA4-derived funnel and channel performance. Without GA4 connected these
 * widgets return a caveat rather than a fabricated funnel.
 */
class FunnelQuery
{
    public function __construct(private readonly SyncHealthQuery $health) {}

    /** @return array<string, mixed> */
    public function conversionFunnel(WidgetFilters $filters): array
    {
        if ($this->missingGa4()) {
            return $this->ga4Missing('the conversion funnel');
        }

        $row = $this->analytics($filters)
            ->selectRaw('COALESCE(SUM(sessions),0) AS sessions, COALESCE(SUM(add_to_carts),0) AS add_to_carts')
            ->selectRaw('COALESCE(SUM(checkouts),0) AS checkouts, COALESCE(SUM(purchases),0) AS purchases')
            ->first();

        $sessions = (int) ($row->sessions ?? 0);
        $atc = (int) ($row->add_to_carts ?? 0);
        $checkouts = (int) ($row->checkouts ?? 0);
        $purchases = (int) ($row->purchases ?? 0);

        $steps = [
            ['key' => 'sessions', 'label' => 'Sessions', 'value' => $sessions, 'step_rate' => 100.0],
            ['key' => 'add_to_cart', 'label' => 'Add to Cart', 'value' => $atc, 'step_rate' => Num::pct($atc, $sessions)],
            ['key' => 'checkout', 'label' => 'Checkout', 'value' => $checkouts, 'step_rate' => Num::pct($checkouts, $atc)],
            ['key' => 'purchase', 'label' => 'Purchase', 'value' => $purchases, 'step_rate' => Num::pct($purchases, $checkouts)],
        ];

        $overall = Num::pct($purchases, $sessions);
        $weakest = collect($steps)->slice(1)->sortBy('step_rate')->first();

        return [
            'steps' => $steps,
            'overall_conversion_pct' => $overall,
            'verdict' => ($sessions === 0
                ? Verdict::neutral('No sessions in this window.')
                : Verdict::watch(
                    sprintf('%.2f%% of sessions convert.', $overall),
                    sprintf('The weakest step is %s at %.1f%%.', $weakest['label'], $weakest['step_rate']),
                    match ($weakest['key']) {
                        'add_to_cart' => 'Product pages are not persuading — check imagery, reviews and price framing.',
                        'checkout' => 'People add to cart and leave — check shipping cost reveal and COD availability.',
                        default => 'Checkout is leaking — check payment failures and form friction.',
                    },
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function channelPerformance(WidgetFilters $filters): array
    {
        if ($this->missingGa4()) {
            return $this->ga4Missing('marketing channel performance');
        }

        $rows = $this->analytics($filters)
            ->selectRaw('channel_group, COALESCE(SUM(sessions),0) AS sessions, COALESCE(SUM(users),0) AS users')
            ->selectRaw('COALESCE(SUM(purchases),0) AS purchases, COALESCE(SUM(revenue),0) AS revenue')
            ->groupBy('channel_group')
            ->orderByDesc('revenue')
            ->get();

        $spendByChannel = DB::table('ad_spend_rollup')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('platform, COALESCE(SUM(spend),0) AS spend')
            ->groupBy('platform')
            ->pluck('spend', 'platform');

        return [
            'rows' => $rows->map(function (object $row) use ($spendByChannel): array {
                $spend = match ($row->channel_group) {
                    'Paid Social' => (int) ($spendByChannel['meta'] ?? 0),
                    'Paid Search' => (int) ($spendByChannel['google_ads'] ?? 0),
                    default => 0,
                };

                return [
                    'channel_group' => $row->channel_group,
                    'sessions' => (int) $row->sessions,
                    'users' => (int) $row->users,
                    'orders' => (int) $row->purchases,
                    'sales' => (int) $row->revenue,
                    'conversion_pct' => Num::pct((int) $row->purchases, (int) $row->sessions),
                    'ad_spend' => $spend,
                    'site_roas' => Num::ratio((int) $row->revenue, $spend),
                    'cac' => (int) round(Num::safeDivide($spend, (int) $row->purchases)),
                ];
            })->all(),
            'caveat' => 'Sales here are GA4-attributed and will not tie exactly to store net sales. Use them for channel comparison, not for accounting.',
        ];
    }

    /** @return array<string, mixed> */
    public function abandonedCarts(WidgetFilters $filters): array
    {
        $row = DB::table('abandoned_checkouts')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('abandoned_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(cart_value),0) AS value')
            ->selectRaw('SUM(CASE WHEN recovered = 1 THEN 1 ELSE 0 END) AS recovered')
            ->selectRaw('COALESCE(SUM(CASE WHEN recovered = 0 THEN cart_value ELSE 0 END),0) AS recoverable_value')
            ->first();

        $total = (int) ($row->total ?? 0);
        $recovered = (int) ($row->recovered ?? 0);
        $recoverable = (int) ($row->recoverable_value ?? 0);

        return [
            'count' => $total,
            'recovered' => $recovered,
            'recovery_rate_pct' => Num::pct($recovered, $total),
            'recoverable_value' => $recoverable,
            'avg_cart_value' => (int) round(Num::safeDivide((int) ($row->value ?? 0), $total)),
            'verdict' => ($total === 0
                ? Verdict::neutral('No abandoned checkouts recorded in this window.')
                : Verdict::watch(
                    sprintf('%s is sitting in abandoned carts.', Money::compact($recoverable)),
                    sprintf('%d carts abandoned, %d recovered (%.1f%%).', $total, $recovered, Num::pct($recovered, $total)),
                    'A three-step recovery flow typically converts 8-12% of this.',
                    $recoverable,
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function buyerPersona(WidgetFilters $filters): array
    {
        $visitors = DB::table('analytics_demographics')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('age, gender, COALESCE(SUM(sessions),0) AS sessions, COALESCE(SUM(purchases),0) AS purchases, COALESCE(SUM(revenue),0) AS revenue')
            ->groupBy('age', 'gender')
            ->get();

        $paid = DB::table('ad_insights_daily')
            ->where('tenant_id', Tenant::id())
            ->where('breakdown_key', 'demographic')
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()])
            ->selectRaw('age, gender, COALESCE(SUM(spend),0) AS spend, COALESCE(SUM(clicks),0) AS clicks')
            ->selectRaw('COALESCE(SUM(conversions),0) AS conversions, COALESCE(SUM(conversion_value),0) AS conversion_value')
            ->groupBy('age', 'gender')
            ->get()
            ->keyBy(static fn (object $row): string => $row->age.'|'.strtolower((string) $row->gender));

        if ($visitors->isEmpty() && $paid->isEmpty()) {
            return [
                'rows' => [],
                'caveat' => (new Caveat(
                    'No demographic rows. GA4 only reports age and gender when Google Signals is enabled, and Meta needs a demographic breakdown sync.',
                    'warning',
                ))->toArray(),
            ];
        }

        $rows = $visitors->map(function (object $row) use ($paid): array {
            $key = $row->age.'|'.strtolower((string) $row->gender);
            $ad = $paid->get($key);
            $spend = (int) ($ad->spend ?? 0);
            $value = (int) ($ad->conversion_value ?? 0);

            return [
                'age' => $row->age,
                'gender' => $row->gender,
                'sessions' => (int) $row->sessions,
                'site_purchases' => (int) $row->purchases,
                'site_revenue' => (int) $row->revenue,
                'ad_spend' => $spend,
                'ad_clicks' => (int) ($ad->clicks ?? 0),
                'ad_orders' => (int) ($ad->conversions ?? 0),
                'ad_sales' => $value,
                'roas' => Num::ratio($value, $spend),
            ];
        })->sortByDesc('site_revenue')->values()->all();

        return [
            'rows' => $rows,
            'caveat' => 'GA4 demographics cover visitors who consented to Google Signals, so the base is smaller than total traffic.',
        ];
    }

    private function analytics(WidgetFilters $filters): Builder
    {
        return DB::table('analytics_daily')
            ->where('tenant_id', Tenant::id())
            ->whereBetween('date', [$filters->period->fromDate(), $filters->period->toDate()]);
    }

    private function missingGa4(): bool
    {
        return in_array('ga4', $this->health->missing(['ga4']), true)
            && DB::table('analytics_daily')->where('tenant_id', Tenant::id())->doesntExist();
    }

    /** @return array<string, mixed> */
    private function ga4Missing(string $what): array
    {
        return [
            'steps' => [],
            'rows' => [],
            'caveat' => Caveat::missingConnector('ga4', $what)->toArray(),
            'verdict' => null,
        ];
    }
}

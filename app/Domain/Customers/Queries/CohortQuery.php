<?php

declare(strict_types=1);

namespace App\Domain\Customers\Queries;

use App\Models\Benchmark;
use App\Support\Facades\Tenant;
use App\Support\Verdict;
use Illuminate\Support\Facades\DB;

/**
 * Acquisition-month cohort retention. D2C only — marketplaces anonymise buyers,
 * so those orders can never join a cohort and we say so in the caveat.
 */
class CohortQuery
{
    /** @return array<string, mixed> */
    public function heatmap(int $months = 12): array
    {
        $rows = DB::table('cohort_snapshots')
            ->where('tenant_id', Tenant::id())
            ->where('month_index', '<=', $months)
            ->orderBy('cohort_month')
            ->orderBy('month_index')
            ->get();

        $cohorts = $rows->groupBy('cohort_month')->map(static function ($group, string $month) use ($months): array {
            $byIndex = $group->keyBy('month_index');

            return [
                'cohort_month' => $month,
                'cohort_size' => (int) ($byIndex[0]->cohort_size ?? $group->first()->cohort_size),
                'cells' => collect(range(0, $months))->map(static fn (int $i): ?array => isset($byIndex[$i]) ? [
                    'month_index' => $i,
                    'active_customers' => (int) $byIndex[$i]->active_customers,
                    'retention_pct' => (float) $byIndex[$i]->retention_pct,
                    'revenue' => (int) $byIndex[$i]->revenue,
                    'margin' => (int) $byIndex[$i]->margin,
                    'avg_ltv' => (int) $byIndex[$i]->avg_ltv,
                ] : null)->all(),
            ];
        })->values();

        return [
            'cohorts' => $cohorts->all(),
            'months' => $months,
            'caveat' => 'Cohorts cover D2C customers only. Marketplaces do not share buyer identity, so those orders cannot be attributed to a cohort.',
            'verdict' => $this->verdict($cohorts->all())->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $rows = DB::table('cohort_snapshots')
            ->where('tenant_id', Tenant::id())
            ->where('month_index', '<=', 6)
            ->orderByDesc('cohort_month')
            ->limit(42)
            ->get();

        $latestCohorts = $rows->pluck('cohort_month')->unique()->take(6);
        $filtered = $rows->whereIn('cohort_month', $latestCohorts->all());

        $byIndex = $filtered->groupBy('month_index')->map(static fn ($group): array => [
            'month_index' => (int) $group->first()->month_index,
            'avg_retention_pct' => round((float) $group->avg('retention_pct'), 2),
            'avg_ltv' => (int) round($group->avg('avg_ltv')),
        ])->sortBy('month_index')->values();

        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);
        $m1 = $byIndex->firstWhere('month_index', 1)['avg_retention_pct'] ?? 0.0;

        return [
            'series' => $byIndex->all(),
            'm1_retention' => $m1,
            'target_repeat_rate' => (float) $benchmark->target_repeat_rate,
            'verdict' => ($m1 >= $benchmark->target_repeat_rate
                ? Verdict::good(sprintf('Month-1 repeat is %.1f%%, at or above your %.0f%% target.', $m1, $benchmark->target_repeat_rate))
                : Verdict::watch(
                    sprintf('Month-1 repeat is %.1f%%, below your %.0f%% target.', $m1, $benchmark->target_repeat_rate),
                    'Below benchmark — focus retention before buying more first orders.',
                    'A post-purchase flow at day 21-30 is the cheapest lever here.',
                ))->toArray(),
        ];
    }

    /** @param list<array<string, mixed>> $cohorts */
    private function verdict(array $cohorts): Verdict
    {
        if ($cohorts === []) {
            return Verdict::neutral('Not enough order history yet to build cohorts.');
        }

        $m1Values = collect($cohorts)
            ->map(static fn (array $c): ?float => $c['cells'][1]['retention_pct'] ?? null)
            ->filter()
            ->values();

        if ($m1Values->count() < 2) {
            return Verdict::neutral('Cohorts need at least two months of history before a trend is meaningful.');
        }

        $recent = $m1Values->take(-3)->avg();
        $earlier = $m1Values->take(3)->avg();

        return $recent >= $earlier
            ? Verdict::good(
                sprintf('Month-1 retention is improving: %.1f%% in recent cohorts vs %.1f%% in the earliest.', $recent, $earlier),
            )
            : Verdict::watch(
                sprintf('Month-1 retention is slipping: %.1f%% in recent cohorts vs %.1f%% in the earliest.', $recent, $earlier),
                'Newer customers are coming back less than older ones did.',
                'Check whether recent acquisition is buying worse-fit customers.',
            );
    }
}

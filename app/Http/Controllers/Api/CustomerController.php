<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Customers\Queries\CohortQuery;
use App\Domain\Customers\Queries\CustomerQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Support\Facades\Tenant;
use App\Support\Num;
use App\Support\Verdict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    use ResolvesFilters;

    public function kpis(Request $request, CustomerQuery $customers): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('customers', 'kpis', $filters, fn (): array => $customers->kpis($filters)), $filters);
    }

    public function list(Request $request, CustomerQuery $customers): JsonResponse
    {
        $filters = $this->filters($request);
        $unmask = $request->user()->can('pii.unmask.view');

        return ApiResponse::ok(
            $customers->list(
                $filters,
                min($request->integer('per_page', 25), 200),
                $request->string('sort', 'total_spent')->toString(),
                $request->string('direction', 'desc')->toString(),
                $unmask,
            ),
            $filters,
            ['pii_unmasked' => $unmask, 'caveat' => $customers->caveat()->toArray()],
        );
    }

    public function show(Request $request, int $customer, CustomerQuery $customers): JsonResponse
    {
        $profile = $customers->profile($customer, $request->user()->can('pii.unmask.view'));

        return $profile === null
            ? ApiResponse::error('Customer not found.', 404)
            : ApiResponse::ok($profile);
    }

    public function rfm(CustomerQuery $customers): JsonResponse
    {
        return ApiResponse::ok($customers->rfm());
    }

    public function cohorts(Request $request, CohortQuery $cohorts): JsonResponse
    {
        return ApiResponse::ok($cohorts->heatmap($request->integer('months', 12)));
    }

    public function repeatMetrics(CustomerQuery $customers): JsonResponse
    {
        return ApiResponse::ok($customers->repeatMetrics());
    }

    public function churn(CustomerQuery $customers): JsonResponse
    {
        return ApiResponse::ok($customers->churn());
    }

    public function serialReturners(CustomerQuery $customers): JsonResponse
    {
        return ApiResponse::ok($customers->serialReturners());
    }

    public function purchaseInterval(CustomerQuery $customers): JsonResponse
    {
        return ApiResponse::ok($customers->purchaseInterval());
    }

    public function vip(Request $request, CustomerQuery $customers): JsonResponse
    {
        $filters = $this->filters($request);
        $unmask = $request->user()->can('pii.unmask.view');

        $rows = Customer::query()
            ->where('is_vip', true)
            ->orderByDesc('total_spent')
            ->limit(100)
            ->get();

        return ApiResponse::ok([
            'rows' => $rows->map(static fn (Customer $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $unmask ? $c->email_encrypted : $c->masked_email,
                'city' => $c->city,
                'state' => $c->state,
                'orders_count' => $c->orders_count,
                'total_spent' => $c->total_spent,
                'aov' => $c->aov,
                'total_margin' => $c->total_margin,
                'last_order_at' => $c->last_order_at?->toIso8601String(),
            ])->all(),
            'count' => $rows->count(),
            'total_value' => (int) $rows->sum('total_spent'),
        ], $filters);
    }

    public function ltvDistribution(): JsonResponse
    {
        $buckets = [
            ['label' => '< ₹1K', 'min' => 0, 'max' => 100_000],
            ['label' => '₹1K–2.5K', 'min' => 100_000, 'max' => 250_000],
            ['label' => '₹2.5K–5K', 'min' => 250_000, 'max' => 500_000],
            ['label' => '₹5K–10K', 'min' => 500_000, 'max' => 1_000_000],
            ['label' => '₹10K–25K', 'min' => 1_000_000, 'max' => 2_500_000],
            ['label' => '₹25K+', 'min' => 2_500_000, 'max' => PHP_INT_MAX],
        ];

        $rows = array_map(static function (array $bucket): array {
            $query = Customer::query()->where('orders_count', '>', 0)->where('ltv', '>=', $bucket['min']);

            if ($bucket['max'] !== PHP_INT_MAX) {
                $query->where('ltv', '<', $bucket['max']);
            }

            return [
                'label' => $bucket['label'],
                'customers' => $query->count(),
                'revenue' => (int) $query->sum('ltv'),
            ];
        }, $buckets);

        $totalRevenue = array_sum(array_column($rows, 'revenue'));
        $totalCustomers = array_sum(array_column($rows, 'customers'));

        return ApiResponse::ok([
            'rows' => array_map(static fn (array $row): array => [
                ...$row,
                'customer_share_pct' => Num::pct($row['customers'], $totalCustomers),
                'revenue_share_pct' => Num::pct($row['revenue'], $totalRevenue),
            ], $rows),
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalRevenue,
        ]);
    }

    public function geo(): JsonResponse
    {
        $rows = DB::table('customers')
            ->where('tenant_id', Tenant::id())
            ->where('orders_count', '>', 0)
            ->whereNotNull('state')
            ->selectRaw('state, COUNT(*) AS customers, COALESCE(SUM(total_spent),0) AS revenue, COALESCE(AVG(ltv),0) AS avg_ltv')
            ->groupBy('state')
            ->orderByDesc('revenue')
            ->get();

        return ApiResponse::ok(['rows' => $rows->map(static fn (object $r): array => [
            'state' => $r->state,
            'customers' => (int) $r->customers,
            'revenue' => (int) $r->revenue,
            'avg_ltv' => (int) round((float) $r->avg_ltv),
        ])->all()]);
    }

    /** Reviews summary, trend and feed. */
    public function reviewSummary(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('reviews', 'summary', $filters, function () use ($filters): array {
            $base = DB::table('reviews')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('reviewed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]);

            $row = (clone $base)
                ->selectRaw('COUNT(*) AS total, AVG(rating) AS avg_rating')
                ->selectRaw('SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) AS positive')
                ->selectRaw('SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS negative')
                ->selectRaw('SUM(CASE WHEN has_photos = 1 THEN 1 ELSE 0 END) AS with_photos')
                ->selectRaw('SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) AS verified')
                ->first();

            $distribution = (clone $base)
                ->selectRaw('rating, COUNT(*) AS count')
                ->groupBy('rating')
                ->pluck('count', 'rating');

            $total = (int) ($row->total ?? 0);
            $analysed = (clone $base)->whereNotNull('sentiment')->count();

            return [
                'total' => $total,
                'avg_rating' => round((float) ($row->avg_rating ?? 0), 2),
                'positive_pct' => Num::pct((int) ($row->positive ?? 0), $total),
                'negative_pct' => Num::pct((int) ($row->negative ?? 0), $total),
                'with_photos_pct' => Num::pct((int) ($row->with_photos ?? 0), $total),
                'verified_pct' => Num::pct((int) ($row->verified ?? 0), $total),
                'distribution' => collect(range(5, 1))->map(static fn (int $star): array => [
                    'rating' => $star,
                    'count' => (int) ($distribution[$star] ?? 0),
                    'pct' => Num::pct((int) ($distribution[$star] ?? 0), $total),
                ])->all(),
                'sentiment_analysed' => $analysed,
                'caveat' => $analysed === 0
                    ? 'Sentiment and themes are left empty until the LLM analysis job has run. They are never inferred from the star rating.'
                    : sprintf('%d of %d reviews have been analysed for sentiment.', $analysed, $total),
            ];
        }), $filters);
    }

    public function recentReviews(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $paginator = DB::table('reviews as r')
            ->leftJoin('skus as s', 's.id', '=', 'r.sku_id')
            ->where('r.tenant_id', Tenant::id())
            ->when($request->filled('rating'), fn ($q) => $q->where('r.rating', $request->integer('rating')))
            ->when($request->boolean('verified_only'), fn ($q) => $q->where('r.verified', true))
            ->when($request->boolean('with_photos'), fn ($q) => $q->where('r.has_photos', true))
            ->selectRaw('r.id, r.rating, r.title, r.body, r.reviewer, r.verified, r.has_photos, r.reviewed_at, r.sentiment, r.themes')
            ->selectRaw('s.sku_code, s.name AS sku_name')
            ->orderByDesc('r.reviewed_at')
            ->paginate(min($request->integer('per_page', 25), 100));

        return ApiResponse::ok($paginator, $filters);
    }

    /**
     * Products with good reviews but high returns — the sizing/expectation gap.
     */
    public function reviewReturnCorrelation(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('reviews', 'return_correlation', $filters, function () use ($filters): array {
            $rows = DB::table('skus as s')
                ->leftJoin('reviews as rv', 'rv.sku_id', '=', 's.id')
                ->leftJoin('sku_daily_rollup as sr', function ($join) use ($filters): void {
                    $join->on('sr.sku_id', '=', 's.id')
                        ->whereBetween('sr.date', [$filters->period->fromDate(), $filters->period->toDate()]);
                })
                ->where('s.tenant_id', Tenant::id())
                ->selectRaw('s.id, s.sku_code, s.name')
                ->selectRaw('AVG(rv.rating) AS avg_rating, COUNT(DISTINCT rv.id) AS review_count')
                ->selectRaw('COALESCE(SUM(DISTINCT sr.units_sold),0) AS units, COALESCE(SUM(DISTINCT sr.returned_units),0) AS returned_units')
                ->groupBy('s.id', 's.sku_code', 's.name')
                ->havingRaw('COUNT(DISTINCT rv.id) >= 3 AND COALESCE(SUM(DISTINCT sr.units_sold),0) > 0')
                ->get();

            $mapped = $rows->map(static function (object $row): array {
                $returnRate = Num::pct((int) $row->returned_units, (int) $row->units);
                $rating = round((float) $row->avg_rating, 2);

                return [
                    'sku_id' => $row->id,
                    'sku_code' => $row->sku_code,
                    'name' => $row->name,
                    'avg_rating' => $rating,
                    'review_count' => (int) $row->review_count,
                    'units' => (int) $row->units,
                    'returned_units' => (int) $row->returned_units,
                    'return_rate' => $returnRate,
                    // Well-reviewed but frequently returned = the product is fine,
                    // the listing is setting the wrong expectation.
                    'expectation_gap' => $rating >= 4.0 && $returnRate >= 15.0,
                ];
            })->sortByDesc('return_rate')->values();

            $gaps = $mapped->where('expectation_gap', true);

            return [
                'rows' => $mapped->all(),
                'expectation_gaps' => $gaps->values()->all(),
                'caveat' => 'Only SKUs with at least three reviews and some sales in the window are included.',
                'verdict' => ($gaps->isEmpty()
                    ? Verdict::good('No product is well-reviewed and heavily returned at the same time.')->toArray()
                    : Verdict::watch(
                        sprintf('%d products are rated 4+ but returned more than 15%% of the time.', $gaps->count()),
                        'People like the product once they have it — the listing is setting the wrong expectation.',
                        'Fix the size chart and photography before touching the product itself.',
                    )->toArray()),
            ];
        }), $filters);
    }
}

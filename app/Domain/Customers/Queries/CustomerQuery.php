<?php

declare(strict_types=1);

namespace App\Domain\Customers\Queries;

use App\Enums\RfmSegment;
use App\Models\Benchmark;
use App\Models\Customer;
use App\Support\Caveat;
use App\Support\Facades\Tenant;
use App\Support\Money;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Customer intelligence. Marketplaces anonymise buyers, so every number here is
 * D2C-only and says so — inflating it with marketplace order counts would make
 * repeat rate and LTV meaningless.
 */
class CustomerQuery
{
    public function caveat(): Caveat
    {
        return Caveat::note('Customer metrics cover D2C orders only. Marketplaces do not share buyer identity, so those orders cannot be attributed to a person.');
    }

    /** @return array<string, mixed> */
    public function kpis(WidgetFilters $filters): array
    {
        $benchmark = Benchmark::query()->firstOrCreate(['tenant_id' => Tenant::id()]);
        $current = $this->summarise($filters);
        $previous = $this->summarise($filters->previous());

        $rating = DB::table('reviews')
            ->where('tenant_id', Tenant::id())
            ->selectRaw('AVG(rating) AS avg_rating, COUNT(*) AS total')
            ->first();

        return [
            'total_customers' => ['label' => 'Total Customers', 'value' => $current['total'], 'prev_value' => $previous['total'], 'format' => 'number', 'higher_is_better' => true],
            'new_customers' => ['label' => 'New', 'value' => $current['new'], 'prev_value' => $previous['new'], 'format' => 'number', 'higher_is_better' => true],
            'returning_customers' => ['label' => 'Returning', 'value' => $current['returning'], 'prev_value' => $previous['returning'], 'format' => 'number', 'higher_is_better' => true],
            'repeat_rate' => [
                'label' => 'Repeat Rate', 'value' => $current['repeat_rate'], 'prev_value' => $previous['repeat_rate'],
                'format' => 'percent', 'higher_is_better' => true,
                'badge' => sprintf('target %.0f%%', $benchmark->target_repeat_rate),
            ],
            'avg_ltv' => ['label' => 'Avg LTV', 'value' => $current['avg_ltv'], 'prev_value' => $previous['avg_ltv'], 'format' => 'currency', 'higher_is_better' => true],
            'store_rating' => [
                'label' => 'Store Rating', 'value' => round((float) ($rating->avg_rating ?? 0), 2), 'prev_value' => null,
                'format' => 'number', 'higher_is_better' => true,
                'badge' => number_format((int) ($rating->total ?? 0)).' reviews',
            ],
            'caveat' => $this->caveat()->toArray(),
            'verdict' => ($current['repeat_rate'] >= $benchmark->target_repeat_rate
                ? Verdict::good(sprintf('Repeat rate is %.1f%%, at or above your %.0f%% benchmark.', $current['repeat_rate'], $benchmark->target_repeat_rate))
                : Verdict::watch(
                    sprintf('Repeat rate is %.1f%%, below your %.0f%% benchmark.', $current['repeat_rate'], $benchmark->target_repeat_rate),
                    'Below benchmark — focus retention.',
                    'A second order is roughly five times cheaper than a first one. Fix this before raising ad spend.',
                ))->toArray(),
        ];
    }

    /** @return array<string, int|float> */
    private function summarise(WidgetFilters $filters): array
    {
        $row = DB::table('orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.tenant_id', Tenant::id())
            ->whereBetween('o.placed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
            ->where('o.status', '!=', 'cancelled')
            ->selectRaw('COUNT(DISTINCT o.customer_id) AS total')
            ->selectRaw('COUNT(DISTINCT CASE WHEN o.is_first_order = 1 THEN o.customer_id END) AS new_customers')
            ->selectRaw('COUNT(DISTINCT CASE WHEN o.is_first_order = 0 THEN o.customer_id END) AS returning')
            ->selectRaw('COALESCE(AVG(c.ltv),0) AS avg_ltv')
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'total' => $total,
            'new' => (int) ($row->new_customers ?? 0),
            'returning' => (int) ($row->returning ?? 0),
            'repeat_rate' => Num::pct((int) ($row->returning ?? 0), $total),
            'avg_ltv' => (int) round((float) ($row->avg_ltv ?? 0)),
        ];
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function list(WidgetFilters $filters, int $perPage = 25, string $sort = 'total_spent', string $direction = 'desc', bool $unmask = false): LengthAwarePaginator
    {
        $sortable = ['total_spent', 'orders_count', 'aov', 'ltv', 'last_order_at', 'churn_risk_score', 'returns_count'];

        return Customer::query()
            ->where('orders_count', '>', 0)
            ->when($filters->search !== null, fn ($q) => $q->where(function ($inner) use ($filters): void {
                $inner->where('name', 'like', '%'.$filters->search.'%')
                    ->orWhere('masked_email', 'like', '%'.$filters->search.'%')
                    ->orWhere('city', 'like', '%'.$filters->search.'%')
                    ->orWhere('state', 'like', '%'.$filters->search.'%');
            }))
            ->orderBy(in_array($sort, $sortable, true) ? $sort : 'total_spent', $direction === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->through(fn (Customer $customer): array => $this->row($customer, $unmask));
    }

    /** @return array<string, mixed> */
    public function rfm(): array
    {
        $rows = DB::table('customers')
            ->where('tenant_id', Tenant::id())
            ->whereNotNull('rfm_segment')
            ->selectRaw('rfm_segment, COUNT(*) AS customers, COALESCE(SUM(total_spent),0) AS revenue, COALESCE(AVG(ltv),0) AS avg_ltv')
            ->groupBy('rfm_segment')
            ->get()
            ->keyBy('rfm_segment');

        $totalCustomers = (int) $rows->sum('customers');
        $totalRevenue = (int) $rows->sum('revenue');

        $segments = collect(RfmSegment::cases())->map(static function (RfmSegment $segment) use ($rows, $totalCustomers, $totalRevenue): array {
            $row = $rows->get($segment->value);

            return [
                'segment' => $segment->value,
                'label' => $segment->label(),
                'color' => $segment->color(),
                'playbook' => $segment->playbook(),
                'customers' => (int) ($row->customers ?? 0),
                'revenue' => (int) ($row->revenue ?? 0),
                'avg_ltv' => (int) round((float) ($row->avg_ltv ?? 0)),
                'customer_share_pct' => Num::pct((int) ($row->customers ?? 0), $totalCustomers),
                'revenue_share_pct' => Num::pct((int) ($row->revenue ?? 0), $totalRevenue),
            ];
        });

        $atRisk = $segments->whereIn('segment', [RfmSegment::AtRisk->value, RfmSegment::Hibernating->value]);
        $atRiskRevenue = (int) $atRisk->sum('revenue');

        return [
            'rows' => $segments->all(),
            'total_customers' => $totalCustomers,
            'total_revenue' => $totalRevenue,
            'caveat' => $this->caveat()->toArray(),
            'verdict' => ($atRiskRevenue > $totalRevenue * 0.2
                ? Verdict::watch(
                    sprintf('%s of lifetime revenue sits with at-risk and hibernating customers.', Money::compact($atRiskRevenue)),
                    sprintf('That is %.0f%% of everything these customers have ever spent.', Num::pct($atRiskRevenue, $totalRevenue)),
                    'Run a win-back to At Risk first — they are the closest to coming back.',
                    $atRiskRevenue,
                )
                : Verdict::good('Most of your lifetime revenue sits with active segments.'))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function churn(int $limit = 50): array
    {
        $rows = Customer::query()
            ->where('orders_count', '>', 1)
            ->where('churn_risk_score', '>=', 60)
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        $valueAtRisk = (int) $rows->sum('total_spent');

        return [
            'rows' => $rows->map(fn (Customer $c): array => $this->row($c, false))->all(),
            'count' => $rows->count(),
            'value_at_risk' => $valueAtRisk,
            'caveat' => 'Churn risk is recency measured against each customer\'s own buying cadence, not a trained model. A monthly buyer is at risk at 60 days; an annual buyer is not.',
            'verdict' => ($rows->isEmpty()
                ? Verdict::good('No repeat customer is currently overdue against their own cadence.')
                : Verdict::watch(
                    sprintf('%d repeat customers are overdue on their own buying cadence.', $rows->count()),
                    sprintf('They represent %s of lifetime spend.', Money::compact($valueAtRisk)),
                    'These are your cheapest orders to win back — they already trust you.',
                    $valueAtRisk,
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function repeatMetrics(): array
    {
        $row = DB::table('customers')
            ->where('tenant_id', Tenant::id())
            ->where('orders_count', '>', 0)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN orders_count > 1 THEN 1 ELSE 0 END) AS repeaters')
            ->selectRaw('AVG(orders_count) AS avg_orders')
            ->selectRaw('AVG(avg_days_between_orders) AS avg_gap')
            ->first();

        $secondWithin90 = (int) DB::table('customers as c')
            ->join('orders as o', 'o.customer_id', '=', 'c.id')
            ->where('c.tenant_id', Tenant::id())
            ->where('o.is_first_order', false)
            ->whereRaw('DATEDIFF(o.placed_at, c.first_order_at) <= 90')
            ->distinct()
            ->count('c.id');

        $total = (int) ($row->total ?? 0);

        return [
            'total_customers' => $total,
            'repeat_customers' => (int) ($row->repeaters ?? 0),
            'repeat_purchase_rate' => Num::pct((int) ($row->repeaters ?? 0), $total),
            'avg_orders_per_customer' => round((float) ($row->avg_orders ?? 0), 2),
            'second_order_within_90d' => $secondWithin90,
            'second_order_within_90d_pct' => Num::pct($secondWithin90, $total),
            'avg_days_to_second_order' => round((float) ($row->avg_gap ?? 0), 1),
            'caveat' => $this->caveat()->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function serialReturners(int $limit = 50): array
    {
        $rows = Customer::query()
            ->where('returns_count', '>=', 2)
            ->orderByDesc('returns_count')
            ->limit($limit)
            ->get();

        return [
            'rows' => $rows->map(fn (Customer $c): array => [
                ...$this->row($c, false),
                'return_rate_pct' => Num::pct($c->returns_count, $c->orders_count),
            ])->all(),
            'count' => $rows->count(),
            'verdict' => ($rows->isEmpty()
                ? Verdict::good('No customer has returned more than once.')
                : Verdict::watch(
                    sprintf('%d customers have returned two or more orders.', $rows->count()),
                    'A small group of serial returners usually accounts for a large share of reverse-logistics cost.',
                    'Add them to the prepaid-only list rather than blocking them outright.',
                ))->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function purchaseInterval(): array
    {
        $buckets = [
            ['label' => '0-30 days', 'min' => 0, 'max' => 30],
            ['label' => '31-60 days', 'min' => 31, 'max' => 60],
            ['label' => '61-90 days', 'min' => 61, 'max' => 90],
            ['label' => '91-180 days', 'min' => 91, 'max' => 180],
            ['label' => '180+ days', 'min' => 181, 'max' => 100000],
        ];

        return [
            'rows' => array_map(static fn (array $bucket): array => [
                'label' => $bucket['label'],
                'customers' => (int) DB::table('customers')
                    ->where('tenant_id', Tenant::id())
                    ->whereBetween('avg_days_between_orders', [$bucket['min'], $bucket['max']])
                    ->count(),
            ], $buckets),
            'caveat' => 'Only customers with two or more orders have an interval.',
        ];
    }

    /**
     * Customer 360 — everything known about one person in one place.
     *
     * @return array<string, mixed>|null
     */
    public function profile(int $customerId, bool $unmask = false): ?array
    {
        $customer = Customer::query()->find($customerId);

        if ($customer === null) {
            return null;
        }

        $orders = DB::table('orders as o')
            ->leftJoin('channels as c', 'c.id', '=', 'o.channel_id')
            ->where('o.customer_id', $customer->id)
            ->selectRaw('o.id, o.order_number, o.placed_at, o.status, o.payment_mode, o.net_amount, o.contribution_margin, o.is_rto, c.name AS channel_name')
            ->orderByDesc('o.placed_at')
            ->get();

        $returns = DB::table('returns as r')
            ->join('orders as o', 'o.id', '=', 'r.order_id')
            ->where('o.customer_id', $customer->id)
            ->selectRaw('r.id, r.type, r.reason_code, r.reason_text, r.initiated_at, r.refund_amount, o.order_number')
            ->orderByDesc('r.initiated_at')
            ->get();

        return [
            'customer' => $this->row($customer, $unmask),
            'orders' => $orders->all(),
            'returns' => $returns->all(),
            'timeline' => $orders->map(static fn (object $o): array => [
                'type' => 'order',
                'at' => $o->placed_at,
                'title' => $o->order_number,
                'amount' => (int) $o->net_amount,
                'meta' => $o->channel_name,
            ])->concat($returns->map(static fn (object $r): array => [
                'type' => 'return',
                'at' => $r->initiated_at,
                'title' => $r->reason_text ?? $r->reason_code,
                'amount' => -(int) $r->refund_amount,
                'meta' => $r->order_number,
            ]))->sortByDesc('at')->values()->all(),
            'segment' => $customer->rfm_segment?->label(),
            'playbook' => $customer->rfm_segment?->playbook(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(Customer $customer, bool $unmask): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            // PII stays masked unless the caller holds pii.unmask.view.
            'email' => $unmask ? $customer->email_encrypted : $customer->masked_email,
            'phone' => $unmask ? $customer->phone_encrypted : $customer->masked_phone,
            'city' => $customer->city,
            'state' => $customer->state,
            'orders_count' => $customer->orders_count,
            'total_spent' => $customer->total_spent,
            'aov' => $customer->aov,
            'ltv' => $customer->ltv,
            'total_margin' => $customer->total_margin,
            'returns_count' => $customer->returns_count,
            'first_order_at' => $customer->first_order_at?->toIso8601String(),
            'last_order_at' => $customer->last_order_at?->toIso8601String(),
            'days_since_last_order' => $customer->days_since_last_order,
            'rfm_segment' => $customer->rfm_segment?->value,
            'rfm_label' => $customer->rfm_segment?->label(),
            'is_vip' => $customer->is_vip,
            'churn_risk_score' => $customer->churn_risk_score,
        ];
    }
}

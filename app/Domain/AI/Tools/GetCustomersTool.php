<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Domain\Customers\Queries\CohortQuery;
use App\Domain\Customers\Queries\CustomerQuery;
use App\Support\WidgetFilters;

class GetCustomersTool extends BaseMetricTool
{
    public function __construct(
        private readonly CustomerQuery $customers,
        private readonly CohortQuery $cohorts,
    ) {}

    public function name(): string
    {
        return 'get_customers';
    }

    public function description(): string
    {
        return 'Customer intelligence: repeat rate against benchmark, average LTV, RFM segments with revenue per segment, churn risk, and cohort retention by acquisition month. D2C only — marketplaces do not share buyer identity. Use for retention, repeat purchase, LTV or segment questions.';
    }

    public function permission(): string
    {
        return 'customer_intelligence.kpi_strip.view';
    }

    /** @return array<string, mixed> */
    protected function properties(): array
    {
        return [
            ...$this->periodProperties(),
            'view' => [
                'type' => 'string',
                'enum' => ['summary', 'segments', 'cohorts', 'churn'],
                'description' => 'Which slice to return. Defaults to summary.',
            ],
        ];
    }

    /** @param array<string, mixed> $input */
    public function run(array $input, WidgetFilters $filters): array
    {
        $resolved = $this->resolve($input, $filters);
        $caveat = $this->customers->caveat()->message;

        return match ($input['view'] ?? 'summary') {
            'segments' => (function () use ($caveat): array {
                $rfm = $this->customers->rfm();

                return [
                    'caveat' => $caveat,
                    'total_customers' => $rfm['total_customers'],
                    'verdict' => $rfm['verdict']['headline'] ?? null,
                    'segments' => array_map(fn (array $row): array => [
                        'segment' => $row['label'],
                        'customers' => $row['customers'],
                        'lifetime_revenue' => $this->money($row['revenue']),
                        'avg_ltv' => $this->money($row['avg_ltv']),
                        'share_of_revenue_pct' => $row['revenue_share_pct'],
                        'playbook' => $row['playbook'],
                    ], $rfm['rows']),
                ];
            })(),

            'cohorts' => (function () use ($caveat): array {
                $summary = $this->cohorts->summary();

                return [
                    'caveat' => $caveat,
                    'month_1_repeat_pct' => $summary['m1_retention'],
                    'target_repeat_rate_pct' => $summary['target_repeat_rate'],
                    'verdict' => $summary['verdict']['headline'] ?? null,
                    'retention_curve' => $summary['series'],
                ];
            })(),

            'churn' => (function (): array {
                $churn = $this->customers->churn(25);

                return [
                    'caveat' => $churn['caveat'],
                    'at_risk_customers' => $churn['count'],
                    'lifetime_value_at_risk' => $this->money($churn['value_at_risk']),
                    'verdict' => $churn['verdict']['headline'] ?? null,
                ];
            })(),

            default => (function () use ($resolved, $caveat): array {
                $kpis = $this->customers->kpis($resolved);
                $repeat = $this->customers->repeatMetrics();

                return [
                    'period' => $this->describePeriod($resolved),
                    'caveat' => $caveat,
                    'total_customers' => $kpis['total_customers']['value'],
                    'new_customers' => $kpis['new_customers']['value'],
                    'returning_customers' => $kpis['returning_customers']['value'],
                    'repeat_rate_pct' => $kpis['repeat_rate']['value'],
                    'avg_ltv' => $this->money((int) $kpis['avg_ltv']['value']),
                    'store_rating' => $kpis['store_rating']['value'],
                    'avg_orders_per_customer' => $repeat['avg_orders_per_customer'],
                    'avg_days_to_second_order' => $repeat['avg_days_to_second_order'],
                    'verdict' => $kpis['verdict']['headline'] ?? null,
                ];
            })(),
        };
    }
}

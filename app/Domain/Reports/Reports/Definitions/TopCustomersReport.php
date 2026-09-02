<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Customers\Queries\CustomerQuery;
use App\Domain\Reports\Datasets\DatasetRegistry;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Money;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The customers worth keeping, the ones about to leave, and the ones whose
 * returns quietly cost more than their orders bring in.
 */
class TopCustomersReport extends Report
{
    public function __construct(
        private readonly CustomerQuery $customers,
        private readonly DatasetRegistry $datasets,
    ) {}

    public function key(): string
    {
        return 'top_customers';
    }

    public function label(): string
    {
        return 'Top Customers';
    }

    public function category(): string
    {
        return 'Marketing & Customers';
    }

    public function description(): string
    {
        return 'Highest lifetime value customers.';
    }

    public function usesPeriod(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['customers'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $kpis = $this->customers->kpis($filters);
        $rfm = $this->customers->rfm();
        $churn = $this->customers->churn(50);
        $returners = $this->customers->serialReturners(30);
        $dataset = $this->datasets->build('customers', $filters);

        return new ReportPayload(
            kpis: [
                $this->kpi('total_customers', 'Customers', (float) $kpis['total_customers']['value'], (float) $kpis['total_customers']['prev_value'], 'number'),
                $this->kpi('avg_ltv', 'Average LTV', (float) $kpis['avg_ltv']['value'], (float) $kpis['avg_ltv']['prev_value']),
                $this->kpi('repeat_rate', 'Repeat Rate', (float) $kpis['repeat_rate']['value'], (float) $kpis['repeat_rate']['prev_value'], 'percent'),
                $this->kpi('at_risk', 'At risk of churning', (float) count($churn['rows'] ?? []), null, 'number', higherIsBetter: false),
            ],
            sections: [
                $this->tableFromDataset($dataset, 'Ranked by lifetime spend', exportKey: 'customers'),
                Section::table('RFM segments', collect($rfm['rows'])->all(), [
                    ['key' => 'label', 'label' => 'Segment', 'format' => 'text'],
                    ['key' => 'customers', 'label' => 'Customers', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'revenue', 'label' => 'Revenue', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'avg_ltv', 'label' => 'Average LTV', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'revenue_share_pct', 'label' => 'Share of revenue', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'playbook', 'label' => 'What to do', 'format' => 'text'],
                ], 'Recency, frequency and monetary scoring across your whole customer base.',
                    caveat: $this->caveatFrom($rfm['caveat'] ?? null)),
                Section::table('About to leave', collect($churn['rows'] ?? [])->all(), [
                    ['key' => 'name', 'label' => 'Customer', 'format' => 'text'],
                    ['key' => 'orders_count', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'total_spent', 'label' => 'Spent', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'days_since_last_order', 'label' => 'Days quiet', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'churn_risk_score', 'label' => 'Risk', 'format' => 'number', 'align' => 'right'],
                ], sprintf('%d repeat customers are overdue against their own buying cadence.', (int) $churn['count']),
                    caveat: $this->caveatFrom($churn['caveat'] ?? null)),
                Section::table('Serial returners', collect($returners['rows'] ?? [])->all(), [
                    ['key' => 'name', 'label' => 'Customer', 'format' => 'text'],
                    ['key' => 'orders_count', 'label' => 'Orders', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'returns_count', 'label' => 'Returns', 'format' => 'number', 'align' => 'right'],
                    ['key' => 'return_rate_pct', 'label' => 'Return rate', 'format' => 'percent', 'align' => 'right'],
                    ['key' => 'total_spent', 'label' => 'Spent', 'format' => 'currency', 'align' => 'right'],
                    ['key' => 'total_margin', 'label' => 'Margin earned', 'format' => 'currency', 'align' => 'right'],
                ], 'Customers whose return habit makes them unprofitable to serve.'),
            ],
            verdict: $this->verdict($rfm, $churn),
            caveats: [$this->customers->caveat()],
        );
    }

    /**
     * @param  array<string, mixed>  $rfm
     * @param  array<string, mixed>  $churn
     */
    private function verdict(array $rfm, array $churn): Verdict
    {
        $atRisk = collect($churn['rows'] ?? []);

        if ($atRisk->isEmpty()) {
            return Verdict::good('No high-value customer has gone quiet', 'Nobody worth chasing has stopped buying.');
        }

        $value = (int) $atRisk->sum('total_spent');

        return Verdict::watch(
            sprintf('%d customers worth %s have gone quiet', $atRisk->count(), Money::format($value)),
            sprintf('Average %.0f days since their last order.', (float) $atRisk->avg('days_since_last_order')),
            'Win-back beats acquisition on cost — send this list to your email tool first.',
        );
    }
}

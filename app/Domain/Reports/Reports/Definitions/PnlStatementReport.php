<?php

declare(strict_types=1);

namespace App\Domain\Reports\Reports\Definitions;

use App\Domain\Reports\Queries\PnlQuery;
use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportPayload;
use App\Domain\Reports\Reports\Section;
use App\Support\Caveat;
use App\Support\Num;
use App\Support\Verdict;
use App\Support\WidgetFilters;

/**
 * The statement an accountant recognises: gross sales down to EBITDA, month by
 * month, in the order the money actually leaves the business.
 */
class PnlStatementReport extends Report
{
    public function __construct(private readonly PnlQuery $pnl) {}

    public function key(): string
    {
        return 'pnl_statement';
    }

    public function label(): string
    {
        return 'P&L Statement';
    }

    public function category(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Full monthly P&L down to EBITDA.';
    }

    /** @return list<string> */
    public function exports(): array
    {
        return ['pnl'];
    }

    public function build(WidgetFilters $filters): ReportPayload
    {
        $result = $this->pnl->handle($filters);
        $previous = $this->pnl->handle($filters->previous());
        $months = collect($result['columns']);
        $totals = $result['totals'];
        $previousTotals = $previous['totals'];

        $rows = collect($result['rows'])->map(static function (array $definition) use ($months, $totals): array {
            $row = [
                'label' => $definition['label'],
                'kind' => $definition['kind'],
                'indent' => $definition['indent'] ?? false,
                'total' => $totals[$definition['key']] ?? 0,
            ];

            foreach ($months as $month) {
                $row[$month['key']] = $month[$definition['key']] ?? 0;
            }

            return $row;
        })->all();

        $trend = $months->map(static fn (array $month): array => [
            'month' => $month['month'],
            'net_sales' => $month['net_sales'],
            'contribution_margin' => $month['contribution_margin'],
            'ebitda' => $month['ebitda'],
        ])->all();

        return new ReportPayload(
            kpis: [
                $this->kpi('net_sales', 'Net Sales', (float) ($totals['net_sales'] ?? 0), (float) ($previousTotals['net_sales'] ?? 0)),
                $this->kpi('gross_profit', 'Gross Profit', (float) ($totals['gross_profit'] ?? 0), (float) ($previousTotals['gross_profit'] ?? 0)),
                $this->kpi('contribution_margin', 'Contribution Margin', (float) ($totals['contribution_margin'] ?? 0), (float) ($previousTotals['contribution_margin'] ?? 0)),
                $this->kpi('ebitda', 'EBITDA', (float) ($totals['ebitda'] ?? 0), (float) ($previousTotals['ebitda'] ?? 0),
                    tooltip: 'Contribution margin less ad spend and the fixed monthly opex from your cost settings.'),
            ],
            sections: [
                Section::table('Profit & loss', $rows, $this->columns($months->all()),
                    'Indented lines are deductions; bold lines are subtotals.',
                    exportKey: 'pnl', config: ['statement' => true]),
                Section::chart(Section::LINE, 'Month by month', $trend, 'month', [
                    ['key' => 'net_sales', 'label' => 'Net sales', 'format' => 'currency'],
                    ['key' => 'contribution_margin', 'label' => 'Contribution margin', 'format' => 'currency'],
                    ['key' => 'ebitda', 'label' => 'EBITDA', 'format' => 'currency'],
                ]),
                Section::callouts('Margin structure', [
                    ['label' => 'Gross margin', 'value' => Num::pct((int) ($totals['gross_profit'] ?? 0), (int) ($totals['net_sales'] ?? 0)), 'format' => 'percent'],
                    ['label' => 'Contribution margin', 'value' => Num::pct((int) ($totals['contribution_margin'] ?? 0), (int) ($totals['net_sales'] ?? 0)), 'format' => 'percent'],
                    ['label' => 'EBITDA margin', 'value' => Num::pct((int) ($totals['ebitda'] ?? 0), (int) ($totals['net_sales'] ?? 0)), 'format' => 'percent',
                        'tone' => (int) ($totals['ebitda'] ?? 0) >= 0 ? 'good' : 'bad'],
                    ['label' => 'Ad spend as % of net sales', 'value' => Num::pct(abs((int) ($totals['ad_spend'] ?? 0)), (int) ($totals['net_sales'] ?? 0)), 'format' => 'percent'],
                ]),
            ],
            verdict: $this->verdictFrom($result),
            caveats: [
                $this->caveatFrom($result['caveat'] ?? null),
                Caveat::note('This is a management P&L built from your order and ad data. It is not a filed statement and does not include depreciation, interest or tax.'),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $months
     * @return list<array<string, mixed>>
     */
    private function columns(array $months): array
    {
        $columns = [['key' => 'label', 'label' => 'Line', 'format' => 'text']];

        foreach ($months as $month) {
            $columns[] = ['key' => $month['key'], 'label' => $month['month'], 'format' => 'currency', 'align' => 'right'];
        }

        $columns[] = ['key' => 'total', 'label' => 'Total', 'format' => 'currency', 'align' => 'right'];

        return $columns;
    }

    /** @param array<string, mixed> $result */
    private function verdictFrom(array $result): Verdict
    {
        $verdict = $result['verdict'] ?? null;

        if (is_array($verdict)) {
            return new Verdict(
                $verdict['status'] ?? Verdict::NEUTRAL,
                $verdict['headline'] ?? '',
                $verdict['detail'] ?? null,
                $verdict['action'] ?? null,
                $verdict['impact_paise'] ?? null,
            );
        }

        return Verdict::neutral('No months in this window');
    }
}

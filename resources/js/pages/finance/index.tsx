import { Head } from '@inertiajs/react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    ResponsiveContainer,
    Tooltip as RTooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { KpiStrip } from '@/components/app/kpi-card';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable, type Column } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { Badge } from '@/components/ui/badge';
import { WaterfallChart, WaterfallLegend, type WaterfallStep } from '@/components/charts/waterfall-chart';
import { AXIS_PROPS, CHART_COLORS, ChartLegend, ChartTooltip, GRID_PROPS, axisCurrency, axisDate } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatDateTime, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Metric } from '@/types';

interface PnlColumn {
    month: string;
    key: string;
    [metric: string]: string | number;
}

interface PnlRow {
    key: string;
    label: string;
    kind: 'positive' | 'negative' | 'subtotal' | 'total';
    indent?: boolean;
}

export default function Finance() {
    const kpis = useWidget<Metric[]>('finance/kpis');
    const salesOverTime = useWidget<{ date: string; net_sales: number; prev_net_sales: number; orders: number }[]>('finance/sales-over-time');
    const aov = useWidget<{ date: string; aov: number; prev_aov: number }[]>('finance/aov-trend');
    const breakdown = useWidget<{ steps: WaterfallStep[] }>('finance/revenue-breakdown');
    const geo = useWidget<{ rows: { state: string; orders: number; net_sales: number; share_pct: number; margin_pct: number }[] }>('finance/geographic-sales');
    const topSkus = useWidget<{ rows: { sku_code: string; name: string; units: string | number; orders: string | number; net_sales: number; margin: number; margin_pct: number }[] }>('finance/top-skus');
    const payments = useWidget<{ rows: { method: string; transactions: number; amount: number; fee: number; share_pct: number }[]; total: number }>('finance/payment-method-split');
    const refunds = useWidget<{ rows: { order_number: string; gateway: string; method: string; amount: number; processed_at: string; status: string }[]; count: number; total: number }>('finance/refunds');
    const pnl = useWidget<{ columns: PnlColumn[]; rows: PnlRow[]; totals: Record<string, number>; caveat: string; verdict: import('@/types').Verdict }>('finance/pnl');
    const cash = useWidget<{ money_in: number; cod_awaiting_remittance: number; cod_in_transit: number; returns_liability: number; working_capital_locked: number; avg_remittance_days: number; prepaid_settled: number; cod_remitted: number; caveat: string }>('finance/cash-flow');
    const gst = useWidget<{ rows: { hsn: string; gst_rate: number; units: number; taxable_value: number; tax_amount: number; cgst: number; sgst: number }[]; total_tax: number; total_taxable: number; caveat: string }>('finance/gst');
    const ledger = useWidget<{ data: { id: number; order_number: string; gateway: string; method: string; kind: string; amount: number; fee: number; status: string; processed_at: string }[] }>('finance/transactions', { per_page: 50 });

    return (
        <AppLayout title="Finance" description="What you billed, what you kept, and what it cost you">
            <Head title="Finance" />

            <PermissionGuard permission="finance.kpi_strip.view">
                <KpiStrip metrics={kpis.data} loading={kpis.loading} columns={6} />
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="finance.sales_over_time.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Net sales over time"
                        subtitle="Current period against the one before it"
                        widgetKey="finance.sales_over_time"
                        loading={salesOverTime.loading}
                        error={salesOverTime.error}
                        onRetry={salesOverTime.reload}
                        insightPayload={salesOverTime.data}
                    >
                        <ResponsiveContainer width="100%" height={270}>
                            <AreaChart data={salesOverTime.data ?? []} margin={{ top: 4, right: 6, bottom: 0, left: 4 }}>
                                <defs>
                                    <linearGradient id="fin-net" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.3} />
                                        <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={28} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisCurrency} width={56} />
                                <RTooltip content={<ChartTooltip />} />
                                <Area type="monotone" dataKey="net_sales" name="Net sales" stroke="var(--chart-1)" strokeWidth={2} fill="url(#fin-net)" isAnimationActive={false} />
                                <Line type="monotone" dataKey="prev_net_sales" name="Previous period" stroke="var(--chart-7)" strokeWidth={1.5} strokeDasharray="4 4" dot={false} isAnimationActive={false} />
                            </AreaChart>
                        </ResponsiveContainer>
                        <ChartLegend items={[{ label: 'Net sales', color: 'var(--chart-1)' }, { label: 'Previous period', color: 'var(--chart-7)' }]} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="finance.cash_flow.view">
                    <ChartCard
                        title="Cash flow"
                        subtitle="Money in vs money stuck"
                        widgetKey="finance.cash_flow"
                        loading={cash.loading}
                        error={cash.error}
                        onRetry={cash.reload}
                        caveat={cash.data?.caveat}
                    >
                        <div className="space-y-3">
                            <div className="rounded-lg border border-good/25 bg-good-soft/40 p-3">
                                <p className="text-[11px] uppercase tracking-wide text-good/80">Money in</p>
                                <p className="text-lg font-semibold tnum text-good">{formatCurrency(cash.data?.money_in ?? 0)}</p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                    {formatCompactCurrency(cash.data?.prepaid_settled ?? 0)} prepaid · {formatCompactCurrency(cash.data?.cod_remitted ?? 0)} COD remitted
                                </p>
                            </div>

                            <div className="rounded-lg border border-warn/25 bg-warn-soft/40 p-3">
                                <p className="text-[11px] uppercase tracking-wide text-warn/90">Working capital locked</p>
                                <p className="text-lg font-semibold tnum text-warn">{formatCurrency(cash.data?.working_capital_locked ?? 0)}</p>
                                <dl className="mt-1.5 space-y-1 text-[11px]">
                                    {[
                                        ['COD collected, not remitted', cash.data?.cod_awaiting_remittance ?? 0],
                                        ['COD still in transit', cash.data?.cod_in_transit ?? 0],
                                        ['Refund liability on open returns', cash.data?.returns_liability ?? 0],
                                    ].map(([label, value]) => (
                                        <div key={label as string} className="flex justify-between gap-2">
                                            <dt className="text-muted-foreground">{label}</dt>
                                            <dd className="tnum">{formatCompactCurrency(value as number)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                COD takes <span className="font-semibold tnum text-foreground">{cash.data?.avg_remittance_days ?? 0} days</span> on average to reach your account.
                            </p>
                        </div>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="finance.pnl.view">
                <ChartCard
                    title="P&L statement"
                    subtitle="Revenue down to EBITDA, month by month"
                    widgetKey="finance.pnl"
                    tooltip="Contribution margin comes from order-level economics; ad spend and fixed opex are added here because neither belongs to a single order."
                    loading={pnl.loading}
                    error={pnl.error}
                    onRetry={pnl.reload}
                    verdict={pnl.data?.verdict}
                    caveat={pnl.data?.caveat}
                    insightPayload={cash.data}
                >
                    <div className="overflow-x-auto scrollbar-thin">
                        <table className="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr className="border-b border-border">
                                    <th className="sticky left-0 bg-card py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Line</th>
                                    {(pnl.data?.columns ?? []).map((column) => (
                                        <th key={column.key} className="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                            {column.month}
                                        </th>
                                    ))}
                                    <th className="px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-foreground">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(pnl.data?.rows ?? []).map((row) => (
                                    <tr
                                        key={row.key}
                                        className={cn(
                                            'border-b border-border/50 last:border-0',
                                            row.kind === 'subtotal' && 'bg-muted/40 font-medium',
                                            row.kind === 'total' && 'border-t-2 border-border bg-accent/40 font-semibold',
                                        )}
                                    >
                                        <td className={cn('sticky left-0 bg-inherit py-2 pr-3', row.indent && 'pl-4 text-muted-foreground')}>{row.label}</td>
                                        {(pnl.data?.columns ?? []).map((column) => {
                                            const value = Number(column[row.key] ?? 0);
                                            return (
                                                <td
                                                    key={column.key}
                                                    className={cn(
                                                        'px-3 py-2 text-right tnum',
                                                        row.kind === 'negative' && value !== 0 && 'text-bad',
                                                        row.kind === 'total' && (value >= 0 ? 'text-good' : 'text-bad'),
                                                    )}
                                                >
                                                    {formatCompactCurrency(value)}
                                                </td>
                                            );
                                        })}
                                        <td
                                            className={cn(
                                                'px-3 py-2 text-right font-medium tnum',
                                                row.kind === 'negative' && 'text-bad',
                                                row.kind === 'total' && ((pnl.data?.totals[row.key] ?? 0) >= 0 ? 'text-good' : 'text-bad'),
                                            )}
                                        >
                                            {formatCompactCurrency(pnl.data?.totals[row.key] ?? 0)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </ChartCard>
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="finance.revenue_breakdown.view">
                    <ChartCard
                        title="Revenue breakdown"
                        subtitle="Gross to contribution margin"
                        widgetKey="finance.revenue_breakdown"
                        loading={breakdown.loading}
                        error={breakdown.error}
                        onRetry={breakdown.reload}
                        exportDataset="sales_summary"
                    >
                        <WaterfallChart steps={breakdown.data?.steps ?? []} />
                        <WaterfallLegend steps={breakdown.data?.steps ?? []} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="finance.aov_trend.view">
                    <ChartCard
                        title="AOV over time"
                        subtitle="Current vs previous period"
                        widgetKey="finance.aov_trend"
                        loading={aov.loading}
                        error={aov.error}
                        onRetry={aov.reload}
                    >
                        <ResponsiveContainer width="100%" height={250}>
                            <AreaChart data={aov.data ?? []} margin={{ top: 4, right: 6, bottom: 0, left: 4 }}>
                                <defs>
                                    <linearGradient id="fin-aov" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-2)" stopOpacity={0.3} />
                                        <stop offset="100%" stopColor="var(--chart-2)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={28} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisCurrency} width={56} />
                                <RTooltip content={<ChartTooltip />} />
                                <Area type="monotone" dataKey="aov" name="AOV" stroke="var(--chart-2)" strokeWidth={2} fill="url(#fin-aov)" isAnimationActive={false} />
                                <Line type="monotone" dataKey="prev_aov" name="Previous" stroke="var(--chart-7)" strokeWidth={1.5} strokeDasharray="4 4" dot={false} isAnimationActive={false} />
                            </AreaChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="finance.geographic_sales.view">
                    <ChartCard
                        title="Geographic sales"
                        subtitle="Realised net sales by state"
                        widgetKey="finance.geographic_sales"
                        loading={geo.loading}
                        error={geo.error}
                        onRetry={geo.reload}
                        exportDataset="geo_states"
                    >
                        <BarList
                            rows={(geo.data?.rows ?? []).slice(0, 12).map((row) => ({
                                label: row.state,
                                value: row.net_sales,
                                share: row.share_pct,
                                secondary: formatPercent(row.margin_pct),
                                tone: row.margin_pct < 0 ? 'bad' : 'neutral',
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="finance.payment_method_split.view">
                    <ChartCard
                        title="Payment method split"
                        subtitle="Where the money came from"
                        widgetKey="finance.payment_method_split"
                        loading={payments.loading}
                        error={payments.error}
                        onRetry={payments.reload}
                    >
                        <BarList
                            rows={(payments.data?.rows ?? []).map((row, index) => ({
                                label: row.method,
                                value: row.amount,
                                share: row.share_pct,
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: `${formatNumber(row.transactions)} txns`,
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="finance.gst.view">
                    <ChartCard
                        title="GST summary"
                        subtitle="Output tax by HSN"
                        widgetKey="finance.gst"
                        loading={gst.loading}
                        error={gst.error}
                        onRetry={gst.reload}
                        caveat={gst.data?.caveat}
                        exportDataset="gst_summary"
                    >
                        <DataTable
                            dense
                            rows={gst.data?.rows ?? []}
                            rowKey={(row) => `${row.hsn}-${row.gst_rate}`}
                            columns={[
                                { key: 'hsn', header: 'HSN', value: (r) => r.hsn, render: (r) => <span className="font-medium">{r.hsn}</span> },
                                { key: 'rate', header: 'Rate', align: 'right', value: (r) => r.gst_rate, render: (r) => `${r.gst_rate}%` },
                                { key: 'taxable', header: 'Taxable', align: 'right', sortable: true, value: (r) => r.taxable_value, render: (r) => formatCompactCurrency(r.taxable_value) },
                                { key: 'tax', header: 'Tax', align: 'right', sortable: true, value: (r) => r.tax_amount, render: (r) => formatCompactCurrency(r.tax_amount) },
                            ]}
                            footer={
                                <tr>
                                    <td className="px-2.5 py-2 text-xs font-semibold" colSpan={2}>Total</td>
                                    <td className="px-2.5 py-2 text-right text-xs font-semibold tnum">{formatCompactCurrency(gst.data?.total_taxable ?? 0)}</td>
                                    <td className="px-2.5 py-2 text-right text-xs font-semibold tnum">{formatCompactCurrency(gst.data?.total_tax ?? 0)}</td>
                                </tr>
                            }
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="finance.top_skus.view">
                    <ChartCard
                        title="Top SKUs by net sales"
                        subtitle="Units, orders and the margin behind them"
                        widgetKey="finance.top_skus"
                        loading={topSkus.loading}
                        error={topSkus.error}
                        onRetry={topSkus.reload}
                        exportDataset="top_skus"
                    >
                        <DataTable
                            dense
                            rows={topSkus.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'units', header: 'Units', align: 'right', sortable: true, value: (r) => Number(r.units), render: (r) => formatNumber(Number(r.units)) },
                                { key: 'net', header: 'Net sales', align: 'right', sortable: true, value: (r) => r.net_sales, render: (r) => formatCompactCurrency(r.net_sales) },
                                { key: 'margin', header: 'Margin', align: 'right', sortable: true, value: (r) => r.margin, render: (r) => (
                                    <span className={r.margin < 0 ? 'font-medium text-bad' : ''}>
                                        {formatCompactCurrency(r.margin)}
                                        <span className="ml-1 text-[10px] text-muted-foreground">{formatPercent(r.margin_pct)}</span>
                                    </span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="finance.refunds.view">
                    <ChartCard
                        title="Refunds"
                        subtitle={`${formatNumber(refunds.data?.count ?? 0)} refunds · ${formatCurrency(refunds.data?.total ?? 0)}`}
                        widgetKey="finance.refunds"
                        loading={refunds.loading}
                        error={refunds.error}
                        onRetry={refunds.reload}
                        exportDataset="transactions"
                    >
                        <DataTable
                            dense
                            rows={refunds.data?.rows ?? []}
                            rowKey={(row, index) => `${row.order_number}-${index}`}
                            columns={[
                                { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => <span className="font-medium">{r.order_number ?? '—'}</span> },
                                { key: 'gateway', header: 'Gateway', value: (r) => r.gateway, render: (r) => r.gateway ?? '—' },
                                { key: 'when', header: 'Processed', value: (r) => r.processed_at, render: (r) => <span className="text-muted-foreground">{formatDateTime(r.processed_at)}</span> },
                                { key: 'amount', header: 'Amount', align: 'right', sortable: true, value: (r) => r.amount, render: (r) => <span className="text-bad">{formatCurrency(r.amount)}</span> },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="finance.transaction_ledger.view">
                <ChartCard
                    title="Transaction ledger"
                    subtitle="Every payment, refund and failure"
                    widgetKey="finance.transaction_ledger"
                    loading={ledger.loading}
                    error={ledger.error}
                    onRetry={ledger.reload}
                    exportDataset="transactions"
                >
                    <DataTable
                        searchable
                        searchPlaceholder="Search by order number…"
                        rows={ledger.data?.data ?? []}
                        rowKey={(row) => row.id}
                        columns={[
                            { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => <span className="font-medium">{r.order_number ?? '—'}</span> },
                            { key: 'gateway', header: 'Gateway', value: (r) => r.gateway, render: (r) => r.gateway ?? '—' },
                            { key: 'method', header: 'Method', value: (r) => r.method, render: (r) => r.method ?? '—' },
                            { key: 'kind', header: 'Kind', value: (r) => r.kind, render: (r) => <Badge variant={r.kind === 'refund' ? 'bad' : 'muted'}>{r.kind}</Badge> },
                            { key: 'status', header: 'Status', value: (r) => r.status, render: (r) => (
                                <Badge variant={r.status === 'success' ? 'good' : 'bad'}>{r.status}</Badge>
                            ) },
                            { key: 'when', header: 'Processed', value: (r) => r.processed_at, render: (r) => <span className="text-muted-foreground">{formatDateTime(r.processed_at)}</span> },
                            { key: 'fee', header: 'Fee', align: 'right', sortable: true, value: (r) => r.fee, render: (r) => formatCurrency(r.fee) },
                            { key: 'amount', header: 'Amount', align: 'right', sortable: true, value: (r) => r.amount, render: (r) => (
                                <span className={r.amount < 0 ? 'text-bad' : ''}>{formatCurrency(r.amount)}</span>
                            ) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}

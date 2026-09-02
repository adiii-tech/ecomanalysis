import { Head, Link } from '@inertiajs/react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip as RTooltip, XAxis, YAxis } from 'recharts';
import { Filter } from 'lucide-react';
import { AppLayout } from '@/layouts/app-layout';
import { StatStrip } from '@/components/app/stat-strip';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { CaveatNote } from '@/components/app/caveat-note';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { AXIS_PROPS, CHART_COLORS, ChartTooltip, GRID_PROPS, axisNumber, axisPercent } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatDate, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Caveat, Verdict } from '@/types';

interface CustomerRow {
    id: number;
    name: string | null;
    email: string | null;
    city: string | null;
    state: string | null;
    orders_count: number;
    total_spent: number;
    aov: number;
    ltv: number;
    returns_count: number;
    last_order_at: string | null;
    rfm_label: string | null;
    is_vip: boolean;
    churn_risk_score: number | null;
}

interface CohortRow {
    cohort_month: string;
    cohort_size: number;
    cells: ({ month_index: number; retention_pct: number; active_customers: number; avg_ltv: number } | null)[];
}

function retentionColor(pct: number): string {
    // A single hue, darkening with retention — easier to read than a rainbow.
    const alpha = Math.min(Math.max(pct / 60, 0.06), 1);
    return `color-mix(in oklch, var(--chart-1) ${Math.round(alpha * 100)}%, transparent)`;
}

export default function Customers() {
    const kpis = useWidget<Record<string, unknown>>('customers/kpis');
    const list = useWidget<{ data: CustomerRow[] }>('customers/list', { per_page: 50 });
    const rfm = useWidget<{ rows: { segment: string; label: string; color: string; playbook: string; customers: number; revenue: number; avg_ltv: number; customer_share_pct: number; revenue_share_pct: number }[]; caveat: Caveat; verdict: Verdict }>('customers/rfm');
    const cohorts = useWidget<{ cohorts: CohortRow[]; months: number; caveat: string; verdict: Verdict }>('customers/cohorts');
    const repeat = useWidget<{ repeat_purchase_rate: number; avg_orders_per_customer: number; second_order_within_90d_pct: number; avg_days_to_second_order: number; caveat: Caveat }>('customers/repeat-metrics');
    const churn = useWidget<{ rows: CustomerRow[]; count: number; value_at_risk: number; caveat: string; verdict: Verdict }>('customers/churn');
    const vip = useWidget<{ rows: CustomerRow[]; count: number; total_value: number }>('customers/vip');
    const ltv = useWidget<{ rows: { label: string; customers: number; revenue: number; customer_share_pct: number; revenue_share_pct: number }[] }>('customers/ltv-distribution');
    const interval = useWidget<{ rows: { label: string; customers: number }[]; caveat: string }>('customers/purchase-interval');
    const returners = useWidget<{ rows: (CustomerRow & { return_rate_pct: number })[]; count: number; verdict: Verdict }>('customers/per-customer-returns');
    const geo = useWidget<{ rows: { state: string; customers: number; revenue: number; avg_ltv: number }[] }>('customers/geo');
    const reviews = useWidget<{ total: number; avg_rating: number; positive_pct: number; negative_pct: number; with_photos_pct: number; distribution: { rating: number; count: number; pct: number }[]; caveat: string }>('reviews/summary');
    const recentReviews = useWidget<{ data: { id: number; rating: number; title: string | null; body: string | null; reviewer: string | null; verified: boolean; reviewed_at: string; sku_code: string | null; sentiment: string | null }[] }>('reviews/recent', { per_page: 20 });
    const correlation = useWidget<{ rows: { sku_code: string; name: string; avg_rating: number; review_count: number; return_rate: number; expectation_gap: boolean }[]; caveat: string; verdict: Verdict }>('reviews/return-correlation');

    const customerColumns = [
        { key: 'name', header: 'Customer', value: (r: CustomerRow) => r.name ?? r.email ?? '', render: (r: CustomerRow) => (
            <Link href={`/customers/${r.id}`} className="min-w-0 hover:underline">
                <p className="truncate font-medium">{r.name ?? '—'}</p>
                <p className="truncate text-[11px] text-muted-foreground">{r.email ?? '—'}</p>
            </Link>
        ) },
        { key: 'location', header: 'Location', value: (r: CustomerRow) => `${r.city ?? ''} ${r.state ?? ''}`, render: (r: CustomerRow) => (
            <span className="text-muted-foreground">{[r.city, r.state].filter(Boolean).join(', ') || '—'}</span>
        ) },
        { key: 'segment', header: 'Segment', value: (r: CustomerRow) => r.rfm_label, render: (r: CustomerRow) => (
            r.rfm_label ? <Badge variant={r.is_vip ? 'good' : 'muted'}>{r.rfm_label}</Badge> : '—'
        ) },
        { key: 'orders', header: 'Orders', align: 'right' as const, sortable: true, value: (r: CustomerRow) => r.orders_count, render: (r: CustomerRow) => formatNumber(r.orders_count) },
        { key: 'aov', header: 'AOV', align: 'right' as const, sortable: true, value: (r: CustomerRow) => r.aov, render: (r: CustomerRow) => formatCurrency(r.aov) },
        { key: 'spent', header: 'Total spent', align: 'right' as const, sortable: true, value: (r: CustomerRow) => r.total_spent, render: (r: CustomerRow) => <span className="font-medium">{formatCurrency(r.total_spent)}</span> },
        { key: 'last', header: 'Last order', align: 'right' as const, sortable: true, value: (r: CustomerRow) => r.last_order_at, render: (r: CustomerRow) => (
            <span className="text-muted-foreground">{r.last_order_at ? formatDate(r.last_order_at) : '—'}</span>
        ) },
    ];

    return (
        <AppLayout
            title="Customers & Reviews"
            description="Who buys again, who is about to leave, and what they say"
            actions={
                <PermissionGuard permission="customer_intelligence.explorer.view">
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/customers/explorer">
                            <Filter className="size-3.5" /> Explorer
                        </Link>
                    </Button>
                </PermissionGuard>
            }
        >
            <Head title="Customers & Reviews" />

            <PermissionGuard permission="customer_intelligence.kpi_strip.view">
                <div className="space-y-2">
                    <StatStrip stats={kpis.data ?? null} loading={kpis.loading} columns={6} />
                    <CaveatNote caveat={(kpis.data?.caveat as Caveat) ?? null} />
                </div>
            </PermissionGuard>

            <PermissionGuard permission="customer_intelligence.cohorts.view">
                <ChartCard
                    title="Cohort retention"
                    subtitle="Acquisition month × months since first order"
                    widgetKey="customer_intelligence.cohorts"
                    tooltip="Each row is the customers who first bought in that month. Each cell is the share of them who ordered again that many months later."
                    loading={cohorts.loading}
                    error={cohorts.error}
                    onRetry={cohorts.reload}
                    verdict={cohorts.data?.verdict}
                    caveat={cohorts.data?.caveat}
                    exportDataset="cohort_retention"
                    empty={(cohorts.data?.cohorts.length ?? 0) === 0}
                >
                    <div className="overflow-x-auto scrollbar-thin">
                        <table className="w-full min-w-[620px] border-collapse text-xs">
                            <thead>
                                <tr>
                                    <th className="py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Cohort</th>
                                    <th className="px-2 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Size</th>
                                    {Array.from({ length: (cohorts.data?.months ?? 12) + 1 }).map((_, index) => (
                                        <th key={index} className="px-1 py-2 text-center text-[11px] font-semibold text-muted-foreground">M{index}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {(cohorts.data?.cohorts ?? []).map((cohort) => (
                                    <tr key={cohort.cohort_month}>
                                        <td className="py-1 pr-2 font-medium">{cohort.cohort_month}</td>
                                        <td className="px-2 py-1 text-right tnum text-muted-foreground">{formatNumber(cohort.cohort_size)}</td>
                                        {cohort.cells.map((cell, index) => (
                                            <td key={index} className="p-0.5">
                                                {cell ? (
                                                    <div
                                                        className="rounded px-1 py-1.5 text-center tnum"
                                                        style={{ background: retentionColor(cell.retention_pct) }}
                                                        title={`${formatNumber(cell.active_customers)} customers · avg LTV ${formatCompactCurrency(cell.avg_ltv)}`}
                                                    >
                                                        {cell.retention_pct >= 0.5 ? `${Math.round(cell.retention_pct)}%` : '·'}
                                                    </div>
                                                ) : (
                                                    <div className="py-1.5 text-center text-muted-foreground/30">–</div>
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </ChartCard>
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="customer_intelligence.rfm.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="RFM segmentation"
                        subtitle="Recency, frequency and monetary quintiles"
                        widgetKey="customer_intelligence.rfm"
                        loading={rfm.loading}
                        error={rfm.error}
                        onRetry={rfm.reload}
                        verdict={rfm.data?.verdict}
                        caveat={rfm.data?.caveat}
                        exportDataset="customers"
                    >
                        <div className="grid gap-2 sm:grid-cols-2">
                            {(rfm.data?.rows ?? []).map((row) => (
                                <div key={row.segment} className="rounded-lg border border-border p-3">
                                    <div className="flex items-baseline justify-between gap-2">
                                        <span className="flex items-center gap-1.5 text-xs font-semibold">
                                            <span className="size-2 rounded-full" style={{ background: row.color }} />
                                            {row.label}
                                        </span>
                                        <span className="text-[11px] tnum text-muted-foreground">{formatNumber(row.customers)}</span>
                                    </div>
                                    <p className="mt-1 text-sm font-semibold tnum">{formatCompactCurrency(row.revenue)}</p>
                                    <p className="text-[11px] text-muted-foreground">
                                        {formatPercent(row.revenue_share_pct, 0)} of revenue · avg LTV {formatCompactCurrency(row.avg_ltv)}
                                    </p>
                                    <p className="mt-1.5 text-[11px] leading-snug text-muted-foreground">{row.playbook}</p>
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="customer_intelligence.repeat_metrics.view">
                    <ChartCard
                        title="Repeat metrics"
                        widgetKey="customer_intelligence.repeat_metrics"
                        loading={repeat.loading}
                        error={repeat.error}
                        onRetry={repeat.reload}
                        caveat={repeat.data?.caveat}
                    >
                        <dl className="space-y-2.5">
                            {[
                                ['Repeat purchase rate', formatPercent(repeat.data?.repeat_purchase_rate ?? 0)],
                                ['Avg orders / customer', (repeat.data?.avg_orders_per_customer ?? 0).toFixed(2)],
                                ['2nd order within 90 days', formatPercent(repeat.data?.second_order_within_90d_pct ?? 0)],
                                ['Avg days to 2nd order', `${repeat.data?.avg_days_to_second_order ?? 0}d`],
                            ].map(([label, value]) => (
                                <div key={label} className="flex items-baseline justify-between gap-3 border-b border-border/50 pb-2 last:border-0">
                                    <dt className="text-xs text-muted-foreground">{label}</dt>
                                    <dd className="text-sm font-semibold tnum">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="customer_intelligence.ltv_distribution.view">
                    <ChartCard title="LTV distribution" widgetKey="customer_intelligence.ltv_distribution" loading={ltv.loading} error={ltv.error} onRetry={ltv.reload}>
                        <ResponsiveContainer width="100%" height={220}>
                            <BarChart data={ltv.data?.rows ?? []} margin={{ top: 4, right: 4, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="label" {...AXIS_PROPS} interval={0} angle={-25} textAnchor="end" height={54} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisNumber} width={40} />
                                <RTooltip content={<ChartTooltip format="number" labelFormatter={(l) => l} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="customers" name="Customers" fill="var(--chart-1)" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="customer_intelligence.purchase_interval.view">
                    <ChartCard title="Purchase interval" subtitle="Average gap between orders" widgetKey="customer_intelligence.purchase_interval" loading={interval.loading} error={interval.error} onRetry={interval.reload} caveat={interval.data?.caveat}>
                        <BarList
                            format="number"
                            rows={(interval.data?.rows ?? []).map((row, index) => ({ label: row.label, value: row.customers, color: CHART_COLORS[index % CHART_COLORS.length] }))}
                            emptyLabel="No customer has two orders yet."
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="customer_intelligence.geo.view">
                    <ChartCard title="Customers by state" widgetKey="customer_intelligence.geo" loading={geo.loading} error={geo.error} onRetry={geo.reload} exportDataset="customers">
                        <BarList
                            rows={(geo.data?.rows ?? []).slice(0, 10).map((row) => ({
                                label: row.state,
                                value: row.revenue,
                                secondary: `${formatNumber(row.customers)} cust`,
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="customer_intelligence.churn.view">
                    <ChartCard
                        title="Churn risk"
                        subtitle="Repeat customers overdue on their own cadence"
                        widgetKey="customer_intelligence.churn"
                        loading={churn.loading}
                        error={churn.error}
                        onRetry={churn.reload}
                        verdict={churn.data?.verdict}
                        caveat={churn.data?.caveat}
                        exportDataset="customers"
                        empty={(churn.data?.count ?? 0) === 0}
                    >
                        <DataTable<CustomerRow>
                            dense
                            rows={churn.data?.rows ?? []}
                            rowKey={(row) => row.id}
                            columns={[
                                customerColumns[0],
                                { key: 'risk', header: 'Risk', align: 'right', sortable: true, value: (r) => r.churn_risk_score ?? 0, render: (r) => (
                                    <Badge variant={(r.churn_risk_score ?? 0) >= 80 ? 'bad' : 'warn'}>{r.churn_risk_score}</Badge>
                                ) },
                                customerColumns[5],
                                customerColumns[6],
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="customer_intelligence.vip.view">
                    <ChartCard
                        title="VIP customers"
                        subtitle={`${formatNumber(vip.data?.count ?? 0)} champions worth ${formatCompactCurrency(vip.data?.total_value ?? 0)}`}
                        widgetKey="customer_intelligence.vip"
                        loading={vip.loading}
                        error={vip.error}
                        onRetry={vip.reload}
                        exportDataset="customers"
                    >
                        <DataTable<CustomerRow>
                            dense
                            rows={vip.data?.rows ?? []}
                            rowKey={(row) => row.id}
                            columns={[customerColumns[0], customerColumns[3], customerColumns[5], customerColumns[6]]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="customer_intelligence.top_customers.view">
                <ChartCard
                    title="All customers"
                    subtitle="Sorted by lifetime spend"
                    widgetKey="customer_intelligence.top_customers"
                    loading={list.loading}
                    error={list.error}
                    onRetry={list.reload}
                    exportDataset="customers"
                >
                    <DataTable<CustomerRow>
                        searchable
                        searchPlaceholder="Search name, email, city…"
                        rows={list.data?.data ?? []}
                        rowKey={(row) => row.id}
                        columns={customerColumns}
                    />
                </ChartCard>
            </PermissionGuard>

            <PermissionGuard permission="customer_intelligence.serial_returners.view">
                <ChartCard
                    title="Serial returners"
                    subtitle="Two or more returns"
                    widgetKey="customer_intelligence.serial_returners"
                    loading={returners.loading}
                    error={returners.error}
                    onRetry={returners.reload}
                    verdict={returners.data?.verdict}
                    exportDataset="customers"
                    empty={(returners.data?.count ?? 0) === 0}
                >
                    <DataTable
                        dense
                        rows={returners.data?.rows ?? []}
                        rowKey={(row) => row.id}
                        columns={[
                            customerColumns[0] as never,
                            { key: 'orders', header: 'Orders', align: 'right', sortable: true, value: (r) => r.orders_count, render: (r) => formatNumber(r.orders_count) },
                            { key: 'returns', header: 'Returns', align: 'right', sortable: true, value: (r) => r.returns_count, render: (r) => formatNumber(r.returns_count) },
                            { key: 'rate', header: 'Return rate', align: 'right', sortable: true, value: (r) => r.return_rate_pct, render: (r) => (
                                <span className="font-medium text-bad">{formatPercent(r.return_rate_pct)}</span>
                            ) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="reviews.summary.view">
                    <ChartCard
                        title="Rating summary"
                        subtitle={`${formatNumber(reviews.data?.total ?? 0)} reviews`}
                        widgetKey="reviews.summary"
                        loading={reviews.loading}
                        error={reviews.error}
                        onRetry={reviews.reload}
                        caveat={reviews.data?.caveat}
                    >
                        <div className="flex items-baseline gap-2">
                            <p className="text-3xl font-semibold tracking-tight tnum">{(reviews.data?.avg_rating ?? 0).toFixed(2)}</p>
                            <p className="text-xs text-muted-foreground">average rating</p>
                        </div>
                        <div className="space-y-1.5">
                            {(reviews.data?.distribution ?? []).map((row) => (
                                <div key={row.rating} className="flex items-center gap-2">
                                    <span className="w-6 text-[11px] tnum text-muted-foreground">{row.rating}★</span>
                                    <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full"
                                            style={{ width: `${Math.max(row.pct, 0.5)}%`, background: row.rating >= 4 ? 'var(--good)' : row.rating === 3 ? 'var(--warn)' : 'var(--bad)' }}
                                        />
                                    </div>
                                    <span className="w-10 text-right text-[11px] tnum text-muted-foreground">{formatNumber(row.count)}</span>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {formatPercent(reviews.data?.positive_pct ?? 0)} positive · {formatPercent(reviews.data?.with_photos_pct ?? 0)} with photos
                        </p>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="reviews.return_correlation.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Review vs return"
                        subtitle="Well-rated products that still come back"
                        widgetKey="reviews.return_correlation"
                        tooltip="A high rating with a high return rate usually means the product is fine but the listing set the wrong expectation."
                        loading={correlation.loading}
                        error={correlation.error}
                        onRetry={correlation.reload}
                        verdict={correlation.data?.verdict}
                        caveat={correlation.data?.caveat}
                    >
                        <DataTable
                            dense
                            rows={correlation.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-1.5 truncate font-medium">
                                            {r.sku_code}
                                            {r.expectation_gap && <Badge variant="warn">expectation gap</Badge>}
                                        </p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'rating', header: 'Rating', align: 'right', sortable: true, value: (r) => r.avg_rating, render: (r) => `${r.avg_rating.toFixed(2)}★` },
                                { key: 'reviews', header: 'Reviews', align: 'right', sortable: true, value: (r) => r.review_count, render: (r) => formatNumber(r.review_count) },
                                { key: 'return', header: 'Return %', align: 'right', sortable: true, value: (r) => r.return_rate, render: (r) => (
                                    <span className={r.return_rate >= 15 ? 'font-medium text-bad' : ''}>{formatPercent(r.return_rate)}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="reviews.recent.view">
                <ChartCard title="Recent reviews" widgetKey="reviews.recent" loading={recentReviews.loading} error={recentReviews.error} onRetry={recentReviews.reload}>
                    <div className="grid gap-2 md:grid-cols-2">
                        {(recentReviews.data?.data ?? []).map((review) => (
                            <Card key={review.id} className="p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-1.5 text-xs font-medium">
                                            <span className={cn(review.rating >= 4 ? 'text-good' : review.rating === 3 ? 'text-warn' : 'text-bad')}>
                                                {'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}
                                            </span>
                                            {review.verified && <Badge variant="muted">verified</Badge>}
                                        </p>
                                        <p className="mt-1 truncate text-xs font-semibold">{review.title ?? '—'}</p>
                                    </div>
                                    <span className="shrink-0 text-[10px] text-muted-foreground">{formatDate(review.reviewed_at)}</span>
                                </div>
                                <p className="mt-1 line-clamp-2 text-[11px] leading-snug text-muted-foreground">{review.body}</p>
                                <p className="mt-1.5 text-[10px] text-muted-foreground">
                                    {review.reviewer ?? 'Anonymous'}{review.sku_code ? ` · ${review.sku_code}` : ''}
                                </p>
                            </Card>
                        ))}
                    </div>
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}

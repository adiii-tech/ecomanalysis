import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ComposedChart,
    Line,
    ReferenceLine,
    ResponsiveContainer,
    Scatter,
    ScatterChart,
    Tooltip as RTooltip,
    XAxis,
    YAxis,
    ZAxis,
} from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { KpiStrip } from '@/components/app/kpi-card';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { ReturnsBasisToggle } from '@/components/app/returns-basis-toggle';
import { DataTable, type Column } from '@/components/app/data-table';
import { DrilldownDrawer } from '@/components/app/drilldown-drawer';
import { EmptyState } from '@/components/app/empty-state';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { HealthFlags, type HealthFlag } from '@/components/dashboard/health-flags';
import { SalesSummaryTable, type SummaryRow } from '@/components/dashboard/sales-summary-table';
import { SalesJourney, type JourneyStep } from '@/components/dashboard/sales-journey';
import { WaterfallChart, WaterfallLegend } from '@/components/charts/waterfall-chart';
import { AXIS_PROPS, CHART_COLORS, ChartLegend, ChartTooltip, GRID_PROPS, axisCurrency, axisDate, axisNumber, axisPercent } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatDateTime, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Metric, Verdict } from '@/types';

interface ChannelRow {
    channel_id: number;
    name: string;
    code: string;
    type: string;
    color: string | null;
    orders: number;
    net_sales: number;
    margin: number;
    margin_pct: number;
    share_pct: number;
    aov: number;
    return_pct: number;
    rto_pct: number;
}

interface PaymentRow {
    mode: string;
    label: string;
    orders: number;
    net_sales: number;
    cogs: number;
    fees: number;
    logistics: number;
    net_margin: number;
    net_margin_pct: number;
    share_of_sales: number;
    share_of_orders: number;
    rto_orders: number;
    rto_pct: number;
    return_pct: number;
    aov: number;
}

interface OrderRow {
    id: number;
    order_number: string;
    placed_at: string;
    status_label: string;
    payment_mode: string;
    channel: { name: string; color: string | null } | null;
    shipping_state: string | null;
    net_amount: number;
    cogs_amount: number;
    fees_amount: number;
    logistics_amount: number;
    contribution_margin: number;
    margin_pct: number | null;
    units: number;
    is_rto: boolean;
}

interface StateRow {
    state: string;
    orders: number;
    net_sales: number;
    share_pct: number;
    margin_pct: number;
}

interface MatrixRow {
    state: string;
    orders: number;
    net_sales: number;
    margin_pct: number;
    rto_pct: number;
    cod_share_pct: number;
    quadrant: 'scale' | 'fix' | 'grow' | 'restrict';
    action: string;
}

const QUADRANT_COLOR: Record<string, string> = {
    scale: 'var(--good)',
    grow: 'var(--chart-2)',
    fix: 'var(--warn)',
    restrict: 'var(--bad)',
};

export default function Dashboard() {
    const [salesMetric, setSalesMetric] = useState<'invoiced_sales' | 'net_sales' | 'orders_count'>('invoiced_sales');
    const [summaryTab, setSummaryTab] = useState<'table' | 'waterfall'>('table');
    const [drilldown, setDrilldown] = useState<null | 'loss_orders' | 'recent_orders'>(null);

    const kpis = useWidget<Metric[]>('dashboard/kpis');
    const flags = useWidget<{ rows: HealthFlag[] }>('dashboard/health-flags');
    const channelDaily = useWidget<{ series: Record<string, number | string>[]; channels: { code: string; name: string; color: string }[] }>(
        'dashboard/sales-by-channel-daily',
        { metric: salesMetric },
    );
    const summary = useWidget<{ rows: SummaryRow[]; leakage_pct: number; verdict: Verdict }>('dashboard/sales-summary');
    const waterfall = useWidget<{ rows: SummaryRow[] }>('dashboard/sales-summary');
    const journey = useWidget<JourneyStep[]>('dashboard/sales-journey');
    const marginTrend = useWidget<{ date: string; net_sales: number; contribution_margin: number; margin_pct: number }[]>('dashboard/revenue-margin-trend');
    const payment = useWidget<{ rows: PaymentRow[]; verdict: Verdict }>('dashboard/payment-mode-economics');
    const channelMix = useWidget<{ rows: ChannelRow[]; verdict: Verdict }>('dashboard/channel-mix');
    const topStates = useWidget<{ rows: StateRow[] }>('dashboard/top-states');
    const categories = useWidget<{ rows: { category: string; units: number; net_sales: number; margin_pct: number; share_pct: number }[] }>('dashboard/top-categories');
    const rtoStates = useWidget<{ rows: { state: string; orders: number; rto_count: number; rto_pct: number; cod_share_pct: number }[]; threshold: number; caveat: string; verdict: Verdict }>('dashboard/top-rto-states');
    const matrix = useWidget<{ rows: MatrixRow[]; rto_threshold: number }>('dashboard/state-action-matrix');
    const pareto = useWidget<{ rows: { rank: number; sku_code: string; name: string; margin: number; cumulative_pct: number }[]; pareto_sku_count: number; sku_count: number; verdict: Verdict }>('dashboard/sku-pareto');
    const lossOrders = useWidget<{ rows: OrderRow[]; count: number; total_loss: number; verdict: Verdict }>('dashboard/top-loss-orders');
    const recentOrders = useWidget<{ rows: OrderRow[] }>('dashboard/recent-orders');
    const returnsByChannel = useWidget<{ rows: { name: string; color: string | null; orders: number; returns_total: number; return_pct: number }[] }>('dashboard/returns-by-channel');
    const returnReasons = useWidget<{ rows: { reason_code: string; label: string; count: number; refund_amount: number }[] }>('dashboard/top-return-reasons');
    const returnSkus = useWidget<{ rows: { sku_code: string; name: string; returns_count: number; refund_amount: number }[] }>('dashboard/high-return-products');
    const pacing = useWidget<{ actual: number; target: number; projected: number; attainment_pct: number | null; series: { date: string; actual: number; target: number | null }[]; caveat: string; verdict: Verdict }>('dashboard/revenue-pacing');
    const inventory = useWidget<{ rows: { sku_code: string; name: string; stock: number; days_of_cover: number; monthly_revenue: number }[]; count: number; verdict: Verdict }>('dashboard/critical-inventory');
    const cohort = useWidget<{ series: { month_index: number; avg_retention_pct: number; avg_ltv: number }[]; m1_retention: number; target_repeat_rate: number; verdict: Verdict }>('dashboard/ltv-cohort');

    const orderColumns: Column<OrderRow>[] = [
        { key: 'order', header: 'Order', sortable: true, value: (r) => r.order_number, render: (r) => <span className="font-medium">{r.order_number}</span> },
        { key: 'placed', header: 'Placed', sortable: true, value: (r) => r.placed_at, render: (r) => <span className="text-muted-foreground">{formatDateTime(r.placed_at)}</span> },
        { key: 'channel', header: 'Channel', value: (r) => r.channel?.name ?? '', render: (r) => (
            <span className="flex items-center gap-1.5">
                <span className="size-2 rounded-full" style={{ background: r.channel?.color ?? 'var(--muted-foreground)' }} />
                {r.channel?.name ?? '—'}
            </span>
        ) },
        { key: 'state', header: 'State', value: (r) => r.shipping_state, render: (r) => r.shipping_state ?? '—' },
        { key: 'payment', header: 'Payment', value: (r) => r.payment_mode, render: (r) => (
            <Badge variant={r.payment_mode === 'cod' ? 'warn' : 'muted'}>{r.payment_mode === 'cod' ? 'COD' : 'Prepaid'}</Badge>
        ) },
        { key: 'status', header: 'Status', value: (r) => r.status_label, render: (r) => (
            <Badge variant={r.is_rto ? 'bad' : 'outline'}>{r.status_label}</Badge>
        ) },
        { key: 'net', header: 'Net', align: 'right', sortable: true, value: (r) => r.net_amount, render: (r) => formatCurrency(r.net_amount) },
        { key: 'cogs', header: 'COGS', align: 'right', sortable: true, value: (r) => r.cogs_amount, render: (r) => formatCurrency(r.cogs_amount) },
        { key: 'fees', header: 'Fees', align: 'right', sortable: true, value: (r) => r.fees_amount, render: (r) => formatCurrency(r.fees_amount) },
        { key: 'margin', header: 'Margin', align: 'right', sortable: true, value: (r) => r.contribution_margin, render: (r) => (
            <span className={r.contribution_margin < 0 ? 'font-medium text-bad' : 'text-foreground'}>
                {formatCurrency(r.contribution_margin)}
                <span className="ml-1 text-[10px] text-muted-foreground">
                    {r.margin_pct === null ? '—' : formatPercent(r.margin_pct)}
                </span>
            </span>
        ) },
    ];

    return (
        <AppLayout
            title="Command Centre"
            description="Am I actually making money?"
            filterExtras={<ReturnsBasisToggle />}
        >
            <Head title="Command Centre" />

            <PermissionGuard permission="dashboard.kpi_strip.view">
                <KpiStrip metrics={kpis.data} loading={kpis.loading} columns={6} />
            </PermissionGuard>

            <PermissionGuard permission="dashboard.health_flags.view">
                <HealthFlags flags={flags.data?.rows ?? null} loading={flags.loading} />
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="dashboard.sales_by_channel.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Sales by channel"
                        subtitle="Daily, stacked by channel"
                        widgetKey="dashboard.sales_by_channel"
                        tooltip="Invoiced sales are what you billed — after discounts and cancellations, before returns and RTO."
                        loading={channelDaily.loading}
                        error={channelDaily.error}
                        onRetry={channelDaily.reload}
                        empty={(channelDaily.data?.channels.length ?? 0) === 0}
                        insightPayload={channelDaily.data}
                        tabs={
                            <Tabs value={salesMetric} onValueChange={(value) => setSalesMetric(value as typeof salesMetric)}>
                                <TabsList>
                                    <TabsTrigger value="invoiced_sales">Invoiced</TabsTrigger>
                                    <TabsTrigger value="net_sales">Net</TabsTrigger>
                                    <TabsTrigger value="orders_count">Orders</TabsTrigger>
                                </TabsList>
                            </Tabs>
                        }
                    >
                        <ResponsiveContainer width="100%" height={260}>
                            <BarChart data={channelDaily.data?.series ?? []} margin={{ top: 4, right: 4, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={26} />
                                <YAxis {...AXIS_PROPS} tickFormatter={salesMetric === 'orders_count' ? axisNumber : axisCurrency} width={54} />
                                <RTooltip content={<ChartTooltip format={salesMetric === 'orders_count' ? 'number' : 'currency'} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                {(channelDaily.data?.channels ?? []).map((channel, index) => (
                                    <Bar
                                        key={channel.code}
                                        dataKey={channel.code}
                                        name={channel.name}
                                        stackId="channel"
                                        fill={channel.color ?? CHART_COLORS[index % CHART_COLORS.length]}
                                        radius={index === (channelDaily.data?.channels.length ?? 1) - 1 ? [3, 3, 0, 0] : 0}
                                        isAnimationActive={false}
                                    />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                        <ChartLegend
                            items={(channelDaily.data?.channels ?? []).map((channel, index) => ({
                                label: channel.name,
                                color: channel.color ?? CHART_COLORS[index % CHART_COLORS.length],
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.revenue_pacing.view">
                    <ChartCard
                        title="Revenue pacing"
                        subtitle="Month to date vs target"
                        widgetKey="dashboard.revenue_pacing"
                        loading={pacing.loading}
                        error={pacing.error}
                        onRetry={pacing.reload}
                        verdict={pacing.data?.verdict}
                        caveat={pacing.data?.caveat}
                    >
                        <div className="grid grid-cols-3 gap-2 text-center">
                            <div>
                                <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Actual</p>
                                <p className="text-sm font-semibold tnum">{formatCompactCurrency(pacing.data?.actual ?? 0)}</p>
                            </div>
                            <div>
                                <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Projected</p>
                                <p className="text-sm font-semibold tnum text-primary">{formatCompactCurrency(pacing.data?.projected ?? 0)}</p>
                            </div>
                            <div>
                                <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Target</p>
                                <p className="text-sm font-semibold tnum text-muted-foreground">{formatCompactCurrency(pacing.data?.target ?? 0)}</p>
                            </div>
                        </div>
                        <ResponsiveContainer width="100%" height={168}>
                            <AreaChart data={pacing.data?.series ?? []} margin={{ top: 8, right: 4, bottom: 0, left: 4 }}>
                                <defs>
                                    <linearGradient id="pacing-fill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.3} />
                                        <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={30} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisCurrency} width={50} />
                                <RTooltip content={<ChartTooltip />} />
                                <Area type="monotone" dataKey="actual" name="Actual" stroke="var(--chart-1)" strokeWidth={2} fill="url(#pacing-fill)" isAnimationActive={false} />
                                <Line type="monotone" dataKey="target" name="Target" stroke="var(--muted-foreground)" strokeDasharray="4 4" strokeWidth={1.5} dot={false} isAnimationActive={false} />
                            </AreaChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="dashboard.sales_summary.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Sales summary"
                        subtitle="Gross → net, line by line"
                        widgetKey="dashboard.sales_summary"
                        tooltip="Every deduction between what customers agreed to pay and what you actually keep."
                        loading={summary.loading}
                        error={summary.error}
                        onRetry={summary.reload}
                        verdict={summary.data?.verdict}
                        insightPayload={pacing.data}
                        tabs={
                            <Tabs value={summaryTab} onValueChange={(value) => setSummaryTab(value as typeof summaryTab)}>
                                <TabsList>
                                    <TabsTrigger value="table">Table</TabsTrigger>
                                    <TabsTrigger value="waterfall">Waterfall</TabsTrigger>
                                </TabsList>
                            </Tabs>
                        }
                    >
                        {summaryTab === 'table' ? (
                            <SalesSummaryTable rows={summary.data?.rows ?? []} />
                        ) : (
                            <WaterfallSection />
                        )}
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.sales_journey.view">
                    <ChartCard
                        title="Sales journey"
                        subtitle="Where the money actually went"
                        widgetKey="dashboard.sales_journey"
                        loading={journey.loading}
                        error={journey.error}
                        onRetry={journey.reload}
                        empty={(journey.data?.length ?? 0) === 0}
                    >
                        <SalesJourney steps={journey.data ?? []} />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="dashboard.payment_mode_economics.view">
                    <ChartCard
                        title="COD vs prepaid economics"
                        subtitle="The number most Indian D2C brands get wrong"
                        widgetKey="dashboard.payment_mode_economics"
                        tooltip="COD looks like revenue until you subtract RTO. This is the same money, resolved down to margin."
                        loading={payment.loading}
                        error={payment.error}
                        onRetry={payment.reload}
                        verdict={payment.data?.verdict}
                        insightPayload={journey.data}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            {(payment.data?.rows ?? []).map((row) => (
                                <div key={row.mode} className="rounded-xl border border-border p-3">
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs font-semibold">{row.label}</p>
                                        <Badge variant={row.mode === 'cod' ? 'warn' : 'default'}>{formatPercent(row.share_of_orders, 0)} of orders</Badge>
                                    </div>
                                    <dl className="mt-2 space-y-1 text-xs">
                                        {[
                                            ['Orders', formatNumber(row.orders)],
                                            ['Net sales', formatCompactCurrency(row.net_sales)],
                                            ['COGS', formatCompactCurrency(row.cogs)],
                                            ['Fees', formatCompactCurrency(row.fees)],
                                            ['Logistics', formatCompactCurrency(row.logistics)],
                                            ['AOV', formatCompactCurrency(row.aov)],
                                        ].map(([label, value]) => (
                                            <div key={label} className="flex justify-between">
                                                <dt className="text-muted-foreground">{label}</dt>
                                                <dd className="tnum">{value}</dd>
                                            </div>
                                        ))}
                                        <div className="flex justify-between border-t border-border pt-1 font-medium">
                                            <dt>Net margin</dt>
                                            <dd className={cn('tnum', row.net_margin < 0 ? 'text-bad' : 'text-good')}>
                                                {formatCompactCurrency(row.net_margin)} · {formatPercent(row.net_margin_pct)}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">RTO</dt>
                                            <dd className={cn('tnum', row.rto_pct > 15 ? 'text-bad' : 'text-muted-foreground')}>
                                                {row.rto_orders} · {formatPercent(row.rto_pct)}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.net_sales_vs_margin.view">
                    <ChartCard
                        title="Net sales vs margin"
                        subtitle="Revenue and the profit inside it"
                        widgetKey="dashboard.net_sales_vs_margin"
                        loading={marginTrend.loading}
                        error={marginTrend.error}
                        onRetry={marginTrend.reload}
                        insightPayload={marginTrend.data}
                    >
                        <ResponsiveContainer width="100%" height={260}>
                            <ComposedChart data={marginTrend.data ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={26} />
                                <YAxis yAxisId="left" {...AXIS_PROPS} tickFormatter={axisCurrency} width={54} />
                                <YAxis yAxisId="right" orientation="right" {...AXIS_PROPS} tickFormatter={axisPercent} width={40} />
                                <RTooltip content={<ChartTooltip formats={{ margin_pct: 'percent' }} />} />
                                <Area yAxisId="left" type="monotone" dataKey="net_sales" name="Net sales" stroke="var(--chart-1)" fill="var(--chart-1)" fillOpacity={0.12} strokeWidth={2} isAnimationActive={false} />
                                <Line yAxisId="right" type="monotone" dataKey="margin_pct" name="Margin %" stroke="var(--chart-3)" strokeWidth={2} dot={false} isAnimationActive={false} />
                            </ComposedChart>
                        </ResponsiveContainer>
                        <ChartLegend
                            items={[
                                { label: 'Net sales', color: 'var(--chart-1)' },
                                { label: 'Contribution margin %', color: 'var(--chart-3)' },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="dashboard.channel_mix.view">
                    <ChartCard
                        title="Channel mix"
                        subtitle="Share of net sales and the margin behind it"
                        widgetKey="dashboard.channel_mix"
                        loading={channelMix.loading}
                        error={channelMix.error}
                        onRetry={channelMix.reload}
                        verdict={channelMix.data?.verdict}
                        exportDataset="channel_scorecard"
                    >
                        <div className="space-y-2.5">
                            {(channelMix.data?.rows ?? []).map((row) => (
                                <div key={row.code} className="space-y-1">
                                    <div className="flex items-baseline justify-between gap-2 text-xs">
                                        <span className="flex items-center gap-1.5 truncate font-medium">
                                            <span className="size-2 shrink-0 rounded-full" style={{ background: row.color ?? 'var(--chart-1)' }} />
                                            {row.name}
                                        </span>
                                        <span className="shrink-0 tnum text-muted-foreground">
                                            {formatCompactCurrency(row.net_sales)} ·{' '}
                                            <span className={row.margin_pct < 0 ? 'text-bad' : row.margin_pct > 30 ? 'text-good' : ''}>
                                                {formatPercent(row.margin_pct)}
                                            </span>
                                        </span>
                                    </div>
                                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full transition-all"
                                            style={{ width: `${Math.max(row.share_pct, 1)}%`, background: row.color ?? 'var(--chart-1)' }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.top_states.view">
                    <ChartCard
                        title="Top states"
                        subtitle="By net sales"
                        widgetKey="dashboard.top_states"
                        loading={topStates.loading}
                        error={topStates.error}
                        onRetry={topStates.reload}
                        exportDataset="geo_states"
                    >
                        <DataTable<StateRow>
                            dense
                            rows={topStates.data?.rows ?? []}
                            rowKey={(row) => row.state}
                            columns={[
                                { key: 'state', header: 'State', value: (r) => r.state, render: (r) => r.state },
                                { key: 'orders', header: 'Orders', align: 'right', sortable: true, value: (r) => r.orders, render: (r) => formatNumber(r.orders) },
                                { key: 'sales', header: 'Net sales', align: 'right', sortable: true, value: (r) => r.net_sales, render: (r) => formatCompactCurrency(r.net_sales) },
                                { key: 'share', header: 'Share', align: 'right', value: (r) => r.share_pct, render: (r) => formatPercent(r.share_pct) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.top_categories.view">
                    <ChartCard
                        title="Top categories"
                        subtitle="Share of net sales"
                        widgetKey="dashboard.top_categories"
                        loading={categories.loading}
                        error={categories.error}
                        onRetry={categories.reload}
                        exportDataset="top_skus"
                    >
                        <div className="space-y-2.5">
                            {(categories.data?.rows ?? []).map((row, index) => (
                                <div key={row.category} className="space-y-1">
                                    <div className="flex items-baseline justify-between gap-2 text-xs">
                                        <span className="truncate font-medium">{row.category}</span>
                                        <span className="shrink-0 tnum text-muted-foreground">
                                            {formatCompactCurrency(row.net_sales)} · {formatPercent(row.share_pct, 0)}
                                        </span>
                                    </div>
                                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full"
                                            style={{ width: `${Math.max(row.share_pct, 1)}%`, background: CHART_COLORS[index % CHART_COLORS.length] }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="dashboard.state_action_matrix.view">
                    <ChartCard
                        title="State action matrix"
                        subtitle="Sales volume against RTO rate, with a recommended move per state"
                        widgetKey="dashboard.state_action_matrix"
                        tooltip="Top-left is where to scale; bottom-right is where to stop offering COD."
                        loading={matrix.loading}
                        error={matrix.error}
                        onRetry={matrix.reload}
                        exportDataset="state_roi"
                    >
                        <ResponsiveContainer width="100%" height={280}>
                            <ScatterChart margin={{ top: 8, right: 12, bottom: 20, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} vertical />
                                <XAxis
                                    type="number"
                                    dataKey="net_sales"
                                    name="Net sales"
                                    {...AXIS_PROPS}
                                    tickFormatter={axisCurrency}
                                    label={{ value: 'Net sales', position: 'insideBottom', offset: -12, fontSize: 11, fill: 'var(--muted-foreground)' }}
                                />
                                <YAxis
                                    type="number"
                                    dataKey="rto_pct"
                                    name="RTO %"
                                    {...AXIS_PROPS}
                                    tickFormatter={axisPercent}
                                    width={44}
                                    label={{ value: 'RTO %', angle: -90, position: 'insideLeft', fontSize: 11, fill: 'var(--muted-foreground)' }}
                                />
                                <ZAxis type="number" dataKey="orders" range={[40, 380]} />
                                <ReferenceLine y={matrix.data?.rto_threshold ?? 15} stroke="var(--bad)" strokeDasharray="4 4" />
                                <RTooltip
                                    cursor={{ strokeDasharray: '3 3' }}
                                    content={({ active, payload }) => {
                                        if (!active || !payload?.length) return null;
                                        const row = payload[0].payload as MatrixRow;
                                        return (
                                            <div className="max-w-[240px] rounded-lg border border-border bg-popover px-2.5 py-2 text-xs shadow-lg">
                                                <p className="font-semibold">{row.state}</p>
                                                <p className="mt-0.5 tnum text-muted-foreground">
                                                    {formatNumber(row.orders)} orders · {formatCompactCurrency(row.net_sales)} · {formatPercent(row.rto_pct)} RTO
                                                </p>
                                                <p className="mt-1 leading-snug">{row.action}</p>
                                            </div>
                                        );
                                    }}
                                />
                                <Scatter data={matrix.data?.rows ?? []} isAnimationActive={false}>
                                    {(matrix.data?.rows ?? []).map((row) => (
                                        <Cell key={row.state} fill={QUADRANT_COLOR[row.quadrant]} fillOpacity={0.72} />
                                    ))}
                                </Scatter>
                            </ScatterChart>
                        </ResponsiveContainer>
                        <ChartLegend
                            items={[
                                { label: 'Scale', color: QUADRANT_COLOR.scale },
                                { label: 'Grow', color: QUADRANT_COLOR.grow },
                                { label: 'Fix', color: QUADRANT_COLOR.fix },
                                { label: 'Restrict', color: QUADRANT_COLOR.restrict },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.top_rto_states.view">
                    <ChartCard
                        title="Highest RTO states"
                        subtitle="Flagged against your threshold"
                        widgetKey="dashboard.top_rto_states"
                        loading={rtoStates.loading}
                        error={rtoStates.error}
                        onRetry={rtoStates.reload}
                        verdict={rtoStates.data?.verdict}
                        caveat={rtoStates.data?.caveat}
                        exportDataset="rto_by_state"
                    >
                        <ResponsiveContainer width="100%" height={230}>
                            <BarChart data={(rtoStates.data?.rows ?? []).slice(0, 10)} layout="vertical" margin={{ top: 0, right: 12, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} horizontal={false} vertical />
                                <XAxis type="number" {...AXIS_PROPS} tickFormatter={axisPercent} />
                                <YAxis type="category" dataKey="state" {...AXIS_PROPS} width={104} interval={0} />
                                <ReferenceLine x={rtoStates.data?.threshold ?? 15} stroke="var(--bad)" strokeDasharray="4 4" />
                                <RTooltip content={<ChartTooltip format="percent" labelFormatter={(l) => l} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="rto_pct" name="RTO %" radius={[0, 3, 3, 0]} isAnimationActive={false}>
                                    {(rtoStates.data?.rows ?? []).slice(0, 10).map((row) => (
                                        <Cell key={row.state} fill={row.rto_pct >= (rtoStates.data?.threshold ?? 15) ? 'var(--bad)' : 'var(--chart-4)'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="dashboard.sku_pareto.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="SKU Pareto"
                        subtitle="Which SKUs actually make the profit"
                        widgetKey="dashboard.sku_pareto"
                        tooltip="SKUs ranked by contribution margin, with the running cumulative share of total profit."
                        loading={pareto.loading}
                        error={pareto.error}
                        onRetry={pareto.reload}
                        verdict={pareto.data?.verdict}
                        exportDataset="top_skus"
                        empty={(pareto.data?.rows.length ?? 0) === 0}
                    >
                        <ResponsiveContainer width="100%" height={250}>
                            <ComposedChart data={(pareto.data?.rows ?? []).slice(0, 25)} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="sku_code" {...AXIS_PROPS} interval={0} angle={-40} textAnchor="end" height={62} />
                                <YAxis yAxisId="left" {...AXIS_PROPS} tickFormatter={axisCurrency} width={54} />
                                <YAxis yAxisId="right" orientation="right" {...AXIS_PROPS} tickFormatter={axisPercent} width={40} domain={[0, 100]} />
                                <ReferenceLine yAxisId="right" y={80} stroke="var(--warn)" strokeDasharray="4 4" />
                                <RTooltip content={<ChartTooltip formats={{ cumulative_pct: 'percent' }} labelFormatter={(l) => l} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar yAxisId="left" dataKey="margin" name="Margin" fill="var(--chart-1)" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                                <Line yAxisId="right" type="monotone" dataKey="cumulative_pct" name="Cumulative %" stroke="var(--chart-4)" strokeWidth={2} dot={false} isAnimationActive={false} />
                            </ComposedChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.ltv_cohort.view">
                    <ChartCard
                        title="Repeat curve"
                        subtitle="Average retention by month since first order"
                        widgetKey="dashboard.ltv_cohort"
                        loading={cohort.loading}
                        error={cohort.error}
                        onRetry={cohort.reload}
                        verdict={cohort.data?.verdict}
                    >
                        <ResponsiveContainer width="100%" height={200}>
                            <AreaChart data={cohort.data?.series ?? []} margin={{ top: 4, right: 4, bottom: 0, left: 4 }}>
                                <defs>
                                    <linearGradient id="cohort-fill" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-2)" stopOpacity={0.3} />
                                        <stop offset="100%" stopColor="var(--chart-2)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="month_index" {...AXIS_PROPS} tickFormatter={(v) => `M${v}`} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisPercent} width={40} />
                                <ReferenceLine y={cohort.data?.target_repeat_rate ?? 25} stroke="var(--muted-foreground)" strokeDasharray="4 4" />
                                <RTooltip content={<ChartTooltip format="percent" labelFormatter={(l) => `Month ${l}`} />} />
                                <Area type="monotone" dataKey="avg_retention_pct" name="Retention" stroke="var(--chart-2)" strokeWidth={2} fill="url(#cohort-fill)" isAnimationActive={false} />
                            </AreaChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="dashboard.returns_by_channel.view">
                    <ChartCard
                        title="Returns by channel"
                        subtitle="Returns and RTO as a share of orders"
                        widgetKey="dashboard.returns_by_channel"
                        loading={returnsByChannel.loading}
                        error={returnsByChannel.error}
                        onRetry={returnsByChannel.reload}
                    >
                        <DataTable
                            dense
                            rows={returnsByChannel.data?.rows ?? []}
                            rowKey={(row) => row.name}
                            columns={[
                                { key: 'name', header: 'Channel', value: (r) => r.name, render: (r) => (
                                    <span className="flex items-center gap-1.5">
                                        <span className="size-2 rounded-full" style={{ background: r.color ?? 'var(--chart-1)' }} />
                                        {r.name}
                                    </span>
                                ) },
                                { key: 'orders', header: 'Orders', align: 'right', value: (r) => r.orders, render: (r) => formatNumber(r.orders) },
                                { key: 'returns', header: 'Returns', align: 'right', value: (r) => r.returns_total, render: (r) => formatNumber(r.returns_total) },
                                { key: 'pct', header: 'Return %', align: 'right', sortable: true, value: (r) => r.return_pct, render: (r) => (
                                    <span className={r.return_pct > 10 ? 'font-medium text-bad' : ''}>{formatPercent(r.return_pct)}</span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.top_return_reasons.view">
                    <ChartCard
                        title="Top return reasons"
                        subtitle="Why product comes back"
                        widgetKey="dashboard.top_return_reasons"
                        loading={returnReasons.loading}
                        error={returnReasons.error}
                        onRetry={returnReasons.reload}
                        exportDataset="returns_register"
                    >
                        <ResponsiveContainer width="100%" height={210}>
                            <BarChart data={(returnReasons.data?.rows ?? []).slice(0, 7)} layout="vertical" margin={{ top: 0, right: 12, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} horizontal={false} vertical />
                                <XAxis type="number" {...AXIS_PROPS} tickFormatter={axisNumber} />
                                <YAxis type="category" dataKey="label" {...AXIS_PROPS} width={118} />
                                <RTooltip content={<ChartTooltip format="number" labelFormatter={(l) => l} />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="count" name="Returns" fill="var(--chart-5)" radius={[0, 3, 3, 0]} isAnimationActive={false} />
                            </BarChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.top_return_skus.view">
                    <ChartCard
                        title="Most returned SKUs"
                        subtitle="Where the returns concentrate"
                        widgetKey="dashboard.top_return_skus"
                        loading={returnSkus.loading}
                        error={returnSkus.error}
                        onRetry={returnSkus.reload}
                        exportDataset="returns_register"
                    >
                        <DataTable
                            dense
                            rows={returnSkus.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'count', header: 'Returns', align: 'right', sortable: true, value: (r) => r.returns_count, render: (r) => formatNumber(r.returns_count) },
                                { key: 'value', header: 'Refunded', align: 'right', sortable: true, value: (r) => r.refund_amount, render: (r) => formatCompactCurrency(r.refund_amount) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="dashboard.loss_orders.view">
                    <ChartCard
                        title="Biggest loss orders"
                        subtitle="Orders where you paid to deliver"
                        widgetKey="dashboard.loss_orders"
                        loading={lossOrders.loading}
                        error={lossOrders.error}
                        onRetry={lossOrders.reload}
                        verdict={lossOrders.data?.verdict}
                        onDetail={() => setDrilldown('loss_orders')}
                        exportDataset="loss_orders"
                        empty={(lossOrders.data?.count ?? 0) === 0}
                        emptyState={<EmptyState kind="celebrate" compact title="No loss-making orders in this view 🎉" description="Every order in this window covered its own costs." />}
                    >
                        <DataTable<OrderRow>
                            dense
                            rows={lossOrders.data?.rows ?? []}
                            rowKey={(row) => row.id}
                            columns={orderColumns.filter((c) => ['order', 'channel', 'payment', 'net', 'margin'].includes(c.key))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="dashboard.critical_inventory.view">
                    <ChartCard
                        title="Critical inventory"
                        subtitle="Bestsellers about to run out"
                        widgetKey="dashboard.critical_inventory"
                        loading={inventory.loading}
                        error={inventory.error}
                        onRetry={inventory.reload}
                        verdict={inventory.data?.verdict}
                        exportDataset="inventory_health"
                        empty={(inventory.data?.count ?? 0) === 0}
                        emptyState={<EmptyState kind="celebrate" compact title="Nothing is about to stock out" description="Every selling SKU has cover beyond your threshold." />}
                    >
                        <DataTable
                            dense
                            rows={inventory.data?.rows ?? []}
                            rowKey={(row) => row.sku_code}
                            columns={[
                                { key: 'sku', header: 'SKU', value: (r) => r.sku_code, render: (r) => (
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{r.sku_code}</p>
                                        <p className="truncate text-[11px] text-muted-foreground">{r.name}</p>
                                    </div>
                                ) },
                                { key: 'stock', header: 'Stock', align: 'right', sortable: true, value: (r) => r.stock, render: (r) => formatNumber(r.stock) },
                                { key: 'cover', header: 'Days left', align: 'right', sortable: true, value: (r) => r.days_of_cover, render: (r) => (
                                    <span className={r.days_of_cover < 4 ? 'font-medium text-bad' : 'text-warn'}>{r.days_of_cover.toFixed(1)}d</span>
                                ) },
                                { key: 'revenue', header: 'Rev / 30d', align: 'right', sortable: true, value: (r) => r.monthly_revenue, render: (r) => formatCompactCurrency(r.monthly_revenue) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="dashboard.recent_orders.view">
                <ChartCard
                    title="Recent orders"
                    subtitle="Latest activity across every channel"
                    widgetKey="dashboard.recent_orders"
                    loading={recentOrders.loading}
                    error={recentOrders.error}
                    onRetry={recentOrders.reload}
                    onDetail={() => setDrilldown('recent_orders')}
                    exportDataset="orders"
                >
                    <DataTable<OrderRow>
                        rows={recentOrders.data?.rows ?? []}
                        rowKey={(row) => row.id}
                        columns={orderColumns}
                    />
                </ChartCard>
            </PermissionGuard>

            <DrilldownDrawer<OrderRow>
                open={drilldown !== null}
                onOpenChange={(open) => setDrilldown(open ? drilldown : null)}
                title={drilldown === 'loss_orders' ? 'Loss-making orders' : 'Recent orders'}
                description={
                    drilldown === 'loss_orders'
                        ? 'Order-level P&L for every order that lost money in this window.'
                        : 'Every order in the current window, newest first.'
                }
                columns={orderColumns}
                rows={(drilldown === 'loss_orders' ? lossOrders.data?.rows : recentOrders.data?.rows) ?? []}
                loading={drilldown === 'loss_orders' ? lossOrders.loading : recentOrders.loading}
                rowKey={(row) => row.id}
                verdict={drilldown === 'loss_orders' ? lossOrders.data?.verdict : null}
                exportDataset={drilldown === 'loss_orders' ? 'loss_orders' : 'orders'}
            />
        </AppLayout>
    );

    function WaterfallSection() {
        const rows = waterfall.data?.rows;
        if (!rows) return <Skeleton className="h-[300px] w-full" />;

        const steps = buildWaterfall(summary.data?.rows ?? []);
        return (
            <div className="space-y-2">
                <WaterfallChart steps={steps} />
                <WaterfallLegend steps={steps} />
            </div>
        );
    }
}

/** Derives the floating-bar geometry from the summary rows already on screen. */
function buildWaterfall(rows: SummaryRow[]) {
    const relevant = rows.filter((row) => !['invoiced_sales', 'net_sales', 'shipping'].includes(row.key));
    let running = 0;

    const steps = relevant.map((row) => {
        const start = running;
        running += row.amount;
        return { key: row.key, label: row.label, delta: row.amount, start, end: running };
    });

    return [...steps, { key: 'result', label: 'Net after leaks', delta: running, start: 0, end: running, is_total: true }];
}

import { Head } from '@inertiajs/react';
import { Radio, TrendingDown, TrendingUp } from 'lucide-react';
import {
    Bar,
    CartesianGrid,
    Cell,
    ComposedChart,
    Line,
    ReferenceDot,
    ResponsiveContainer,
    Tooltip as RTooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { KpiStrip } from '@/components/app/kpi-card';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { Funnel } from '@/components/app/funnel';
import { VerdictBadge } from '@/components/app/verdict-note';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { AXIS_PROPS, CHART_COLORS, ChartLegend, ChartTooltip, GRID_PROPS, axisCurrency, axisDate } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCurrency, formatNumber, formatPercent, formatRatio } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Metric, Verdict } from '@/types';

interface CampaignRow {
    id: number;
    name: string;
    platform: string;
    objective: string | null;
    spend: number;
    impressions: number;
    clicks: number;
    ctr: number;
    cpc: number;
    conversions: number;
    attributed_sales: number;
    roas: number;
    cac: number;
    verdict: Verdict;
}

const TONE_CLASS: Record<string, string> = {
    good: 'border-good/25 bg-good-soft/40',
    warn: 'border-warn/25 bg-warn-soft/40',
    bad: 'border-bad/25 bg-bad-soft/40',
    neutral: 'border-border bg-accent/30',
};

export default function Marketing() {
    const kpis = useWidget<Metric[]>('marketing/kpis');
    const campaigns = useWidget<{ rows: CampaignRow[]; account_roas: number; target_roas: number; caveat: string; verdict: Verdict }>('marketing/campaigns');
    const insights = useWidget<{ cards: { kind: string; title: string; headline: string; body: string; tone: string }[] }>('marketing/insights');
    const trend = useWidget<{ series: { date: string; meta_spend: number; google_spend: number; net_sales: number; blended_roas: number }[]; anomalies: { date: string; value: number; note: string; direction: string }[] }>('marketing/trend');
    const funnel = useWidget<{ steps: { key: string; label: string; value: number; step_rate: number }[]; overall_conversion_pct: number; caveat?: unknown; verdict: Verdict }>('marketing/conversion-funnel');
    const carts = useWidget<{ count: number; recovered: number; recovery_rate_pct: number; recoverable_value: number; avg_cart_value: number; verdict: Verdict }>('marketing/abandoned-carts');
    const realtime = useWidget<{ active_users: number | null; by_country: Record<string, number>; by_page: Record<string, number>; is_stale?: boolean; caveat?: unknown }>('marketing/realtime-active-users');
    const mer = useWidget<{ series: { date: string; net_sales: number; ad_spend: number; mer: number }[]; mer: number; target: number; verdict: Verdict }>('marketing/mer');
    const attribution = useWidget<{ platform_reported_sales: number; store_net_sales: number; gap_amount: number; gap_pct: number; blended_roas: number; attributed_roas: number; caveat: string; verdict: Verdict }>('marketing/attribution-gap');
    const channels = useWidget<{ rows: { channel_group: string; sessions: number; orders: number; sales: number; conversion_pct: number; ad_spend: number; site_roas: number; cac: number }[]; caveat: string }>('marketing/channel-performance');
    const fatigue = useWidget<{ rows: { ad_id: number; name: string; spend: number; frequency: number; ctr_first_half: number; ctr_second_half: number; ctr_decay_pct: number; is_fatigued: boolean }[]; caveat: string; verdict: Verdict }>('marketing/creative-fatigue');
    const objectives = useWidget<{ rows: { objective: string; spend: number; attributed_sales: number; roas: number }[] }>('marketing/spend-by-objective');
    const placements = useWidget<{ rows: { dimension: string; spend: number; ctr: number; roas: number }[] }>('marketing/placements');
    const utm = useWidget<{ rows: { source: string; medium: string; campaign: string; orders: number; net_sales: number; margin_pct: number; new_customers: number; aov: number }[] }>('marketing/utm-analysis');
    const pacing = useWidget<{ planned_to_date: number; actual_to_date: number; variance: number; variance_pct: number; projected_month_end: number; planned_month_end: number; caveat: string }>('marketing/budget-pacing');
    const persona = useWidget<{ rows: { age: string; gender: string; sessions: number; site_purchases: number; ad_spend: number; ad_orders: number; ad_sales: number; roas: number }[]; caveat: unknown }>('marketing/buyer-persona');

    return (
        <AppLayout title="Marketing" description="What you spent, what it returned, and where to move the budget">
            <Head title="Marketing" />

            <PermissionGuard permission="marketing.kpi_strip.view">
                <KpiStrip metrics={kpis.data} loading={kpis.loading} columns={4} />
            </PermissionGuard>

            <PermissionGuard permission="marketing.insights_cards.view">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {(insights.data?.cards ?? []).map((card) => (
                        <Card key={card.kind} className={cn('p-4', TONE_CLASS[card.tone] ?? TONE_CLASS.neutral)}>
                            <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{card.title}</p>
                            <p className="mt-1 truncate text-sm font-semibold">{card.headline}</p>
                            <p className="mt-1 text-xs leading-snug text-muted-foreground">{card.body}</p>
                        </Card>
                    ))}
                </div>
            </PermissionGuard>

            <PermissionGuard permission="marketing.campaign_table.view">
                <ChartCard
                    title="Campaign performance"
                    subtitle={`Account ROAS ${formatRatio(campaigns.data?.account_roas ?? 0)} · target ${formatRatio(campaigns.data?.target_roas ?? 0)}`}
                    widgetKey="marketing.campaign_table"
                    tooltip="Scale / Hold / Cut is computed against both your target ROAS and the account average, so a campaign is only cut when it is genuinely behind."
                    loading={campaigns.loading}
                    error={campaigns.error}
                    onRetry={campaigns.reload}
                    verdict={campaigns.data?.verdict}
                    caveat={campaigns.data?.caveat}
                    exportDataset="campaigns"
                    insightPayload={campaigns.data}
                >
                    <DataTable<CampaignRow>
                        searchable
                        searchPlaceholder="Search campaigns…"
                        rows={campaigns.data?.rows ?? []}
                        rowKey={(row) => row.id}
                        initialSort={{ key: 'spend', direction: 'desc' }}
                        columns={[
                            { key: 'name', header: 'Campaign', value: (r) => r.name, render: (r) => (
                                <div className="min-w-0">
                                    <p className="truncate font-medium">{r.name}</p>
                                    <p className="truncate text-[11px] uppercase tracking-wide text-muted-foreground">
                                        {r.platform.replace('_', ' ')}{r.objective ? ` · ${r.objective.replace('_', ' ')}` : ''}
                                    </p>
                                </div>
                            ) },
                            { key: 'spend', header: 'Spend', align: 'right', sortable: true, value: (r) => r.spend, render: (r) => formatCompactCurrency(r.spend) },
                            { key: 'sales', header: 'Attributed', align: 'right', sortable: true, value: (r) => r.attributed_sales, render: (r) => formatCompactCurrency(r.attributed_sales) },
                            { key: 'orders', header: 'Orders', align: 'right', sortable: true, value: (r) => r.conversions, render: (r) => formatNumber(r.conversions) },
                            { key: 'ctr', header: 'CTR', align: 'right', sortable: true, value: (r) => r.ctr, render: (r) => formatPercent(r.ctr, 2) },
                            { key: 'cac', header: 'CAC', align: 'right', sortable: true, value: (r) => r.cac, render: (r) => formatCompactCurrency(r.cac) },
                            { key: 'roas', header: 'ROAS', align: 'right', sortable: true, value: (r) => r.roas, render: (r) => (
                                <span className={cn('font-medium', r.roas >= (campaigns.data?.target_roas ?? 3) ? 'text-good' : r.roas < 1 ? 'text-bad' : '')}>
                                    {formatRatio(r.roas)}
                                </span>
                            ) },
                            { key: 'status', header: 'Verdict', align: 'center', sortable: true, value: (r) => r.verdict.status, render: (r) => <VerdictBadge verdict={r.verdict} /> },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketing.trend.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Marketing trend"
                        subtitle="Spend by platform against net sales, with anomaly markers"
                        widgetKey="marketing.trend"
                        loading={trend.loading}
                        error={trend.error}
                        onRetry={trend.reload}
                        caveat={
                            (trend.data?.anomalies.length ?? 0) > 0
                                ? `${trend.data?.anomalies.length} day(s) flagged: spend moved more than 2 standard deviations from the period mean.`
                                : undefined
                        }
                        insightPayload={trend.data}
                    >
                        <ResponsiveContainer width="100%" height={280}>
                            <ComposedChart data={trend.data?.series ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={28} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisCurrency} width={56} />
                                <RTooltip content={<ChartTooltip />} />
                                <Bar dataKey="meta_spend" name="Meta spend" stackId="spend" fill="var(--chart-1)" isAnimationActive={false} />
                                <Bar dataKey="google_spend" name="Google spend" stackId="spend" fill="var(--chart-4)" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                                <Line type="monotone" dataKey="net_sales" name="Net sales" stroke="var(--chart-3)" strokeWidth={2} dot={false} isAnimationActive={false} />
                                {(trend.data?.anomalies ?? []).map((anomaly) => (
                                    <ReferenceDot
                                        key={anomaly.date}
                                        x={anomaly.date}
                                        y={anomaly.value}
                                        r={5}
                                        fill="var(--bad)"
                                        stroke="var(--card)"
                                        strokeWidth={2}
                                        ifOverflow="visible"
                                    />
                                ))}
                            </ComposedChart>
                        </ResponsiveContainer>
                        <ChartLegend
                            items={[
                                { label: 'Meta spend', color: 'var(--chart-1)' },
                                { label: 'Google spend', color: 'var(--chart-4)' },
                                { label: 'Net sales', color: 'var(--chart-3)' },
                                { label: 'Anomaly', color: 'var(--bad)' },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.mer.view">
                    <ChartCard
                        title="Marketing efficiency ratio"
                        subtitle="Total net sales ÷ total ad spend"
                        widgetKey="marketing.mer"
                        tooltip="MER is the honest number. Platform ROAS double counts across networks; MER cannot."
                        loading={mer.loading}
                        error={mer.error}
                        onRetry={mer.reload}
                        verdict={mer.data?.verdict}
                    >
                        <p className="text-3xl font-semibold tracking-tight tnum">{formatRatio(mer.data?.mer ?? 0)}</p>
                        <p className="text-xs text-muted-foreground">against a {formatRatio(mer.data?.target ?? 0)} target</p>
                        <ResponsiveContainer width="100%" height={150}>
                            <ComposedChart data={mer.data?.series ?? []} margin={{ top: 8, right: 4, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={34} />
                                <YAxis {...AXIS_PROPS} width={32} />
                                <RTooltip content={<ChartTooltip format="ratio" />} />
                                <Line type="monotone" dataKey="mer" name="MER" stroke="var(--chart-2)" strokeWidth={2} dot={false} isAnimationActive={false} />
                            </ComposedChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketing.conversion_funnel.view">
                    <ChartCard
                        title="Conversion funnel"
                        subtitle="Sessions → cart → checkout → purchase"
                        widgetKey="marketing.conversion_funnel"
                        loading={funnel.loading}
                        error={funnel.error}
                        onRetry={funnel.reload}
                        verdict={funnel.data?.verdict}
                        caveat={funnel.data?.caveat as never}
                        empty={(funnel.data?.steps.length ?? 0) === 0}
                    >
                        <Funnel steps={funnel.data?.steps ?? []} overall={funnel.data?.overall_conversion_pct} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.abandoned_carts.view">
                    <ChartCard
                        title="Abandoned carts"
                        subtitle="What is sitting one step from a sale"
                        widgetKey="marketing.abandoned_carts"
                        loading={carts.loading}
                        error={carts.error}
                        onRetry={carts.reload}
                        verdict={carts.data?.verdict}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            {[
                                ['Carts', formatNumber(carts.data?.count ?? 0)],
                                ['Recoverable', formatCompactCurrency(carts.data?.recoverable_value ?? 0)],
                                ['Recovered', `${formatNumber(carts.data?.recovered ?? 0)} · ${formatPercent(carts.data?.recovery_rate_pct ?? 0)}`],
                                ['Avg cart', formatCompactCurrency(carts.data?.avg_cart_value ?? 0)],
                            ].map(([label, value]) => (
                                <div key={label} className="rounded-lg border border-border p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</p>
                                    <p className="mt-0.5 text-base font-semibold tnum">{value}</p>
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.realtime_users.view">
                    <ChartCard
                        title="Live active users"
                        subtitle="Right now, from GA4 realtime"
                        widgetKey="marketing.realtime_users"
                        loading={realtime.loading}
                        error={realtime.error}
                        onRetry={realtime.reload}
                        caveat={realtime.data?.caveat as never}
                        actions={
                            realtime.data?.active_users !== null && (
                                <span className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                    <Radio className="size-3 animate-pulse text-good" />
                                    live
                                </span>
                            )
                        }
                    >
                        {realtime.data?.active_users === null ? null : (
                            <>
                                <p className="text-4xl font-semibold tracking-tight tnum">{formatNumber(realtime.data?.active_users ?? 0)}</p>
                                <p className="text-xs text-muted-foreground">active users on site</p>
                                <BarList
                                    format="number"
                                    rows={Object.entries(realtime.data?.by_page ?? {}).slice(0, 5).map(([page, users]) => ({ label: page, value: users }))}
                                />
                            </>
                        )}
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="marketing.attribution_gap.view">
                    <ChartCard
                        title="Attribution gap"
                        subtitle="What platforms claim vs what your store recorded"
                        widgetKey="marketing.attribution_gap"
                        loading={attribution.loading}
                        error={attribution.error}
                        onRetry={attribution.reload}
                        verdict={attribution.data?.verdict}
                        caveat={attribution.data?.caveat}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-lg border border-border p-3">
                                <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Platforms claim</p>
                                <p className="mt-0.5 text-lg font-semibold tnum">{formatCompactCurrency(attribution.data?.platform_reported_sales ?? 0)}</p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">{formatRatio(attribution.data?.attributed_roas ?? 0)} attributed ROAS</p>
                            </div>
                            <div className="rounded-lg border border-primary/30 bg-primary/5 p-3">
                                <p className="text-[11px] uppercase tracking-wide text-primary/80">Store recorded</p>
                                <p className="mt-0.5 text-lg font-semibold tnum text-primary">{formatCompactCurrency(attribution.data?.store_net_sales ?? 0)}</p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">{formatRatio(attribution.data?.blended_roas ?? 0)} blended ROAS</p>
                            </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Gap of{' '}
                            <span className="font-semibold tnum text-foreground">{formatCompactCurrency(attribution.data?.gap_amount ?? 0)}</span>{' '}
                            ({formatPercent(attribution.data?.gap_pct ?? 0)} of store net sales).
                        </p>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.budget_pacing.view">
                    <ChartCard
                        title="Budget pacing"
                        subtitle="Planned vs actual spend this month"
                        widgetKey="marketing.budget_pacing"
                        loading={pacing.loading}
                        error={pacing.error}
                        onRetry={pacing.reload}
                        caveat={pacing.data?.caveat}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            {[
                                ['Planned to date', pacing.data?.planned_to_date ?? 0],
                                ['Actual to date', pacing.data?.actual_to_date ?? 0],
                                ['Projected month end', pacing.data?.projected_month_end ?? 0],
                                ['Planned month end', pacing.data?.planned_month_end ?? 0],
                            ].map(([label, value]) => (
                                <div key={label as string} className="rounded-lg border border-border p-3">
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</p>
                                    <p className="mt-0.5 text-base font-semibold tnum">{formatCompactCurrency(value as number)}</p>
                                </div>
                            ))}
                        </div>
                        <p className={cn('flex items-center gap-1.5 text-xs font-medium', (pacing.data?.variance ?? 0) > 0 ? 'text-warn' : 'text-good')}>
                            {(pacing.data?.variance ?? 0) > 0 ? <TrendingUp className="size-3.5" /> : <TrendingDown className="size-3.5" />}
                            {formatPercent(Math.abs(pacing.data?.variance_pct ?? 0))} {(pacing.data?.variance ?? 0) > 0 ? 'over' : 'under'} plan
                        </p>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="marketing.spend_by_objective.view">
                    <ChartCard title="Spend by objective" widgetKey="marketing.spend_by_objective" loading={objectives.loading} error={objectives.error} onRetry={objectives.reload} exportDataset="campaigns">
                        <BarList
                            rows={(objectives.data?.rows ?? []).map((row, index) => ({
                                label: row.objective,
                                value: row.spend,
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: formatRatio(row.roas),
                                tone: row.roas >= 3 ? 'good' : row.roas < 1 ? 'bad' : 'neutral',
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.placements.view">
                    <ChartCard title="Placement performance" widgetKey="marketing.placements" loading={placements.loading} error={placements.error} onRetry={placements.reload} exportDataset="campaigns">
                        <BarList
                            rows={(placements.data?.rows ?? []).map((row, index) => ({
                                label: row.dimension,
                                value: row.spend,
                                color: CHART_COLORS[index % CHART_COLORS.length],
                                secondary: formatRatio(row.roas),
                                tone: row.roas >= 3 ? 'good' : row.roas < 1 ? 'bad' : 'neutral',
                            }))}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.channel_performance.view">
                    <ChartCard
                        title="Channel performance"
                        subtitle="Sessions, orders and site ROAS"
                        widgetKey="marketing.channel_performance"
                        loading={channels.loading}
                        error={channels.error}
                        onRetry={channels.reload}
                        caveat={channels.data?.caveat}
                    >
                        <DataTable
                            dense
                            rows={channels.data?.rows ?? []}
                            rowKey={(row) => row.channel_group}
                            columns={[
                                { key: 'group', header: 'Channel', value: (r) => r.channel_group, render: (r) => <span className="font-medium">{r.channel_group}</span> },
                                { key: 'sessions', header: 'Sessions', align: 'right', sortable: true, value: (r) => r.sessions, render: (r) => formatNumber(r.sessions) },
                                { key: 'conv', header: 'Conv %', align: 'right', sortable: true, value: (r) => r.conversion_pct, render: (r) => formatPercent(r.conversion_pct, 2) },
                                { key: 'roas', header: 'Site ROAS', align: 'right', sortable: true, value: (r) => r.site_roas, render: (r) => (r.ad_spend > 0 ? formatRatio(r.site_roas) : '—') },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="marketing.creative_fatigue.view">
                    <ChartCard
                        title="Creative fatigue"
                        subtitle="CTR decay against rising frequency"
                        widgetKey="marketing.creative_fatigue"
                        loading={fatigue.loading}
                        error={fatigue.error}
                        onRetry={fatigue.reload}
                        verdict={fatigue.data?.verdict}
                        caveat={fatigue.data?.caveat}
                    >
                        <DataTable
                            dense
                            rows={fatigue.data?.rows ?? []}
                            rowKey={(row) => row.ad_id}
                            columns={[
                                { key: 'name', header: 'Ad', value: (r) => r.name, render: (r) => (
                                    <span className="flex items-center gap-1.5">
                                        <span className="truncate">{r.name}</span>
                                        {r.is_fatigued && <Badge variant="warn">fatigued</Badge>}
                                    </span>
                                ) },
                                { key: 'spend', header: 'Spend', align: 'right', sortable: true, value: (r) => r.spend, render: (r) => formatCompactCurrency(r.spend) },
                                { key: 'freq', header: 'Freq', align: 'right', sortable: true, value: (r) => r.frequency, render: (r) => r.frequency.toFixed(2) },
                                { key: 'decay', header: 'CTR change', align: 'right', sortable: true, value: (r) => r.ctr_decay_pct, render: (r) => (
                                    <span className={r.ctr_decay_pct < -20 ? 'font-medium text-bad' : r.ctr_decay_pct > 0 ? 'text-good' : ''}>
                                        {r.ctr_decay_pct > 0 ? '+' : ''}{r.ctr_decay_pct.toFixed(1)}%
                                    </span>
                                ) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="marketing.buyer_persona.view">
                    <ChartCard
                        title="Buyer persona"
                        subtitle="Age × gender: site visitors vs paid buyers"
                        widgetKey="marketing.buyer_persona"
                        loading={persona.loading}
                        error={persona.error}
                        onRetry={persona.reload}
                        caveat={persona.data?.caveat as never}
                        empty={(persona.data?.rows.length ?? 0) === 0}
                    >
                        <DataTable
                            dense
                            rows={persona.data?.rows ?? []}
                            rowKey={(row) => `${row.age}-${row.gender}`}
                            columns={[
                                { key: 'seg', header: 'Segment', value: (r) => `${r.age} ${r.gender}`, render: (r) => (
                                    <span className="font-medium capitalize">{r.age} · {r.gender}</span>
                                ) },
                                { key: 'sessions', header: 'Sessions', align: 'right', sortable: true, value: (r) => r.sessions, render: (r) => formatNumber(r.sessions) },
                                { key: 'spend', header: 'Ad spend', align: 'right', sortable: true, value: (r) => r.ad_spend, render: (r) => formatCompactCurrency(r.ad_spend) },
                                { key: 'roas', header: 'ROAS', align: 'right', sortable: true, value: (r) => r.roas, render: (r) => (r.ad_spend > 0 ? formatRatio(r.roas) : '—') },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="marketing.utm_analysis.view">
                <ChartCard
                    title="UTM analysis"
                    subtitle="Orders attributed by the tags on your own links"
                    widgetKey="marketing.utm_analysis"
                    loading={utm.loading}
                    error={utm.error}
                    onRetry={utm.reload}
                >
                    <DataTable
                        searchable
                        rows={utm.data?.rows ?? []}
                        rowKey={(row, index) => `${row.source}-${row.medium}-${row.campaign}-${index}`}
                        initialSort={{ key: 'net_sales', direction: 'desc' }}
                        columns={[
                            { key: 'source', header: 'Source', value: (r) => r.source, render: (r) => <span className="font-medium">{r.source}</span> },
                            { key: 'medium', header: 'Medium', value: (r) => r.medium, render: (r) => r.medium },
                            { key: 'campaign', header: 'Campaign', value: (r) => r.campaign, render: (r) => <span className="truncate text-muted-foreground">{r.campaign}</span> },
                            { key: 'orders', header: 'Orders', align: 'right', sortable: true, value: (r) => r.orders, render: (r) => formatNumber(r.orders) },
                            { key: 'new', header: 'New', align: 'right', sortable: true, value: (r) => r.new_customers, render: (r) => formatNumber(r.new_customers) },
                            { key: 'aov', header: 'AOV', align: 'right', sortable: true, value: (r) => r.aov, render: (r) => formatCurrency(r.aov) },
                            { key: 'net_sales', header: 'Net sales', align: 'right', sortable: true, value: (r) => r.net_sales, render: (r) => formatCompactCurrency(r.net_sales) },
                            { key: 'margin', header: 'Margin %', align: 'right', sortable: true, value: (r) => r.margin_pct, render: (r) => (
                                <span className={r.margin_pct < 0 ? 'text-bad' : ''}>{formatPercent(r.margin_pct)}</span>
                            ) },
                        ]}
                    />
                </ChartCard>
            </PermissionGuard>
        </AppLayout>
    );
}

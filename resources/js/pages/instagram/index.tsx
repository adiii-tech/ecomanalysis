import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { Bookmark, ExternalLink, Heart, MessageCircle, Send } from 'lucide-react';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ComposedChart, Line, ResponsiveContainer, Tooltip as RTooltip, XAxis, YAxis } from 'recharts';
import { AppLayout } from '@/layouts/app-layout';
import { KpiStrip } from '@/components/app/kpi-card';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { DataTable } from '@/components/app/data-table';
import { BarList } from '@/components/app/bar-list';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { AXIS_PROPS, CHART_COLORS, ChartLegend, ChartTooltip, GRID_PROPS, axisCurrency, axisDate, axisNumber } from '@/components/charts/chart-primitives';
import { useWidget } from '@/hooks/use-widget';
import { formatCompactCurrency, formatCompactNumber, formatDate, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Metric, Verdict } from '@/types';

interface Post {
    media_id: string;
    type: string;
    caption: string | null;
    permalink: string | null;
    published_at: string;
    reach: number;
    views: number;
    likes: number;
    comments: number;
    shares: number;
    saves: number;
    engagement_rate: number;
    avg_watch_time: number;
}

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const DAY_INDEX: Record<string, number> = { Mon: 2, Tue: 3, Wed: 4, Thu: 5, Fri: 6, Sat: 7, Sun: 1 };

export default function Instagram() {
    const [sort, setSort] = useState<'reach' | 'recent' | 'engagement' | 'saves'>('reach');

    const kpis = useWidget<Metric[]>('instagram/kpis');
    const trend = useWidget<{ series: { date: string; followers: number; reach: number; views: number; profile_views: number }[] }>('instagram/account-trend');
    const engagement = useWidget<{ series: { date: string; likes: number; comments: number; shares: number; saves: number }[]; caveat: string }>('instagram/engagement-trend');
    const content = useWidget<{ rows: Post[] }>('instagram/content', { sort });
    const formats = useWidget<{ rows: { type: string; posts: number; avg_reach: number; avg_engagement: number; saves: number }[]; verdict: Verdict }>('instagram/reels-vs-feed');
    const stories = useWidget<{ rows: { media_id: string; published_at: string; reach: number; views: number; replies: number }[]; caveat: string | null }>('instagram/stories');
    const audience = useWidget<{ cities: { label: string; value: number }[]; countries: { label: string; value: number }[]; gender_age: { label: string; value: number }[]; caveat: string | null }>('instagram/audience');
    const bestTime = useWidget<{ cells: { day: string; day_index: number; hour: number; posts: number; avg_reach: number }[]; max_reach: number; caveat: string; verdict: Verdict }>('instagram/best-time');
    const hashtags = useWidget<{ rows: { tag: string; posts: number; avg_reach: number; avg_engagement: number; saves: number }[]; caveat: string }>('instagram/hashtags');
    const correlation = useWidget<{ series: { date: string; reach: number; net_sales: number }[]; caveat: string }>('instagram/sales-correlation');
    const fbPage = useWidget<{ series: { date: string; reach: number; views: number; followers: number }[]; caveat: string | null }>('instagram/fb-page');

    return (
        <AppLayout title="Instagram & Facebook" description="What organic is actually earning you">
            <Head title="Instagram" />

            <PermissionGuard permission="instagram.kpi_strip.view">
                <KpiStrip metrics={kpis.data} loading={kpis.loading} columns={6} />
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="instagram.growth_reach.view">
                    <ChartCard
                        title="Growth & reach"
                        subtitle="Followers against the reach earning them"
                        widgetKey="instagram.growth_reach"
                        loading={trend.loading}
                        error={trend.error}
                        onRetry={trend.reload}
                        insightPayload={trend.data}
                    >
                        <ResponsiveContainer width="100%" height={260}>
                            <ComposedChart data={trend.data?.series ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <defs>
                                    <linearGradient id="ig-reach" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-6)" stopOpacity={0.28} />
                                        <stop offset="100%" stopColor="var(--chart-6)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={28} />
                                <YAxis yAxisId="left" {...AXIS_PROPS} tickFormatter={axisNumber} width={48} />
                                <YAxis yAxisId="right" orientation="right" {...AXIS_PROPS} tickFormatter={axisNumber} width={48} />
                                <RTooltip content={<ChartTooltip format="number" />} />
                                <Area yAxisId="left" type="monotone" dataKey="reach" name="Reach" stroke="var(--chart-6)" strokeWidth={2} fill="url(#ig-reach)" isAnimationActive={false} />
                                <Line yAxisId="right" type="monotone" dataKey="followers" name="Followers" stroke="var(--chart-1)" strokeWidth={2} dot={false} isAnimationActive={false} />
                            </ComposedChart>
                        </ResponsiveContainer>
                        <ChartLegend items={[{ label: 'Reach', color: 'var(--chart-6)' }, { label: 'Followers', color: 'var(--chart-1)' }]} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="instagram.engagement.view">
                    <ChartCard
                        title="Engagement"
                        subtitle="Saves are the buy-intent signal"
                        widgetKey="instagram.engagement"
                        loading={engagement.loading}
                        error={engagement.error}
                        onRetry={engagement.reload}
                        caveat={engagement.data?.caveat}
                    >
                        <ResponsiveContainer width="100%" height={260}>
                            <BarChart data={engagement.data?.series ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={28} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisNumber} width={44} />
                                <RTooltip content={<ChartTooltip format="number" />} cursor={{ fill: 'var(--accent)', opacity: 0.4 }} />
                                <Bar dataKey="likes" name="Likes" stackId="e" fill="var(--chart-7)" isAnimationActive={false} />
                                <Bar dataKey="comments" name="Comments" stackId="e" fill="var(--chart-2)" isAnimationActive={false} />
                                <Bar dataKey="shares" name="Shares" stackId="e" fill="var(--chart-4)" isAnimationActive={false} />
                                <Bar dataKey="saves" name="Saves" stackId="e" fill="var(--chart-3)" radius={[3, 3, 0, 0]} isAnimationActive={false} />
                            </BarChart>
                        </ResponsiveContainer>
                        <ChartLegend
                            items={[
                                { label: 'Likes', color: 'var(--chart-7)' },
                                { label: 'Comments', color: 'var(--chart-2)' },
                                { label: 'Shares', color: 'var(--chart-4)' },
                                { label: 'Saves — buy intent', color: 'var(--chart-3)' },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <PermissionGuard permission="instagram.content_performance.view">
                <ChartCard
                    title="Content performance"
                    subtitle="Everything published in this window"
                    widgetKey="instagram.content_performance"
                    loading={content.loading}
                    error={content.error}
                    onRetry={content.reload}
                    tabs={
                        <Tabs value={sort} onValueChange={(value) => setSort(value as typeof sort)}>
                            <TabsList>
                                <TabsTrigger value="reach">Reach</TabsTrigger>
                                <TabsTrigger value="engagement">Engagement</TabsTrigger>
                                <TabsTrigger value="saves">Saves</TabsTrigger>
                                <TabsTrigger value="recent">Recent</TabsTrigger>
                            </TabsList>
                        </Tabs>
                    }
                >
                    <div className="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                        {(content.data?.rows ?? []).slice(0, 12).map((post) => (
                            <Card key={post.media_id} className="p-3">
                                <div className="flex items-start justify-between gap-2">
                                    <Badge variant={post.type === 'reel' ? 'default' : 'muted'}>{post.type}</Badge>
                                    <span className="text-[10px] text-muted-foreground">{formatDate(post.published_at)}</span>
                                </div>
                                <p className="mt-1.5 line-clamp-2 text-xs leading-snug">{post.caption ?? '—'}</p>
                                <div className="mt-2 grid grid-cols-2 gap-1.5 text-[11px]">
                                    <span className="text-muted-foreground">Reach <span className="font-medium tnum text-foreground">{formatCompactNumber(post.reach)}</span></span>
                                    <span className="text-muted-foreground">ENG <span className={cn('font-medium tnum', post.engagement_rate > 5 ? 'text-good' : 'text-foreground')}>{formatPercent(post.engagement_rate, 1)}</span></span>
                                </div>
                                <div className="mt-2 flex items-center gap-3 border-t border-border/60 pt-2 text-[11px] text-muted-foreground">
                                    <span className="flex items-center gap-1"><Heart className="size-3" />{formatCompactNumber(post.likes)}</span>
                                    <span className="flex items-center gap-1"><MessageCircle className="size-3" />{formatCompactNumber(post.comments)}</span>
                                    <span className="flex items-center gap-1"><Send className="size-3" />{formatCompactNumber(post.shares)}</span>
                                    <span className={cn('flex items-center gap-1', post.saves > 0 && 'font-medium text-good')}>
                                        <Bookmark className="size-3" />{formatCompactNumber(post.saves)}
                                    </span>
                                    {post.permalink && (
                                        <a href={post.permalink} target="_blank" rel="noreferrer" className="ml-auto hover:text-foreground">
                                            <ExternalLink className="size-3" />
                                        </a>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>
                </ChartCard>
            </PermissionGuard>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="instagram.reels_vs_feed.view">
                    <ChartCard
                        title="Reels vs feed"
                        subtitle="Which format earns reach"
                        widgetKey="instagram.reels_vs_feed"
                        loading={formats.loading}
                        error={formats.error}
                        onRetry={formats.reload}
                        verdict={formats.data?.verdict}
                    >
                        <DataTable
                            dense
                            rows={formats.data?.rows ?? []}
                            rowKey={(row) => row.type}
                            columns={[
                                { key: 'type', header: 'Format', value: (r) => r.type, render: (r) => <span className="font-medium capitalize">{r.type}</span> },
                                { key: 'posts', header: 'Posts', align: 'right', value: (r) => r.posts, render: (r) => formatNumber(r.posts) },
                                { key: 'reach', header: 'Avg reach', align: 'right', sortable: true, value: (r) => r.avg_reach, render: (r) => formatCompactNumber(r.avg_reach) },
                                { key: 'eng', header: 'Avg ENG', align: 'right', sortable: true, value: (r) => r.avg_engagement, render: (r) => formatPercent(r.avg_engagement, 1) },
                                { key: 'saves', header: 'Saves', align: 'right', sortable: true, value: (r) => r.saves, render: (r) => formatCompactNumber(r.saves) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="instagram.best_time.view">
                    <ChartCard
                        title="Best time to post"
                        subtitle="Average reach by day and hour"
                        widgetKey="instagram.best_time"
                        loading={bestTime.loading}
                        error={bestTime.error}
                        onRetry={bestTime.reload}
                        verdict={bestTime.data?.verdict}
                        caveat={bestTime.data?.caveat}
                    >
                        <div className="overflow-x-auto scrollbar-thin">
                            <table className="w-full text-[10px]">
                                <thead>
                                    <tr>
                                        <th className="w-8" />
                                        {[6, 9, 12, 15, 18, 21].map((hour) => (
                                            <th key={hour} className="pb-1 text-center font-medium text-muted-foreground">{hour}:00</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {DAYS.map((day) => (
                                        <tr key={day}>
                                            <td className="pr-1.5 text-right font-medium text-muted-foreground">{day}</td>
                                            {[6, 9, 12, 15, 18, 21].map((hour) => {
                                                const cell = (bestTime.data?.cells ?? []).find(
                                                    (c) => c.day_index === DAY_INDEX[day] && c.hour >= hour && c.hour < hour + 3,
                                                );
                                                const intensity = cell ? cell.avg_reach / Math.max(bestTime.data?.max_reach ?? 1, 1) : 0;

                                                return (
                                                    <td key={hour} className="p-0.5">
                                                        <div
                                                            className="rounded py-1.5 text-center tnum"
                                                            style={{ background: `color-mix(in oklch, var(--chart-6) ${Math.round(intensity * 85)}%, transparent)` }}
                                                            title={cell ? `${formatCompactNumber(cell.avg_reach)} avg reach · ${cell.posts} posts` : 'Never posted'}
                                                        >
                                                            {cell ? formatCompactNumber(cell.avg_reach) : '·'}
                                                        </div>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="instagram.audience.view">
                    <ChartCard
                        title="Audience"
                        subtitle="Where your followers are"
                        widgetKey="instagram.audience"
                        loading={audience.loading}
                        error={audience.error}
                        onRetry={audience.reload}
                        caveat={audience.data?.caveat}
                    >
                        <div className="space-y-3">
                            {([['Top cities', audience.data?.cities], ['Age & gender', audience.data?.gender_age]] as const).map(([label, rows]) => (
                                <div key={label} className="space-y-1.5">
                                    <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
                                    <BarList
                                        format="number"
                                        rows={(rows ?? []).slice(0, 5).map((row, index) => ({
                                            label: row.label,
                                            value: row.value,
                                            color: CHART_COLORS[index % CHART_COLORS.length],
                                        }))}
                                    />
                                </div>
                            ))}
                        </div>
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                <PermissionGuard permission="instagram.sales_correlation.view">
                    <ChartCard
                        className="xl:col-span-2"
                        title="Organic reach vs sales"
                        subtitle="Do they move together?"
                        widgetKey="instagram.sales_correlation"
                        loading={correlation.loading}
                        error={correlation.error}
                        onRetry={correlation.reload}
                        caveat={correlation.data?.caveat}
                    >
                        <ResponsiveContainer width="100%" height={250}>
                            <ComposedChart data={correlation.data?.series ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={28} />
                                <YAxis yAxisId="left" {...AXIS_PROPS} tickFormatter={axisNumber} width={48} />
                                <YAxis yAxisId="right" orientation="right" {...AXIS_PROPS} tickFormatter={axisCurrency} width={54} />
                                <RTooltip content={<ChartTooltip formats={{ reach: 'number', net_sales: 'currency' }} />} />
                                <Bar yAxisId="left" dataKey="reach" name="Organic reach" fill="var(--chart-6)" fillOpacity={0.55} radius={[3, 3, 0, 0]} isAnimationActive={false} />
                                <Line yAxisId="right" type="monotone" dataKey="net_sales" name="Net sales" stroke="var(--chart-1)" strokeWidth={2} dot={false} isAnimationActive={false} />
                            </ComposedChart>
                        </ResponsiveContainer>
                        <ChartLegend items={[{ label: 'Organic reach', color: 'var(--chart-6)' }, { label: 'Net sales', color: 'var(--chart-1)' }]} />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="instagram.hashtags.view">
                    <ChartCard
                        title="Hashtag performance"
                        widgetKey="instagram.hashtags"
                        loading={hashtags.loading}
                        error={hashtags.error}
                        onRetry={hashtags.reload}
                        caveat={hashtags.data?.caveat}
                        empty={(hashtags.data?.rows.length ?? 0) === 0}
                    >
                        <DataTable
                            dense
                            rows={hashtags.data?.rows ?? []}
                            rowKey={(row) => row.tag}
                            columns={[
                                { key: 'tag', header: 'Hashtag', value: (r) => r.tag, render: (r) => <span className="font-medium">#{r.tag}</span> },
                                { key: 'posts', header: 'Posts', align: 'right', value: (r) => r.posts, render: (r) => formatNumber(r.posts) },
                                { key: 'reach', header: 'Avg reach', align: 'right', sortable: true, value: (r) => r.avg_reach, render: (r) => formatCompactNumber(r.avg_reach) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <PermissionGuard permission="instagram.stories.view">
                    <ChartCard
                        title="Stories"
                        subtitle="Reach, views and replies"
                        widgetKey="instagram.stories"
                        loading={stories.loading}
                        error={stories.error}
                        onRetry={stories.reload}
                        caveat={stories.data?.caveat}
                        empty={(stories.data?.rows.length ?? 0) === 0}
                        emptyState={<p className="py-8 text-center text-xs text-muted-foreground">No stories captured in this window.</p>}
                    >
                        <DataTable
                            dense
                            rows={stories.data?.rows ?? []}
                            rowKey={(row) => row.media_id}
                            columns={[
                                { key: 'when', header: 'Published', value: (r) => r.published_at, render: (r) => formatDate(r.published_at) },
                                { key: 'reach', header: 'Reach', align: 'right', sortable: true, value: (r) => r.reach, render: (r) => formatCompactNumber(r.reach) },
                                { key: 'views', header: 'Views', align: 'right', sortable: true, value: (r) => r.views, render: (r) => formatCompactNumber(r.views) },
                                { key: 'replies', header: 'Replies', align: 'right', sortable: true, value: (r) => r.replies, render: (r) => formatNumber(r.replies) },
                            ]}
                        />
                    </ChartCard>
                </PermissionGuard>

                <PermissionGuard permission="instagram.fb_page.view">
                    <ChartCard
                        title="Facebook Page"
                        subtitle="Reach and page views"
                        widgetKey="instagram.fb_page"
                        loading={fbPage.loading}
                        error={fbPage.error}
                        onRetry={fbPage.reload}
                        caveat={fbPage.data?.caveat}
                        empty={(fbPage.data?.series.length ?? 0) === 0}
                    >
                        <ResponsiveContainer width="100%" height={220}>
                            <AreaChart data={fbPage.data?.series ?? []} margin={{ top: 4, right: 8, bottom: 0, left: 4 }}>
                                <defs>
                                    <linearGradient id="fb-reach" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.28} />
                                        <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid {...GRID_PROPS} />
                                <XAxis dataKey="date" {...AXIS_PROPS} tickFormatter={axisDate} minTickGap={30} />
                                <YAxis {...AXIS_PROPS} tickFormatter={axisNumber} width={46} />
                                <RTooltip content={<ChartTooltip format="number" />} />
                                <Area type="monotone" dataKey="reach" name="Reach" stroke="var(--chart-1)" strokeWidth={2} fill="url(#fb-reach)" isAnimationActive={false} />
                            </AreaChart>
                        </ResponsiveContainer>
                    </ChartCard>
                </PermissionGuard>
            </div>
        </AppLayout>
    );
}

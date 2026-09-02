import { Head } from '@inertiajs/react';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useWidget } from '@/hooks/use-widget';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Profile {
    customer: {
        id: number;
        name: string | null;
        email: string | null;
        phone: string | null;
        city: string | null;
        state: string | null;
        orders_count: number;
        total_spent: number;
        aov: number;
        ltv: number;
        total_margin: number;
        returns_count: number;
        first_order_at: string | null;
        last_order_at: string | null;
        days_since_last_order: number | null;
        rfm_label: string | null;
        is_vip: boolean;
        churn_risk_score: number | null;
    };
    orders: { id: number; order_number: string; placed_at: string; status: string; payment_mode: string; net_amount: number; contribution_margin: number; channel_name: string | null }[];
    returns: { id: number; type: string; reason_text: string | null; initiated_at: string; refund_amount: number; order_number: string }[];
    timeline: { type: string; at: string; title: string; amount: number; meta: string | null }[];
    segment: string | null;
    playbook: string | null;
}

export default function CustomerShow({ customerId }: { customerId: number }) {
    const profile = useWidget<Profile>(`customers/customer/${customerId}`);
    const customer = profile.data?.customer;

    return (
        <AppLayout
            title={customer?.name ?? 'Customer'}
            description={customer?.email ?? undefined}
            showFilters={false}
            breadcrumb={{ label: 'Customers', href: '/customers' }}
        >
            <Head title={customer?.name ?? 'Customer'} />

            {profile.loading && <Skeleton className="h-32 w-full" />}

            {profile.error && <EmptyState kind="error" title="Could not load this customer" description={profile.error} />}

            {customer && (
                <>
                    <div className="grid gap-3 grid-cols-2 lg:grid-cols-6">
                        {[
                            ['Orders', formatNumber(customer.orders_count)],
                            ['Total spent', formatCurrency(customer.total_spent)],
                            ['AOV', formatCurrency(customer.aov)],
                            ['Margin earned', formatCurrency(customer.total_margin)],
                            ['Returns', formatNumber(customer.returns_count)],
                            ['Days since order', customer.days_since_last_order === null ? '—' : `${customer.days_since_last_order}d`],
                        ].map(([label, value]) => (
                            <Card key={label} className="p-4">
                                <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                                <p className="mt-2 text-lg font-semibold tnum">{value}</p>
                            </Card>
                        ))}
                    </div>

                    <Card className="flex flex-wrap items-center gap-3 p-4">
                        {profile.data?.segment && <Badge variant={customer.is_vip ? 'good' : 'default'}>{profile.data.segment}</Badge>}
                        {customer.churn_risk_score !== null && (
                            <Badge variant={customer.churn_risk_score >= 60 ? 'bad' : 'muted'}>Churn risk {customer.churn_risk_score}</Badge>
                        )}
                        <span className="text-xs text-muted-foreground">
                            {[customer.city, customer.state].filter(Boolean).join(', ') || 'Location unknown'}
                            {customer.first_order_at && ` · first order ${formatDate(customer.first_order_at)}`}
                        </span>
                        {profile.data?.playbook && (
                            <p className="w-full text-xs leading-snug text-muted-foreground">→ {profile.data.playbook}</p>
                        )}
                    </Card>

                    <div className="grid gap-4 xl:grid-cols-3">
                        <ChartCard className="xl:col-span-2" title="Orders" subtitle="Every order this customer has placed">
                            <DataTable
                                rows={profile.data?.orders ?? []}
                                rowKey={(row) => row.id}
                                columns={[
                                    { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => <span className="font-medium">{r.order_number}</span> },
                                    { key: 'when', header: 'Placed', value: (r) => r.placed_at, render: (r) => <span className="text-muted-foreground">{formatDateTime(r.placed_at)}</span> },
                                    { key: 'channel', header: 'Channel', value: (r) => r.channel_name, render: (r) => r.channel_name ?? '—' },
                                    { key: 'status', header: 'Status', value: (r) => r.status, render: (r) => <Badge variant="outline">{r.status}</Badge> },
                                    { key: 'net', header: 'Net', align: 'right', sortable: true, value: (r) => r.net_amount, render: (r) => formatCurrency(r.net_amount) },
                                    { key: 'margin', header: 'Margin', align: 'right', sortable: true, value: (r) => r.contribution_margin, render: (r) => (
                                        <span className={r.contribution_margin < 0 ? 'font-medium text-bad' : ''}>{formatCurrency(r.contribution_margin)}</span>
                                    ) },
                                ]}
                            />
                        </ChartCard>

                        <ChartCard title="Timeline" subtitle="Orders and returns, newest first">
                            <div className="space-y-2">
                                {(profile.data?.timeline ?? []).slice(0, 25).map((event, index) => (
                                    <div key={index} className="flex items-start gap-2.5 border-b border-border/50 pb-2 last:border-0">
                                        <span
                                            className={cn('mt-1.5 size-2 shrink-0 rounded-full', event.type === 'return' ? 'bg-bad' : 'bg-good')}
                                        />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-xs font-medium">{event.title}</p>
                                            <p className="text-[11px] text-muted-foreground">
                                                {formatDate(event.at)}{event.meta ? ` · ${event.meta}` : ''}
                                            </p>
                                        </div>
                                        <span className={cn('shrink-0 text-xs tnum', event.amount < 0 ? 'text-bad' : '')}>
                                            {formatCurrency(event.amount)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </ChartCard>
                    </div>

                    {(profile.data?.returns.length ?? 0) > 0 && (
                        <ChartCard title="Returns" subtitle="What came back and why">
                            <DataTable
                                dense
                                rows={profile.data?.returns ?? []}
                                rowKey={(row) => row.id}
                                columns={[
                                    { key: 'order', header: 'Order', value: (r) => r.order_number, render: (r) => r.order_number },
                                    { key: 'type', header: 'Type', value: (r) => r.type, render: (r) => <Badge variant={r.type === 'rto' ? 'bad' : 'warn'}>{r.type.replace('_', ' ')}</Badge> },
                                    { key: 'reason', header: 'Reason', value: (r) => r.reason_text, render: (r) => r.reason_text ?? '—' },
                                    { key: 'when', header: 'Initiated', value: (r) => r.initiated_at, render: (r) => <span className="text-muted-foreground">{formatDate(r.initiated_at)}</span> },
                                    { key: 'refund', header: 'Refund', align: 'right', sortable: true, value: (r) => r.refund_amount, render: (r) => formatCurrency(r.refund_amount) },
                                ]}
                            />
                        </ChartCard>
                    )}
                </>
            )}
        </AppLayout>
    );
}

import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AlertTriangle, BellOff, Check, Loader2, Plus, TestTube2, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { PermissionGuard } from '@/components/app/permission-guard';
import { EmptyState } from '@/components/app/empty-state';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input, Label } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { usePermissions } from '@/hooks/use-permissions';
import { apiGet, apiSend } from '@/lib/api';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

interface MetricMeta {
    key: string;
    label: string;
    unit: string;
    dimension: string | null;
    description: string;
    higher_is_worse: boolean;
}

interface ChannelMeta {
    key: string;
    label: string;
    available: boolean;
    note?: string;
}

interface Schema {
    metrics: MetricMeta[];
    operators: { key: string; label: string }[];
    channels: ChannelMeta[];
    windows: number[];
}

interface RuleRow {
    id: number;
    name: string;
    metric: string;
    metric_label: string;
    operator: string;
    threshold: number;
    window_days: number;
    channels: string[] | null;
    is_active: boolean;
    is_muted: boolean;
    last_triggered_at: string | null;
    recent_events: number;
}

interface EventRow {
    id: number;
    title: string;
    body: string;
    severity: string;
    read_at: string | null;
    created_at: string;
    rule: { name: string } | null;
}

const BLANK = {
    name: '',
    metric: 'rto_rate_by_state',
    operator: 'gt',
    threshold: 25,
    window_days: 7,
    channels: ['in_app'],
    is_active: true,
};

export default function Alerts() {
    const { can } = usePermissions();
    const [schema, setSchema] = useState<Schema | null>(null);
    const [rules, setRules] = useState<RuleRow[]>([]);
    const [events, setEvents] = useState<EventRow[]>([]);
    const [unread, setUnread] = useState(0);
    const [draft, setDraft] = useState<typeof BLANK & { id?: number } | null>(null);
    const [testing, setTesting] = useState(false);
    const [testResult, setTestResult] = useState<{ would_fire: boolean; message: string } | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void refresh();
    }, []);

    async function refresh() {
        setLoading(true);
        try {
            const [s, r, e] = await Promise.all([
                apiGet<Schema>('alerts/schema'),
                apiGet<{ rows: RuleRow[] }>('alerts/rules'),
                apiGet<{ rows: EventRow[]; unread: number }>('alerts/events'),
            ]);
            setSchema(s.data);
            setRules(r.data.rows);
            setEvents(e.data.rows);
            setUnread(e.data.unread);
        } catch {
            /* permission-gated widgets handle their own absence */
        } finally {
            setLoading(false);
        }
    }

    const metric = schema?.metrics.find((m) => m.key === draft?.metric);

    async function save() {
        if (!draft) return;
        try {
            if (draft.id) {
                await apiSend('PUT', `alerts/rules/${draft.id}`, draft);
            } else {
                await apiSend('POST', 'alerts/rules', draft);
            }
            toast.success('Rule saved.');
            setDraft(null);
            setTestResult(null);
            void refresh();
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save.');
        }
    }

    async function test() {
        if (!draft) return;
        setTesting(true);
        try {
            const response = await apiSend<{ would_fire: boolean; message: string }>('POST', 'alerts/test', draft);
            setTestResult(response.data);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not test.');
        } finally {
            setTesting(false);
        }
    }

    return (
        <AppLayout
            title="Alerts"
            description="Tell me before it costs money"
            showFilters={false}
            actions={
                can('alerts.rules.manage') && (
                    <Button size="sm" onClick={() => { setDraft({ ...BLANK }); setTestResult(null); }} className="gap-1.5">
                        <Plus className="size-3.5" />
                        New rule
                    </Button>
                )
            }
        >
            <Head title="Alerts" />

            <PermissionGuard permission="alerts.events.view">
                <ChartCard
                    title="Notifications"
                    subtitle={unread > 0 ? `${unread} unread` : 'Everything read'}
                    loading={loading}
                    actions={
                        unread > 0 && (
                            <Button
                                size="xs"
                                variant="ghost"
                                onClick={async () => {
                                    await apiSend('POST', 'alerts/events/read');
                                    void refresh();
                                }}
                                className="gap-1"
                            >
                                <Check className="size-3" />
                                Mark all read
                            </Button>
                        )
                    }
                    empty={events.length === 0}
                    emptyState={
                        <EmptyState
                            kind="celebrate"
                            compact
                            title="Nothing has tripped an alert"
                            description="Rules are evaluated hourly against the same rollups the dashboard reads."
                        />
                    }
                >
                    <div className="space-y-2">
                        {events.map((event) => (
                            <Card
                                key={event.id}
                                className={cn(
                                    'p-3',
                                    event.severity === 'critical' ? 'border-bad/25 bg-bad-soft/30' : 'border-warn/25 bg-warn-soft/30',
                                    event.read_at && 'opacity-60',
                                )}
                            >
                                <div className="flex items-start gap-2.5">
                                    <AlertTriangle className={cn('mt-0.5 size-4 shrink-0', event.severity === 'critical' ? 'text-bad' : 'text-warn')} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold leading-snug">{event.title}</p>
                                        <p className="mt-0.5 text-xs leading-snug text-muted-foreground">{event.body}</p>
                                        <p className="mt-1 text-[10px] text-muted-foreground">
                                            {event.rule?.name} · {formatDateTime(event.created_at)}
                                        </p>
                                    </div>
                                    <Button
                                        size="xs"
                                        variant="ghost"
                                        onClick={async () => {
                                            await apiSend('POST', `alerts/events/${event.id}/snooze`, { days: 7 });
                                            void refresh();
                                        }}
                                        className="shrink-0 gap-1 text-[11px]"
                                    >
                                        <BellOff className="size-3" />
                                        Snooze
                                    </Button>
                                </div>
                            </Card>
                        ))}
                    </div>
                </ChartCard>
            </PermissionGuard>

            <PermissionGuard permission="alerts.rules.view">
                <ChartCard
                    title="Rules"
                    subtitle="Evaluated hourly"
                    loading={loading}
                    empty={rules.length === 0}
                    emptyState={
                        <EmptyState
                            title="No rules yet"
                            description="A rule watches one metric over a window and tells you when it crosses a line."
                        />
                    }
                >
                    <div className="space-y-2">
                        {rules.map((rule) => (
                            <div key={rule.id} className="flex flex-wrap items-center gap-2 rounded-lg border border-border p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-1.5 text-sm font-medium">
                                        {rule.name}
                                        {!rule.is_active && <Badge variant="muted">off</Badge>}
                                        {rule.is_muted && <Badge variant="warn">muted</Badge>}
                                    </p>
                                    <p className="text-[11px] text-muted-foreground">
                                        {rule.metric_label} {rule.operator === 'gt' ? '>' : rule.operator === 'lt' ? '<' : rule.operator}{' '}
                                        {rule.threshold} over {rule.window_days}d
                                        {rule.last_triggered_at ? ` · last fired ${formatDateTime(rule.last_triggered_at)}` : ' · never fired'}
                                    </p>
                                </div>

                                <Badge variant={rule.recent_events > 0 ? 'warn' : 'muted'}>{rule.recent_events} in 30d</Badge>

                                {can('alerts.rules.manage') && (
                                    <>
                                        <Button size="xs" variant="ghost" onClick={() => { setDraft({ ...BLANK, ...rule, channels: rule.channels ?? ['in_app'] }); setTestResult(null); }}>
                                            Edit
                                        </Button>
                                        <Button
                                            size="xs"
                                            variant="ghost"
                                            className="text-bad"
                                            onClick={async () => {
                                                await apiSend('DELETE', `alerts/rules/${rule.id}`);
                                                toast.success('Rule deleted.');
                                                void refresh();
                                            }}
                                        >
                                            <Trash2 className="size-3" />
                                        </Button>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </ChartCard>
            </PermissionGuard>

            <Sheet open={draft !== null} onOpenChange={(open) => !open && setDraft(null)}>
                <SheetContent side="right" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>{draft?.id ? 'Edit rule' : 'New alert rule'}</SheetTitle>
                        <SheetDescription>Watch one metric over a window and get told when it crosses a line.</SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-4 overflow-auto p-5">
                        <div className="space-y-1.5">
                            <Label htmlFor="rule-name">Name</Label>
                            <Input
                                id="rule-name"
                                value={draft?.name ?? ''}
                                placeholder="RTO spike in any state"
                                onChange={(event) => setDraft((d) => (d ? { ...d, name: event.target.value } : d))}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label>Metric</Label>
                            <Select value={draft?.metric} onValueChange={(value) => setDraft((d) => (d ? { ...d, metric: value } : d))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {(schema?.metrics ?? []).map((m) => (
                                        <SelectItem key={m.key} value={m.key}>{m.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {metric && <p className="text-[11px] text-muted-foreground">{metric.description}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-2">
                            <div className="space-y-1.5">
                                <Label>Condition</Label>
                                <Select value={draft?.operator} onValueChange={(value) => setDraft((d) => (d ? { ...d, operator: value } : d))}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {(schema?.operators ?? []).map((o) => (
                                            <SelectItem key={o.key} value={o.key}>{o.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="threshold">
                                    Threshold {metric ? `(${metric.unit})` : ''}
                                </Label>
                                <Input
                                    id="threshold"
                                    type="number"
                                    step="0.1"
                                    value={draft?.threshold ?? 0}
                                    onChange={(event) => setDraft((d) => (d ? { ...d, threshold: Number(event.target.value) } : d))}
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Window</Label>
                            <Select
                                value={String(draft?.window_days ?? 7)}
                                onValueChange={(value) => setDraft((d) => (d ? { ...d, window_days: Number(value) } : d))}
                            >
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {(schema?.windows ?? [7]).map((w) => (
                                        <SelectItem key={w} value={String(w)}>{w} day{w === 1 ? '' : 's'}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Deliver to</Label>
                            <div className="space-y-1.5">
                                {(schema?.channels ?? []).map((channel) => (
                                    <label key={channel.key} className={cn('flex items-center gap-2 text-xs', !channel.available && 'opacity-50')}>
                                        <input
                                            type="checkbox"
                                            disabled={!channel.available}
                                            checked={draft?.channels?.includes(channel.key) ?? false}
                                            onChange={(event) =>
                                                setDraft((d) =>
                                                    d
                                                        ? {
                                                              ...d,
                                                              channels: event.target.checked
                                                                  ? [...(d.channels ?? []), channel.key]
                                                                  : (d.channels ?? []).filter((c) => c !== channel.key),
                                                          }
                                                        : d,
                                                )
                                            }
                                            className="size-3.5 rounded border-input"
                                        />
                                        {channel.label}
                                        {channel.note && <span className="text-[10px] text-muted-foreground">— {channel.note}</span>}
                                    </label>
                                ))}
                            </div>
                        </div>

                        <label className="flex items-center justify-between text-xs">
                            <span>Active</span>
                            <Switch
                                checked={draft?.is_active ?? true}
                                onCheckedChange={(checked) => setDraft((d) => (d ? { ...d, is_active: checked } : d))}
                            />
                        </label>

                        {testResult && (
                            <div className={cn('rounded-lg px-3 py-2 text-xs', testResult.would_fire ? 'bg-warn-soft text-warn' : 'bg-good-soft text-good')}>
                                {testResult.message}
                            </div>
                        )}
                    </div>

                    <div className="flex justify-between gap-2 border-t border-border px-5 py-3">
                        <Button variant="outline" size="sm" onClick={test} disabled={testing || !draft?.name} className="gap-1.5">
                            {testing ? <Loader2 className="animate-spin" /> : <TestTube2 className="size-3.5" />}
                            Test against live data
                        </Button>
                        <div className="flex gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setDraft(null)}>Cancel</Button>
                            <Button size="sm" onClick={save} disabled={!draft?.name}>Save</Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}

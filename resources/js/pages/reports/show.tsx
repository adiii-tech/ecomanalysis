import { Head, Link } from '@inertiajs/react';
import { Calendar, Download, Link2, Loader2, Star, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input, Label } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { KpiCard } from '@/components/app/kpi-card';
import { VerdictNote } from '@/components/app/verdict-note';
import { CaveatNote } from '@/components/app/caveat-note';
import { EmptyState, WidgetError } from '@/components/app/empty-state';
import { SkeletonChart } from '@/components/ui/skeleton';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ReportSectionView } from '@/components/reports/report-section';
import { OrdersDrilldown, type DrilldownTarget } from '@/components/app/orders-drilldown';
import type { ReportPayload } from '@/components/reports/types';
import { useWidget } from '@/hooks/use-widget';
import { useExport, type ExportFormat } from '@/hooks/use-export';
import { apiGet, apiSend } from '@/lib/api';
import { REPORT_LINKS } from '@/lib/navigation';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';

interface ShareRow {
    id: number;
    url: string;
    created_at: string | null;
    expires_at: string | null;
    view_count: number;
    is_expired: boolean;
}

interface ScheduleRow {
    id: number;
    cadence: string;
    hour: number;
    day_of_week: number | null;
    day_of_month: number | null;
    recipients: string[];
    format: string;
    is_active: boolean;
    last_sent_at: string | null;
}

export default function ReportShow({ reportKey }: { reportKey: string }) {
    const fallback = REPORT_LINKS.find((item) => item.slug === reportKey);
    const { data, loading, error, forbidden, reload } = useWidget<ReportPayload>(`/reports/${reportKey}`);
    const download = useExport();

    const [favourite, setFavourite] = useState(false);
    const [sharesOpen, setSharesOpen] = useState(false);
    const [drilldown, setDrilldown] = useState<DrilldownTarget | null>(null);
    const [scheduleOpen, setScheduleOpen] = useState(false);

    useEffect(() => {
        if (data?.report) {
            setFavourite(Boolean(data.report.is_favourite));
        }
    }, [data]);

    const toggleFavourite = useCallback(async () => {
        try {
            const response = await apiSend<{ is_favourite: boolean }>('POST', `/reports/${reportKey}/favourite`);
            setFavourite(response.data.is_favourite);
            toast.success(response.message);
        } catch {
            toast.error('Could not update your favourites.');
        }
    }, [reportKey]);

    const report = data?.report ?? null;
    const title = report?.label ?? fallback?.label ?? 'Report';

    if (forbidden) {
        return (
            <AppLayout title={title} showFilters={false} breadcrumb={{ label: 'Report library', href: '/reports' }}>
                <Head title={title} />
                <EmptyState
                    title="You do not have access to this report"
                    description="Ask an admin to grant it in Admin → Users → Permissions."
                />
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title={title}
            description={report?.description ?? fallback?.description}
            showFilters={report?.uses_period ?? true}
            breadcrumb={{ label: 'Report library', href: '/reports' }}
            actions={
                <div className="flex items-center gap-1.5">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={toggleFavourite}
                        aria-label={favourite ? 'Remove from favourites' : 'Add to favourites'}
                    >
                        <Star className={cn('size-4', favourite && 'fill-warn text-warn')} />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => setSharesOpen(true)} aria-label="Share link">
                        <Link2 className="size-4" />
                    </Button>
                    <Button variant="ghost" size="icon" onClick={() => setScheduleOpen(true)} aria-label="Schedule delivery">
                        <Calendar className="size-4" />
                    </Button>
                    {(report?.exports.length ?? 0) > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Download className="size-3.5" /> Export
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {(report?.exports ?? []).flatMap((dataset) =>
                                    (['csv', 'xlsx', 'pdf'] as ExportFormat[]).map((format) => (
                                        <DropdownMenuItem key={`${dataset}-${format}`} onClick={() => download(dataset, format)}>
                                            {dataset.replace(/_/g, ' ')} · {format.toUpperCase()}
                                        </DropdownMenuItem>
                                    )),
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            }
        >
            <Head title={title} />

            {error && !loading && <WidgetError message={error} onRetry={reload} />}

            {loading && !data && (
                <div className="space-y-4">
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {[0, 1, 2, 3].map((index) => (
                            <SkeletonChart key={index} className="h-24" />
                        ))}
                    </div>
                    <SkeletonChart className="h-72" />
                </div>
            )}

            {data && (
                <>
                    <VerdictNote verdict={data.verdict} />

                    {data.kpis.length > 0 && (
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            {data.kpis.map((metric) => (
                                <KpiCard key={metric.key} metric={metric} />
                            ))}
                        </div>
                    )}

                    {data.caveats.map((caveat, index) => (
                        <CaveatNote key={index} caveat={caveat} />
                    ))}

                    <div className="space-y-4">
                        {data.sections.map((section, index) => (
                            <ReportSectionView
                                key={`${section.type}-${index}`}
                                section={section}
                                onDrilldown={setDrilldown}
                            />
                        ))}
                    </div>
                </>
            )}

            <OrdersDrilldown target={drilldown} onClose={() => setDrilldown(null)} />

            <SharesSheet open={sharesOpen} onOpenChange={setSharesOpen} reportKey={reportKey} />
            <ScheduleSheet open={scheduleOpen} onOpenChange={setScheduleOpen} reportKey={reportKey} />
        </AppLayout>
    );
}

function SharesSheet({
    open,
    onOpenChange,
    reportKey,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    reportKey: string;
}) {
    const [rows, setRows] = useState<ShareRow[] | null>(null);
    const [creating, setCreating] = useState(false);
    const [days, setDays] = useState('30');

    const load = useCallback(() => {
        apiGet<{ rows: ShareRow[] }>(`/reports/${reportKey}/shares`)
            .then((response) => setRows(response.data.rows))
            .catch(() => setRows([]));
    }, [reportKey]);

    useEffect(() => {
        if (open) {
            load();
        }
    }, [open, load]);

    const create = async () => {
        setCreating(true);
        try {
            const response = await apiSend<{ url: string }>('POST', `/reports/${reportKey}/share`, {
                expires_in_days: Number(days),
            });
            await navigator.clipboard?.writeText(response.data.url).catch(() => undefined);
            toast.success('Share link created and copied.');
            load();
        } catch {
            toast.error('Could not create the share link.');
        } finally {
            setCreating(false);
        }
    };

    const revoke = async (id: number) => {
        try {
            await apiSend('DELETE', `/reports/${reportKey}/shares/${id}`);
            toast.success('Link revoked.');
            load();
        } catch {
            toast.error('Could not revoke that link.');
        }
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Share this report</SheetTitle>
                    <SheetDescription>
                        Anyone with the link sees a read-only snapshot on the filters you have applied right now. No sign-in
                        needed, and you can revoke it at any time.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-4 px-4">
                    <div className="flex items-end gap-2">
                        <div className="flex-1 space-y-1.5">
                            <Label htmlFor="share-days">Expires in (days)</Label>
                            <Input id="share-days" type="number" min={1} max={90} value={days} onChange={(event) => setDays(event.target.value)} />
                        </div>
                        <Button onClick={create} disabled={creating}>
                            {creating ? <Loader2 className="size-4 animate-spin" /> : <Link2 className="size-4" />}
                            Create link
                        </Button>
                    </div>

                    <div className="space-y-2">
                        {rows === null && <p className="text-sm text-muted-foreground">Loading…</p>}
                        {rows?.length === 0 && <p className="text-sm text-muted-foreground">No active links.</p>}
                        {rows?.map((row) => (
                            <Card key={row.id} className="flex items-center justify-between gap-3 p-3">
                                <div className="min-w-0">
                                    <button
                                        type="button"
                                        className="truncate text-xs text-primary hover:underline"
                                        onClick={() => {
                                            navigator.clipboard?.writeText(row.url).catch(() => undefined);
                                            toast.success('Link copied.');
                                        }}
                                    >
                                        {row.url}
                                    </button>
                                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                                        {row.view_count} views ·{' '}
                                        {row.expires_at ? `expires ${formatDateTime(row.expires_at)}` : 'no expiry'}
                                        {row.is_expired && ' · expired'}
                                    </p>
                                </div>
                                <Button variant="ghost" size="icon" onClick={() => revoke(row.id)} aria-label="Revoke link">
                                    <Trash2 className="size-4" />
                                </Button>
                            </Card>
                        ))}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ScheduleSheet({
    open,
    onOpenChange,
    reportKey,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    reportKey: string;
}) {
    const [rows, setRows] = useState<ScheduleRow[] | null>(null);
    const [saving, setSaving] = useState(false);
    const [cadence, setCadence] = useState('weekly');
    const [hour, setHour] = useState('8');
    const [dayOfWeek, setDayOfWeek] = useState('1');
    const [recipients, setRecipients] = useState('');
    const [format, setFormat] = useState('pdf');

    const load = useCallback(() => {
        apiGet<{ rows: ScheduleRow[] }>(`/reports/${reportKey}/schedules`)
            .then((response) => setRows(response.data.rows))
            .catch(() => setRows([]));
    }, [reportKey]);

    useEffect(() => {
        if (open) {
            load();
        }
    }, [open, load]);

    const save = async () => {
        const emails = recipients
            .split(/[,\s]+/)
            .map((value) => value.trim())
            .filter(Boolean);

        if (emails.length === 0) {
            toast.error('Add at least one recipient.');
            return;
        }

        setSaving(true);
        try {
            await apiSend('POST', `/reports/${reportKey}/schedules`, {
                cadence,
                hour: Number(hour),
                day_of_week: cadence === 'weekly' ? Number(dayOfWeek) : null,
                day_of_month: cadence === 'monthly' ? 1 : null,
                recipients: emails,
                format,
            });
            toast.success('Schedule saved.');
            setRecipients('');
            load();
        } catch {
            toast.error('Could not save the schedule.');
        } finally {
            setSaving(false);
        }
    };

    const remove = async (id: number) => {
        try {
            await apiSend('DELETE', `/reports/${reportKey}/schedules/${id}`);
            toast.success('Schedule deleted.');
            load();
        } catch {
            toast.error('Could not delete that schedule.');
        }
    };

    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>Email this report on a schedule</SheetTitle>
                    <SheetDescription>
                        The file is generated fresh at send time using the same filters, and goes to the addresses you list.
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-4 px-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Cadence</Label>
                            <Select value={cadence} onValueChange={setCadence}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="weekly">Weekly</SelectItem>
                                    <SelectItem value="monthly">Monthly</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="schedule-hour">Hour (IST)</Label>
                            <Input id="schedule-hour" type="number" min={0} max={23} value={hour} onChange={(event) => setHour(event.target.value)} />
                        </div>
                    </div>

                    {cadence === 'weekly' && (
                        <div className="space-y-1.5">
                            <Label>Day</Label>
                            <Select value={dayOfWeek} onValueChange={setDayOfWeek}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {days.map((day, index) => (
                                        <SelectItem key={day} value={String(index)}>{day}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label htmlFor="schedule-recipients">Recipients</Label>
                        <Input
                            id="schedule-recipients"
                            placeholder="founder@brand.com, ops@brand.com"
                            value={recipients}
                            onChange={(event) => setRecipients(event.target.value)}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Format</Label>
                        <Select value={format} onValueChange={setFormat}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="pdf">PDF</SelectItem>
                                <SelectItem value="csv">CSV</SelectItem>
                                <SelectItem value="xlsx">Excel</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button onClick={save} disabled={saving} className="w-full">
                        {saving && <Loader2 className="size-4 animate-spin" />} Save schedule
                    </Button>

                    <div className="space-y-2">
                        {rows?.map((row) => (
                            <Card key={row.id} className="flex items-center justify-between gap-3 p-3">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium capitalize">
                                        {row.cadence} · {row.hour}:00 · {row.format.toUpperCase()}
                                    </p>
                                    <p className="mt-0.5 truncate text-[11px] text-muted-foreground">
                                        {row.recipients.join(', ')}
                                        {row.last_sent_at && ` · last sent ${formatDateTime(row.last_sent_at)}`}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Badge variant={row.is_active ? 'good' : 'muted'}>{row.is_active ? 'Active' : 'Paused'}</Badge>
                                    <Button variant="ghost" size="icon" onClick={() => remove(row.id)} aria-label="Delete schedule">
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </Card>
                        ))}
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}

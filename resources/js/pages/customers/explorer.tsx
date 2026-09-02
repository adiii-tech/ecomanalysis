import { Head } from '@inertiajs/react';
import { Download, Loader2, Plus, RefreshCw, Trash2, Users } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable, type Column } from '@/components/app/data-table';
import { CaveatNote } from '@/components/app/caveat-note';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input, Label } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import { apiGet, apiSend } from '@/lib/api';
import { formatCurrency, formatNumber } from '@/lib/format';
import { usePermissions } from '@/hooks/use-permissions';

interface FieldMeta {
    key: string;
    label: string;
    type: 'money' | 'number' | 'text' | 'date' | 'enum' | 'boolean';
    operators: string[];
    options: string[] | null;
}

interface Segment {
    id: number;
    name: string;
    description: string | null;
    rules: RuleSet;
    member_count: number;
    member_value: number;
    computed_at: string | null;
    owner: string | null;
}

interface Destination {
    key: string;
    label: string;
    format: string;
    pii: string;
    note: string;
}

interface Condition {
    field: string;
    operator: string;
    value: string | number | boolean | string[];
}

interface RuleSet {
    match: 'all' | 'any';
    conditions: Condition[];
}

interface Preview {
    member_count: number;
    member_value: number;
    average_aov: number;
    contactable: number;
    sample: Record<string, unknown>[];
    caveat: string | null;
}

const OPERATOR_LABELS: Record<string, string> = {
    gt: 'is more than',
    gte: 'is at least',
    lt: 'is less than',
    lte: 'is at most',
    eq: 'is',
    not_eq: 'is not',
    between: 'is between',
    contains: 'contains',
    in: 'is any of',
    before: 'is before',
    after: 'is after',
    within_days: 'was within the last (days)',
    not_within_days: 'was not within the last (days)',
    is: 'is',
};

export default function CustomerExplorer() {
    const { can } = usePermissions();
    const [fields, setFields] = useState<FieldMeta[]>([]);
    const [segments, setSegments] = useState<Segment[]>([]);
    const [destinations, setDestinations] = useState<Destination[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [rules, setRules] = useState<RuleSet>({ match: 'all', conditions: [] });
    const [preview, setPreview] = useState<Preview | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');

    const load = useCallback(() => {
        apiGet<{ rows: Segment[]; fields: FieldMeta[]; destinations: Destination[] }>('/segments')
            .then((response) => {
                setSegments(response.data.rows);
                setFields(response.data.fields);
                setDestinations(response.data.destinations);
                setError(null);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load segments.'));
    }, []);

    useEffect(load, [load]);

    const fieldMap = useMemo(() => Object.fromEntries(fields.map((field) => [field.key, field])), [fields]);

    const runPreview = useCallback(async (next: RuleSet) => {
        setPreviewing(true);
        try {
            const response = await apiSend<Preview>('POST', '/segments/preview', { rules: next });
            setPreview(response.data);
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Could not preview that segment.');
            setPreview(null);
        } finally {
            setPreviewing(false);
        }
    }, []);

    useEffect(() => {
        void runPreview(rules);
    }, [rules, runPreview]);

    const addCondition = () => {
        const first = fields[0];
        if (!first) return;

        setRules((current) => ({
            ...current,
            conditions: [...current.conditions, { field: first.key, operator: first.operators[0], value: '' }],
        }));
    };

    const updateCondition = (index: number, patch: Partial<Condition>) => {
        setRules((current) => ({
            ...current,
            conditions: current.conditions.map((condition, position) =>
                position === index ? { ...condition, ...patch } : condition,
            ),
        }));
    };

    const removeCondition = (index: number) => {
        setRules((current) => ({
            ...current,
            conditions: current.conditions.filter((_, position) => position !== index),
        }));
    };

    const save = async () => {
        if (name.trim() === '') {
            toast.error('Give the segment a name.');
            return;
        }

        try {
            const response = await apiSend<{ member_count: number }>('POST', '/segments', {
                name: name.trim(),
                description: description || null,
                rules,
            });
            toast.success(response.message);
            setName('');
            setDescription('');
            load();
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Could not save the segment.');
        }
    };

    const sampleColumns: Column<Record<string, unknown>>[] = [
        { key: 'name', header: 'Customer', render: (row) => String(row.name ?? '—'), sortable: true, value: (row) => String(row.name ?? '') },
        { key: 'email', header: 'Email', render: (row) => String(row.email ?? '—') },
        { key: 'city', header: 'City', render: (row) => String(row.city ?? '—') },
        { key: 'orders_count', header: 'Orders', align: 'right', sortable: true, value: (row) => Number(row.orders_count ?? 0), render: (row) => formatNumber(Number(row.orders_count ?? 0)) },
        { key: 'total_spent', header: 'Spent', align: 'right', sortable: true, value: (row) => Number(row.total_spent ?? 0), render: (row) => formatCurrency(Number(row.total_spent ?? 0)) },
        { key: 'days_since_last_order', header: 'Days quiet', align: 'right', sortable: true, value: (row) => Number(row.days_since_last_order ?? 0), render: (row) => (row.days_since_last_order === null ? '—' : formatNumber(Number(row.days_since_last_order))) },
    ];

    return (
        <AppLayout
            title="Customer explorer"
            description="Build a segment, see who is in it, then send it somewhere useful"
            showFilters={false}
            breadcrumb={{ label: 'Customers & Reviews', href: '/customers' }}
        >
            <Head title="Customer explorer" />

            {error && <WidgetError message={error} onRetry={load} />}
            {fields.length === 0 && !error && <SkeletonChart className="h-64" />}

            {fields.length > 0 && (
                <div className="grid gap-4 xl:grid-cols-[1fr_20rem]">
                    <div className="space-y-4">
                        <ChartCard title="Rules" subtitle="Every field here is a real customer attribute — nothing free-text reaches the query">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2 text-xs">
                                    <span className="text-muted-foreground">Match</span>
                                    <Select value={rules.match} onValueChange={(value) => setRules((c) => ({ ...c, match: value as 'all' | 'any' }))}>
                                        <SelectTrigger className="h-8 w-28"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">all rules</SelectItem>
                                            <SelectItem value="any">any rule</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {rules.conditions.map((condition, index) => {
                                    const meta = fieldMap[condition.field];

                                    return (
                                        <div key={index} className="flex flex-wrap items-center gap-2">
                                            <Select
                                                value={condition.field}
                                                onValueChange={(value) => {
                                                    const next = fieldMap[value];
                                                    updateCondition(index, { field: value, operator: next.operators[0], value: '' });
                                                }}
                                            >
                                                <SelectTrigger className="h-9 w-52"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    {fields.map((field) => (
                                                        <SelectItem key={field.key} value={field.key}>{field.label}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>

                                            <Select value={condition.operator} onValueChange={(value) => updateCondition(index, { operator: value })}>
                                                <SelectTrigger className="h-9 w-48"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    {(meta?.operators ?? []).map((operator) => (
                                                        <SelectItem key={operator} value={operator}>{OPERATOR_LABELS[operator] ?? operator}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>

                                            {meta?.type === 'enum' ? (
                                                <Select value={String(condition.value)} onValueChange={(value) => updateCondition(index, { value })}>
                                                    <SelectTrigger className="h-9 w-44"><SelectValue placeholder="Pick one" /></SelectTrigger>
                                                    <SelectContent>
                                                        {(meta.options ?? []).map((option) => (
                                                            <SelectItem key={option} value={option}>{option.replace(/_/g, ' ')}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            ) : meta?.type === 'boolean' ? (
                                                <Select value={String(condition.value)} onValueChange={(value) => updateCondition(index, { value: value === 'true' })}>
                                                    <SelectTrigger className="h-9 w-32"><SelectValue placeholder="Yes / no" /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="true">Yes</SelectItem>
                                                        <SelectItem value="false">No</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            ) : (
                                                <Input
                                                    className="h-9 w-44"
                                                    type={meta?.type === 'date' ? 'date' : meta?.type === 'text' ? 'text' : 'number'}
                                                    placeholder={meta?.type === 'money' ? '₹ amount' : ''}
                                                    value={String(condition.value ?? '')}
                                                    onChange={(event) => updateCondition(index, { value: event.target.value })}
                                                />
                                            )}

                                            <Button variant="ghost" size="icon" onClick={() => removeCondition(index)} aria-label="Remove rule">
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    );
                                })}

                                <Button variant="outline" size="sm" onClick={addCondition}>
                                    <Plus className="size-3.5" /> Add a rule
                                </Button>
                            </div>
                        </ChartCard>

                        <ChartCard
                            title="Who is in it"
                            subtitle={previewing ? 'Counting…' : `${formatNumber(preview?.member_count ?? 0)} customers match`}
                            bodyClassName="p-0"
                            empty={(preview?.sample.length ?? 0) === 0}
                            emptyState={<p className="p-6 text-center text-sm text-muted-foreground">No customer matches these rules.</p>}
                        >
                            <DataTable columns={sampleColumns} rows={preview?.sample ?? []} rowKey={(row) => String(row.id)} dense />
                        </ChartCard>

                        {preview?.caveat && <CaveatNote caveat={preview.caveat} />}
                    </div>

                    <div className="space-y-3">
                        <Card className="space-y-2 p-4">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">This segment</p>
                            <p className="text-2xl font-semibold tnum">{formatNumber(preview?.member_count ?? 0)}</p>
                            <div className="space-y-1 text-xs text-muted-foreground">
                                <p>{formatCurrency(preview?.member_value ?? 0)} of lifetime spend</p>
                                <p>{formatCurrency(preview?.average_aov ?? 0)} average order value</p>
                                <p>{formatNumber(preview?.contactable ?? 0)} opted into marketing</p>
                            </div>
                        </Card>

                        {can('customer_intelligence.segments.manage') && (
                            <Card className="space-y-2 p-4">
                                <div className="space-y-1">
                                    <Label htmlFor="segment-name">Save as</Label>
                                    <Input id="segment-name" placeholder="High value, gone quiet" value={name} onChange={(e) => setName(e.target.value)} />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="segment-description">Note</Label>
                                    <Input id="segment-description" placeholder="Win-back list for March" value={description} onChange={(e) => setDescription(e.target.value)} />
                                </div>
                                <Button size="sm" className="w-full" onClick={save}>Save segment</Button>
                            </Card>
                        )}

                        <Card className="space-y-2 p-4">
                            <p className="text-sm font-semibold">Saved segments</p>
                            {segments.length === 0 && <p className="text-xs text-muted-foreground">Nothing saved yet.</p>}

                            {segments.map((segment) => (
                                <div key={segment.id} className="space-y-1.5 rounded-lg border border-border p-2.5">
                                    <div className="flex items-start justify-between gap-2">
                                        <button type="button" className="min-w-0 text-left" onClick={() => setRules(segment.rules)}>
                                            <p className="truncate text-xs font-medium">{segment.name}</p>
                                            <p className="text-[11px] text-muted-foreground">
                                                {formatNumber(segment.member_count)} people · {formatCurrency(segment.member_value)}
                                            </p>
                                        </button>
                                        <div className="flex shrink-0 items-center gap-0.5">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Recount"
                                                onClick={async () => {
                                                    await apiSend('POST', `/segments/${segment.id}/refresh`);
                                                    load();
                                                }}
                                            >
                                                <RefreshCw className="size-3.5" />
                                            </Button>
                                            {can('customer_intelligence.segments.export') && (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="icon" aria-label="Export">
                                                            <Download className="size-3.5" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-72">
                                                        {destinations.map((destination) => (
                                                            <DropdownMenuItem
                                                                key={destination.key}
                                                                className="flex-col items-start gap-0.5"
                                                                onClick={() => {
                                                                    window.location.href = `/api/segments/${segment.id}/export/${destination.key}`;
                                                                }}
                                                            >
                                                                <span className="text-xs font-medium">{destination.label}</span>
                                                                <span className="text-[11px] leading-snug text-muted-foreground">{destination.note}</span>
                                                            </DropdownMenuItem>
                                                        ))}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            )}
                                            {can('customer_intelligence.segments.manage') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Delete segment"
                                                    onClick={async () => {
                                                        await apiSend('DELETE', `/segments/${segment.id}`);
                                                        toast.success('Segment deleted.');
                                                        load();
                                                    }}
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                    {segment.description && <p className="text-[11px] text-muted-foreground">{segment.description}</p>}
                                    <Badge variant="muted">
                                        <Users className="mr-1 size-3" />
                                        {segment.rules.conditions.length} rule{segment.rules.conditions.length === 1 ? '' : 's'}
                                    </Badge>
                                </div>
                            ))}
                        </Card>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

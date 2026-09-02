import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Check, Circle, Loader2, Lock } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AppLayout } from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { SkeletonChart } from '@/components/ui/skeleton';
import { WidgetError } from '@/components/app/empty-state';
import { apiGet, apiSend } from '@/lib/api';
import { cn } from '@/lib/utils';

interface Step {
    key: string;
    title: string;
    summary: string;
    complete: boolean;
    evidence: string | null;
    blocked_by?: string | null;
    optional?: boolean;
}

interface OnboardingPayload {
    steps: Step[];
    business: {
        brand_name?: string;
        categories?: string[];
        channels?: string[];
        monthly_orders?: number;
        average_order_value?: number;
        gst_state?: string;
    } | null;
    progress: { done: number; total: number; pct: number };
    is_demo: boolean;
    dismissed: boolean;
    connectors: { id: string; label: string; summary: string }[];
}

const STEP_ACTIONS: Record<string, { label: string; href: string }> = {
    connect: { label: 'Go to connectors', href: '/connectors' },
    sync: { label: 'Watch the sync', href: '/connectors' },
    costs: { label: 'Enter costs', href: '/admin/users' },
    benchmarks: { label: 'Set targets', href: '/admin/users' },
    team: { label: 'Invite people', href: '/admin/users' },
};

export default function Onboarding() {
    const [data, setData] = useState<OnboardingPayload | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        brand_name: '',
        categories: '',
        monthly_orders: '',
        average_order_value: '',
        gst_state: '',
    });

    const load = useCallback(() => {
        apiGet<OnboardingPayload>('/onboarding')
            .then((response) => {
                setData(response.data);
                const business = response.data.business;
                if (business) {
                    setForm({
                        brand_name: business.brand_name ?? '',
                        categories: (business.categories ?? []).join(', '),
                        monthly_orders: business.monthly_orders ? String(business.monthly_orders) : '',
                        average_order_value: business.average_order_value ? String(business.average_order_value) : '',
                        gst_state: business.gst_state ?? '',
                    });
                }
                setError(null);
            })
            .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Could not load your setup.'));
    }, []);

    useEffect(load, [load]);

    const saveBusiness = async () => {
        setSaving(true);
        try {
            const response = await apiSend<null>('PUT', '/onboarding/business', {
                brand_name: form.brand_name || null,
                categories: form.categories.split(',').map((value) => value.trim()).filter(Boolean),
                monthly_orders: form.monthly_orders ? Number(form.monthly_orders) : null,
                average_order_value: form.average_order_value ? Number(form.average_order_value) : null,
                gst_state: form.gst_state || null,
            });
            toast.success(response.message);
            load();
            router.reload({ only: ['tenant'] });
        } catch (err) {
            toast.error(err instanceof Error ? err.message : 'Could not save.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <AppLayout
            title="Set up your data"
            description="Replace the sample data with your own numbers"
            showFilters={false}
        >
            <Head title="Set up your data" />

            {error && <WidgetError message={error} onRetry={load} />}
            {!data && !error && <SkeletonChart className="h-64" />}

            {data && (
                <div className="grid gap-4 lg:grid-cols-[1fr_22rem]">
                    <div className="space-y-3">
                        <Card className="p-4">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <p className="text-sm font-semibold">
                                        {data.progress.done} of {data.progress.total} steps done
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Each one is ticked from what is actually in your account, not from clicking through.
                                    </p>
                                </div>
                                <span className="text-2xl font-semibold tnum">{data.progress.pct}%</span>
                            </div>
                            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${data.progress.pct}%` }} />
                            </div>
                        </Card>

                        {data.steps.map((step, index) => (
                            <Card key={step.key} className={cn('p-4', step.complete && 'border-good/40 bg-good/5')}>
                                <div className="flex items-start gap-3">
                                    <span
                                        className={cn(
                                            'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold',
                                            step.complete ? 'bg-good text-white' : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {step.complete ? <Check className="size-3.5" /> : index + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="text-sm font-semibold">{step.title}</p>
                                            {step.optional && <Badge variant="muted">Optional</Badge>}
                                            {step.blocked_by && !step.complete && (
                                                <Badge variant="muted">
                                                    <Lock className="mr-1 size-3" /> needs "{step.blocked_by}" first
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs leading-snug text-muted-foreground">{step.summary}</p>
                                        {step.evidence && (
                                            <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                                <Circle className={cn('size-2 fill-current', step.complete ? 'text-good' : 'text-muted-foreground')} />
                                                {step.evidence}
                                            </p>
                                        )}
                                    </div>
                                    {STEP_ACTIONS[step.key] && !step.complete && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={STEP_ACTIONS[step.key].href}>
                                                {STEP_ACTIONS[step.key].label} <ArrowRight className="size-3.5" />
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </Card>
                        ))}
                    </div>

                    <div className="space-y-3">
                        <Card className="space-y-3 p-4">
                            <div>
                                <p className="text-sm font-semibold">Tell us about your business</p>
                                <p className="text-xs text-muted-foreground">
                                    This sets your first revenue target and your GST state. You can change all of it later.
                                </p>
                            </div>

                            <div className="space-y-1">
                                <Label htmlFor="brand_name">Brand name</Label>
                                <Input id="brand_name" value={form.brand_name} onChange={(e) => setForm((f) => ({ ...f, brand_name: e.target.value }))} />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="categories">What you sell</Label>
                                <Input
                                    id="categories"
                                    placeholder="Ethnic wear, Accessories"
                                    value={form.categories}
                                    onChange={(e) => setForm((f) => ({ ...f, categories: e.target.value }))}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-1">
                                    <Label htmlFor="monthly_orders">Orders / month</Label>
                                    <Input
                                        id="monthly_orders"
                                        type="number"
                                        value={form.monthly_orders}
                                        onChange={(e) => setForm((f) => ({ ...f, monthly_orders: e.target.value }))}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="average_order_value">Typical order (₹)</Label>
                                    <Input
                                        id="average_order_value"
                                        type="number"
                                        value={form.average_order_value}
                                        onChange={(e) => setForm((f) => ({ ...f, average_order_value: e.target.value }))}
                                    />
                                </div>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="gst_state">Your GST state</Label>
                                <Input
                                    id="gst_state"
                                    placeholder="Maharashtra"
                                    value={form.gst_state}
                                    onChange={(e) => setForm((f) => ({ ...f, gst_state: e.target.value }))}
                                />
                            </div>

                            <Button size="sm" className="w-full" onClick={saveBusiness} disabled={saving}>
                                {saving && <Loader2 className="size-3.5 animate-spin" />} Save
                            </Button>
                        </Card>

                        <Card className="space-y-2 p-4">
                            <p className="text-sm font-semibold">Sources you can connect now</p>
                            {data.connectors.map((connector) => (
                                <div key={connector.id} className="rounded-lg border border-border p-2.5">
                                    <p className="text-xs font-medium">{connector.label}</p>
                                    <p className="mt-0.5 text-[11px] leading-snug text-muted-foreground">{connector.summary}</p>
                                </div>
                            ))}
                            <Button variant="outline" size="sm" className="w-full" asChild>
                                <Link href="/connectors">Open connectors</Link>
                            </Button>
                        </Card>

                        {data.is_demo && !data.dismissed && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="w-full"
                                onClick={async () => {
                                    try {
                                        const response = await apiSend<null>('POST', '/onboarding/dismiss');
                                        toast.success(response.message);
                                        load();
                                    } catch {
                                        toast.error('Could not save that.');
                                    }
                                }}
                            >
                                Keep exploring the sample data for now
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

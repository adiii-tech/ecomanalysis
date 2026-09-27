import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input, Label } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { usePermissions } from '@/hooks/use-permissions';
import { apiGet, apiSend } from '@/lib/api';
import { formatCurrency, formatDate, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

interface SkuDetail {
    sku_id: number;
    sku_code: string;
    name: string;
    variant_title: string | null;
    category: string | null;
    brand: string | null;
    image_url: string | null;
    is_active: boolean;
    cost_price: number;
    selling_price: number;
    stock: number;
    stock_value: number;
    units_30d: number;
    daily_rate: number;
    days_of_cover: number;
    monthly_revenue: number;
    suggested_reorder_qty: number;
    margin_pct: number | null;
}

interface SkuMonth {
    month: string;
    label: string;
    full_label: string;
    units: number;
    revenue: number;
}

interface SkuHistory {
    months: SkuMonth[];
    units_30: number;
    units_60: number;
    units_90: number;
    lifetime_units: number;
    lifetime_revenue: number;
    returns_lifetime: number;
    first_sale: string | null;
}

interface CostHistoryEntry {
    id: number;
    cost_price: number;
    effective_from: string;
    note: string | null;
}

interface DetailPayload {
    sku: SkuDetail;
    history: SkuHistory;
    cost_history: CostHistoryEntry[];
}

/**
 * One SKU, opened from any catalog table — reorder plan, stockouts, dead stock
 * or the full list. Every table clicks into the exact same view, so a SKU
 * reads the same wherever it was found.
 */
export function SkuDetailDrawer({ skuId, onClose, onCostSaved }: { skuId: number | null; onClose: () => void; onCostSaved?: () => void }) {
    const { can } = usePermissions();
    const [data, setData] = useState<DetailPayload | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [hovered, setHovered] = useState<number | null>(null);
    const [editingCost, setEditingCost] = useState(false);
    const [costInput, setCostInput] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (skuId === null) return;

        const controller = new AbortController();
        setLoading(true);
        setFailed(false);
        setData(null);
        setEditingCost(false);
        setHovered(null);

        apiGet<DetailPayload>(`/catalog/skus/${skuId}`, {}, controller.signal)
            .then((response) => {
                setData(response.data);
                setCostInput((response.data.sku.cost_price / 100).toString());
            })
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) setFailed(true);
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [skuId]);

    if (skuId === null) return null;

    const sku = data?.sku;
    const history = data?.history;
    const peak = Math.max(1, ...(history?.months ?? []).map((month) => month.units));
    const shown = hovered !== null ? history?.months[hovered] : undefined;

    const saveCost = async () => {
        const rupees = Number(costInput);
        if (!Number.isFinite(rupees) || rupees < 0) {
            toast.error('Enter a valid cost.');
            return;
        }

        setSaving(true);
        try {
            const response = await apiSend<null>('PUT', `/catalog/skus/${skuId}/cost`, { cost_price: rupees });
            toast.success(response.message);
            setEditingCost(false);
            onCostSaved?.();
            // Re-fetch so the drawer's own numbers (margin %, stock value) catch up immediately.
            const refreshed = await apiGet<DetailPayload>(`/catalog/skus/${skuId}`);
            setData(refreshed.data);
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Could not save the cost.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle className="text-sm">{sku ? sku.name : <Skeleton className="h-4 w-32" />}</SheetTitle>
                    <SheetDescription>
                        {sku ? (
                            <>
                                {sku.sku_code}
                                {sku.variant_title ? ` · ${sku.variant_title}` : ''}
                            </>
                        ) : (
                            'Loading this SKU…'
                        )}
                    </SheetDescription>
                </SheetHeader>

                <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                    {loading && (
                        <div className="space-y-2">
                            <Skeleton className="h-24 w-full" />
                            <Skeleton className="h-32 w-full" />
                        </div>
                    )}

                    {failed && <p className="text-xs text-muted-foreground">Could not load this SKU.</p>}

                    {sku && (
                        <>
                            <div className="flex items-start gap-3">
                                {sku.image_url ? (
                                    <img src={sku.image_url} alt="" loading="lazy" referrerPolicy="no-referrer" className="size-20 shrink-0 rounded-lg border border-border object-cover" />
                                ) : (
                                    <div className="flex size-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-border text-[10px] text-muted-foreground">no photo</div>
                                )}
                                <div className="flex flex-wrap gap-1.5">
                                    <Badge variant="muted">{sku.category ?? 'Uncategorised'}</Badge>
                                    {sku.brand && <Badge variant="outline">{sku.brand}</Badge>}
                                    {!sku.is_active && <Badge variant="bad">Archived</Badge>}
                                    {sku.stock <= 0 && <Badge variant="bad">Out of stock</Badge>}
                                </div>
                            </div>

                            <dl className="grid grid-cols-3 gap-2">
                                {(
                                    [
                                        ['Stock', formatNumber(sku.stock), sku.stock <= 0 ? 'nothing to sell' : `${formatCurrency(sku.stock_value)} value`],
                                        ['Sold / day', sku.daily_rate.toFixed(2), `${formatNumber(sku.units_30d)} in 30 days`],
                                        ['Cover', sku.days_of_cover >= 999 ? '∞' : `${sku.days_of_cover.toFixed(1)}d`, sku.suggested_reorder_qty > 0 ? `reorder ${formatNumber(sku.suggested_reorder_qty)}` : ''],
                                        ['Selling price', formatCurrency(sku.selling_price), ''],
                                        ['Cost price', formatCurrency(sku.cost_price), sku.margin_pct === null ? '' : `${formatPercent(sku.margin_pct)} margin`],
                                        ['Rev / 30d', formatCurrency(sku.monthly_revenue), ''],
                                    ] as const
                                ).map(([label, value, note]) => (
                                    <div key={label} className="rounded-lg border border-border bg-muted/40 px-2.5 py-2">
                                        <dt className="text-[9px] font-medium uppercase tracking-wide text-muted-foreground">{label}</dt>
                                        <dd className="mt-0.5 tnum text-sm font-semibold">{value}</dd>
                                        {note && <p className="text-[10px] text-muted-foreground">{note}</p>}
                                    </div>
                                ))}
                            </dl>

                            {can('catalog.cost_editor.manage') && (
                                <section className="rounded-lg border border-dashed border-border p-2.5">
                                    {editingCost ? (
                                        <div className="flex items-end gap-1.5">
                                            <div className="flex-1 space-y-1">
                                                <Label htmlFor="sku-cost-input" className="text-[10px]">New cost (₹)</Label>
                                                <Input id="sku-cost-input" type="number" min={0} className="h-8" value={costInput} onChange={(event) => setCostInput(event.target.value)} />
                                            </div>
                                            <Button size="xs" onClick={saveCost} disabled={saving}>{saving ? 'Saving…' : 'Save'}</Button>
                                            <Button size="xs" variant="ghost" onClick={() => setEditingCost(false)} disabled={saving}>Cancel</Button>
                                        </div>
                                    ) : (
                                        <button type="button" onClick={() => setEditingCost(true)} className="text-xs font-medium text-primary hover:underline">
                                            Edit cost price
                                        </button>
                                    )}

                                    {(data?.cost_history.length ?? 0) > 0 && (
                                        <div className="mt-2 space-y-1 border-t border-border/60 pt-2">
                                            <p className="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Recent cost changes</p>
                                            {data!.cost_history.slice(0, 4).map((entry) => (
                                                <div key={entry.id} className="flex items-center justify-between text-[11px]">
                                                    <span className="text-muted-foreground">
                                                        {formatDate(entry.effective_from)}
                                                        {entry.note ? ` · ${entry.note}` : ''}
                                                    </span>
                                                    <span className="tnum font-medium">{formatCurrency(entry.cost_price)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </section>
                            )}

                            <section>
                                <div className="flex items-baseline justify-between gap-2">
                                    <h3 className="text-xs font-semibold">Sales — last 12 months</h3>
                                    <p className="tnum text-[11px] text-muted-foreground">
                                        {shown ? `${shown.full_label}: ${formatNumber(shown.units)} units · ${formatCurrency(shown.revenue)}` : `${formatNumber(history?.lifetime_units ?? 0)} units lifetime`}
                                    </p>
                                </div>
                                {history && (
                                    <div className="mt-2 flex h-24 items-end gap-[2px]" onMouseLeave={() => setHovered(null)}>
                                        {history.months.map((month, index) => (
                                            <button
                                                key={month.month}
                                                type="button"
                                                onMouseEnter={() => setHovered(index)}
                                                onFocus={() => setHovered(index)}
                                                title={`${month.full_label}: ${formatNumber(month.units)} units · ${formatCurrency(month.revenue)}`}
                                                className="flex min-w-0 flex-1 cursor-default flex-col items-center gap-1"
                                            >
                                                <span
                                                    className={cn('w-full rounded-t-[4px] transition-opacity', month.units > 0 ? 'bg-[var(--chart-1)]' : 'bg-muted')}
                                                    style={{ height: `${Math.max(2, Math.round((month.units / peak) * 72))}px`, opacity: hovered === null || hovered === index ? 1 : 0.45 }}
                                                />
                                                <span className="truncate text-[9px] text-muted-foreground">{month.label}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </section>

                            {history && (
                                <section className="grid grid-cols-3 gap-2">
                                    {(
                                        [
                                            ['30 days', history.units_30],
                                            ['60 days', history.units_60],
                                            ['90 days', history.units_90],
                                        ] as const
                                    ).map(([label, units]) => (
                                        <div key={label} className="rounded-lg border border-border px-2.5 py-2">
                                            <p className="text-[9px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                                            <p className="mt-0.5 tnum text-sm font-semibold">{formatNumber(units)}</p>
                                        </div>
                                    ))}
                                    {history.returns_lifetime > 0 && (
                                        <p className="col-span-3 text-[11px] text-bad">{formatNumber(history.returns_lifetime)} units returned, lifetime</p>
                                    )}
                                </section>
                            )}
                        </>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

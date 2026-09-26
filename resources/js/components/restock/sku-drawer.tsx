import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { apiGet } from '@/lib/api';
import { formatCurrency, formatNumber } from '@/lib/format';
import { BUCKET_LABEL, BUCKET_VARIANT, poEligible, type RestockRow, type RestockSettings, type SkuHistory } from '@/lib/restock';
import { cn } from '@/lib/utils';

/**
 * One product, opened from the restock list: what it has done, what the desk
 * thinks you should buy, and the arithmetic that produced that number.
 *
 * The reorder maths is spelled out rather than summarised on purpose — a buyer
 * who cannot see where a quantity came from has no way to disagree with it.
 */
export function SkuDrawer({
    row,
    settings,
    included,
    quantity,
    onToggleInclude,
    onQuantity,
    onClose,
}: {
    row: RestockRow | null;
    settings: RestockSettings;
    included: boolean;
    quantity: number;
    onToggleInclude: (skuId: number, include: boolean) => void;
    onQuantity: (skuId: number, qty: number) => void;
    onClose: () => void;
}) {
    const [history, setHistory] = useState<SkuHistory | null>(null);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [hovered, setHovered] = useState<number | null>(null);

    const skuId = row?.sku_id ?? null;

    useEffect(() => {
        if (skuId === null) return;

        const controller = new AbortController();
        setLoading(true);
        setFailed(false);
        setHistory(null);
        setHovered(null);

        apiGet<SkuHistory>(`/restock/sku/${skuId}`, { returns: settings.returns }, controller.signal)
            .then((response) => setHistory(response.data))
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError')) setFailed(true);
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [skuId, settings.returns]);

    if (row === null) return null;

    const outOfStock = row.stock <= 0;
    const peak = Math.max(1, ...(history?.months ?? []).map((month) => month.units));
    const monthTotal = (history?.months ?? []).reduce((sum, month) => sum + month.units, 0);
    const shown = hovered !== null ? history?.months[hovered] : undefined;

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent side="right" className="sm:max-w-md">
                <SheetHeader>
                    <SheetTitle className="text-sm">{row.name}</SheetTitle>
                    <p className="font-mono text-[11px] text-muted-foreground">
                        {row.sku_code}
                        {row.variant_title ? ` · ${row.variant_title}` : ''}
                    </p>
                    <p className="text-[11px] text-muted-foreground">
                        {[row.type, row.supplier_name ?? 'no supplier', row.product_status ?? '—'].join(' · ')}
                    </p>
                </SheetHeader>

                <div className="flex-1 overflow-y-auto px-5 py-4">
                    <div className="flex items-start gap-3">
                        {row.image_url ? (
                            <img
                                src={row.image_url}
                                alt=""
                                loading="lazy"
                                referrerPolicy="no-referrer"
                                className="size-20 shrink-0 rounded-lg border border-border object-cover"
                            />
                        ) : (
                            <div className="flex size-20 shrink-0 items-center justify-center rounded-lg border border-dashed border-border text-[10px] text-muted-foreground">
                                no photo
                            </div>
                        )}
                        <div className="flex flex-wrap gap-1.5">
                            <Badge variant={BUCKET_VARIANT[row.bucket] ?? 'muted'}>{BUCKET_LABEL[row.bucket] ?? row.bucket}</Badge>
                            <Badge variant="muted">ABC {row.abc}</Badge>
                            {row.bestseller_rank && <Badge variant="secondary">#{formatNumber(row.bestseller_rank)} bestseller</Badge>}
                            {outOfStock && <Badge variant="bad">Out of stock</Badge>}
                        </div>
                    </div>

                    <dl className="mt-4 grid grid-cols-3 gap-2">
                        {(
                            [
                                ['Available', formatNumber(row.stock), outOfStock ? 'nothing to sell' : 'units on hand'],
                                ['Incoming', formatNumber(row.incoming), 'on order'],
                                ['Cover', outOfStock ? 'OUT' : row.cover_days === null ? '∞' : `${Math.round(row.cover_days)}d`, row.days_since_sale === null ? 'never sold' : `last sale ${row.days_since_sale}d ago`],
                                ['Unit cost', formatCurrency(row.unit_cost), row.cost_estimated ? 'estimated' : 'actual'],
                                ['Stock value', formatCurrency(row.stock_value), 'at cost'],
                                ['₹ blocked', row.blocked_value ? formatCurrency(row.blocked_value) : '—', row.bucket === 'dead' ? 'dead stock' : row.bucket === 'overstock' ? 'beyond target' : ''],
                            ] as const
                        ).map(([label, value, note]) => (
                            <div key={label} className="rounded-lg border border-border bg-muted/40 px-2.5 py-2">
                                <dt className="text-[9px] font-medium uppercase tracking-wide text-muted-foreground">{label}</dt>
                                <dd className={cn('mt-0.5 tnum text-sm font-semibold', label === 'Unit cost' && row.cost_estimated && 'text-warn')}>{value}</dd>
                                {note && <p className="text-[10px] text-muted-foreground">{note}</p>}
                            </div>
                        ))}
                    </dl>

                    <section className="mt-5 border-t border-border pt-3">
                        <div className="flex items-baseline justify-between gap-2">
                            <h3 className="text-xs font-semibold">Sales — last 12 months</h3>
                            <p className="tnum text-[11px] text-muted-foreground">
                                {shown
                                    ? `${shown.full_label}: ${formatNumber(shown.units)} units · ${formatCurrency(shown.revenue)}`
                                    : `${formatNumber(monthTotal)} units`}
                            </p>
                        </div>

                        {loading && <Skeleton className="mt-2 h-24 w-full" />}
                        {failed && <p className="mt-2 text-[11px] text-muted-foreground">Could not load this product's history.</p>}

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

                    <section className="mt-5 border-t border-border pt-3">
                        <h3 className="text-xs font-semibold">Pace</h3>
                        <div className="mt-2 grid grid-cols-2 gap-2">
                            {history &&
                                (
                                    [
                                        ['30 days', history.units_30, history.units_30 / 30],
                                        ['60 days', history.units_60, history.units_60 / 60],
                                        ['90 days', history.units_90, history.units_90 / 90],
                                    ] as const
                                ).map(([label, units, perDay]) => (
                                    <div key={label} className="rounded-lg border border-border px-2.5 py-2">
                                        <p className="text-[9px] font-medium uppercase tracking-wide text-muted-foreground">{label}</p>
                                        <p className="mt-0.5 tnum text-sm font-semibold">{formatNumber(units)}</p>
                                        <p className="tnum text-[10px] text-muted-foreground">{perDay.toFixed(2)}/day</p>
                                    </div>
                                ))}

                            {row.projected_velocity > 0 && (
                                <div className="rounded-lg border border-border bg-muted/40 px-2.5 py-2">
                                    <p className="text-[9px] font-medium uppercase tracking-wide text-muted-foreground">Projected</p>
                                    <p className="mt-0.5 tnum text-sm font-semibold">{row.projected_velocity.toFixed(2)}/day</p>
                                    <p className="tnum text-[10px] text-muted-foreground">
                                        ≈{formatNumber(Math.round(row.projected_velocity * 30))} in 30d{row.projection_drives ? ' · driving the order' : ''}
                                    </p>
                                </div>
                            )}

                            {history && (
                                <div className="col-span-2 rounded-lg border border-border bg-good-soft/40 px-2.5 py-2">
                                    <p className="text-[9px] font-medium uppercase tracking-wide text-muted-foreground">Lifetime</p>
                                    <p className="mt-0.5 tnum text-sm font-semibold">{formatNumber(history.lifetime_units)} units</p>
                                    <p className="tnum text-[10px] text-muted-foreground">
                                        {history.lifetime_units > 0
                                            ? `${formatCurrency(history.lifetime_revenue)} · first sold ${history.first_sale ?? '—'}`
                                            : 'never sold'}
                                        {history.returns_lifetime > 0 && <span className="text-bad"> · {formatNumber(history.returns_lifetime)} returned</span>}
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="mt-5 border-t border-border pt-3">
                        <h3 className="text-xs font-semibold">Reorder maths</h3>
                        <div className="mt-2 rounded-lg border border-dashed border-border bg-muted/30 px-3 py-2.5 text-[11px] leading-relaxed">
                            {reorderMaths(row, settings)}
                        </div>
                    </section>

                    <section className="mt-4">
                        {poEligible(row) ? (
                            <div className="flex flex-wrap items-center gap-3 rounded-lg bg-good-soft px-3 py-2.5">
                                <label className="flex cursor-pointer items-center gap-2 text-xs font-semibold">
                                    <input
                                        type="checkbox"
                                        checked={included}
                                        onChange={(event) => onToggleInclude(row.sku_id, event.target.checked)}
                                        className="size-4 accent-[var(--good)]"
                                    />
                                    On the purchase order
                                </label>
                                {included && (
                                    <>
                                        <Input
                                            type="number"
                                            min={1}
                                            className="h-7 w-20 text-right tnum font-semibold"
                                            value={quantity}
                                            onChange={(event) => onQuantity(row.sku_id, Math.max(1, Math.round(+event.target.value || 0)))}
                                        />
                                        <span className="ml-auto tnum text-xs font-medium">{formatCurrency(quantity * row.unit_cost)}</span>
                                    </>
                                )}
                            </div>
                        ) : (
                            <p className="rounded-lg bg-muted px-3 py-2.5 text-[11px] text-muted-foreground">
                                Virtual combo — it holds no stock of its own, so it cannot be ordered.
                            </p>
                        )}
                    </section>
                </div>
            </SheetContent>
        </Sheet>
    );
}

/** The sentence that has to survive a buyer asking "why that number?". */
function reorderMaths(row: RestockRow, settings: RestockSettings) {
    if (row.bucket === 'new') {
        return `Added ${row.age_days === null ? 'recently' : `${row.age_days} days ago`} — no sales yet, but too new to judge, so it is not counted as dead. It finds its real bucket as sales arrive, or moves to Dead after ${settings.dead} days with none.`;
    }

    if (row.bucket === 'dead') {
        return `No sale in ${row.days_since_sale === null ? 'the whole window' : `${row.days_since_sale} days`}${row.age_days === null ? '' : ` · added ${row.age_days}d ago`} — nothing suggested. Clear this stock rather than repeat it.`;
    }

    if (row.bucket === 'overstock') {
        return `Cover is ${row.cover_days === null ? '∞' : `${Math.round(row.cover_days)} days`} at the current pace — already past your ${settings.over}-day overstock line.${row.excess_units ? ` ${row.excess_units} excess units (${formatCurrency(row.blocked_value)}) to clear first.` : ''}`;
    }

    if (row.suggested_qty > 0) {
        const need = Math.ceil(settings.target * row.velocity);

        return (
            <>
                Target {settings.target}d × {row.velocity.toFixed(2)}/day = {formatNumber(need)} needed → minus {formatNumber(Math.max(row.stock, 0))} in stock → minus{' '}
                {formatNumber(row.incoming)} incoming = <b className="text-bad">{formatNumber(row.suggested_qty)} to order</b>
                {settings.round > 1 && ` (rounded to ×${settings.round})`}.
                {row.projection_drives && (
                    <>
                        <br />★ Projection is driving this: ≈{row.projected_velocity.toFixed(2)}/day expected beats the real pace.
                    </>
                )}
                {row.velocity_adjusted && (
                    <>
                        <br />† Velocity is stockout-adjusted — it counts only the days this was actually on the shelf.
                    </>
                )}
                {row.low_confidence && (
                    <>
                        <br />! Under 5 sales in the window — treat the quantity as a judgement call.
                    </>
                )}
            </>
        );
    }

    if (row.bucket === 'healthy') {
        return `Cover ${row.cover_days === null ? '∞' : `${Math.round(row.cover_days)} days`} — inside target, nothing to order yet. The reorder point arrives at ${settings.lead + settings.safety}d of cover.`;
    }

    if ((row.bucket === 'reorder' || row.bucket === 'soon') && row.velocity > 0) {
        return `Cover is short, but ${formatNumber(row.incoming)} already incoming covers the ${settings.target}-day target — nothing extra to order until it lands.`;
    }

    return 'No sales signal to size an order from. If you order this, set the quantity by hand below.';
}

/**
 * The restock desk's shared vocabulary: the shapes the API returns, how a
 * purchase order is assembled from the rows, and the files that leave the page.
 *
 * It lives outside the page component because the table, the detail drawer and
 * the three exports all have to agree on which rows are on the order and at
 * what quantity — a disagreement there would send a supplier the wrong sheet.
 */

export interface RestockRow {
    sku_id: number;
    sku_code: string;
    name: string;
    variant_title: string | null;
    type: string;
    supplier_name: string | null;
    product_status: string | null;
    image_url: string | null;
    stock: number;
    incoming: number;
    units_window: number;
    units_30: number;
    units_prev_30: number;
    returns_window: number;
    revenue_window: number;
    velocity: number;
    projected_velocity: number;
    projection_drives: boolean;
    velocity_adjusted: boolean;
    low_confidence: boolean;
    cover_days: number | null;
    days_since_sale: number | null;
    age_days: number | null;
    bucket: string;
    suggested_qty: number;
    order_value: number;
    unit_cost: number;
    cost_estimated: boolean;
    stock_value: number;
    blocked_value: number;
    excess_units: number;
    abc: string;
    bestseller_rank: number | null;
    bestseller_units: number;
    is_top_seller: boolean;
}

export interface BucketRow {
    bucket: string;
    skus: number;
    stock_value: number;
    blocked_value: number;
    order_value: number;
}

export interface CategoryRow {
    type: string;
    skus_in_stock: number;
    stock_value: number;
    blocked_value: number;
    units: number;
    sales_share_pct: number;
}

export interface RestockSummary {
    stock_value: number;
    working_value: number;
    dead_value: number;
    excess_value: number;
    releasable_value: number;
    spend_now: number;
    spend_soon: number;
    out_of_stock_sellers: number;
    skus_holding_stock: number;
    dead_after_days: number;
}

export interface RestockPayload {
    rows: RestockRow[];
    summary: RestockSummary;
    buckets: BucketRow[];
    categories: CategoryRow[];
    health: { level: string; text: string }[];
    anchor: string;
    window_days_used: number;
    history_days: number;
}

export interface SkuMonth {
    month: string;
    label: string;
    full_label: string;
    units: number;
    revenue: number;
}

export interface SkuHistory {
    months: SkuMonth[];
    units_30: number;
    units_60: number;
    units_90: number;
    lifetime_units: number;
    lifetime_revenue: number;
    returns_lifetime: number;
    first_sale: string | null;
    anchor: string;
}

export interface RestockSettings {
    window: string;
    lead: number;
    safety: number;
    target: number;
    over: number;
    dead: number;
    new_days: number;
    round: number;
    projection: number;
    best: string;
    exclude: string;
    returns: string;
    cost_mode: string;
    cost_value: number;
    /** In rupees, as typed. Converted to paise where it is compared against order values. */
    budget: number;
}

export const RESTOCK_DEFAULTS: RestockSettings = {
    window: '90',
    lead: 21,
    safety: 14,
    target: 60,
    over: 150,
    dead: 90,
    new_days: 30,
    round: 1,
    projection: 0,
    best: 'all',
    exclude: 'stack, combo',
    returns: 'net',
    cost_mode: 'flat',
    cost_value: 150,
    budget: 0,
};

export const BUCKET_LABEL: Record<string, string> = {
    reorder: 'Reorder now',
    soon: 'Order soon',
    healthy: 'Healthy',
    overstock: 'Overstock',
    dead: 'Dead stock',
    new: 'New',
    inactive: 'Inactive',
    excluded: 'Excluded',
};

export const BUCKET_VARIANT: Record<string, 'good' | 'bad' | 'warn' | 'muted' | 'secondary'> = {
    reorder: 'bad',
    soon: 'warn',
    healthy: 'good',
    overstock: 'secondary',
    dead: 'muted',
    new: 'good',
    inactive: 'muted',
    excluded: 'muted',
};

/** The order a buyer should work through the page in. Drives the default sort. */
const BUCKET_RANK: Record<string, number> = {
    reorder: 0,
    soon: 1,
    dead: 2,
    overstock: 3,
    healthy: 4,
    new: 5,
    inactive: 6,
    excluded: 7,
};

/** Which capital-bar segment a bucket belongs to, for the hero filters. */
export const SEGMENT_BUCKETS: Record<string, string[]> = {
    working: ['reorder', 'soon', 'healthy', 'new'],
    excess: ['overstock'],
    dead: ['dead'],
};

/**
 * Worst-first, the way a buyer reads the list: what is already out before what
 * is merely short, then the money sitting still, then everything that is fine.
 */
export function byAttention(a: RestockRow, b: RestockRow): number {
    const rankA = BUCKET_RANK[a.bucket] ?? 9;
    const rankB = BUCKET_RANK[b.bucket] ?? 9;

    if (rankA !== rankB) return rankA - rankB;

    if (a.bucket === 'reorder' || a.bucket === 'soon') {
        const outA = a.stock <= 0;
        const outB = b.stock <= 0;
        if (outA !== outB) return outA ? -1 : 1;
        return (a.cover_days ?? 0) - (b.cover_days ?? 0) || b.order_value - a.order_value;
    }

    if (a.bucket === 'dead' || a.bucket === 'overstock') return b.blocked_value - a.blocked_value;

    return b.stock_value - a.stock_value;
}

/* ---------- purchase order assembly ---------- */

/** A row the desk would put on the order by itself. */
export function poSuggested(row: RestockRow): boolean {
    return (row.bucket === 'reorder' || row.bucket === 'soon') && row.suggested_qty > 0;
}

/** A virtual combo holds no stock of its own, so it can never be ordered. */
export function poEligible(row: RestockRow): boolean {
    return row.bucket !== 'excluded';
}

export function poIncluded(row: RestockRow, overrides: Record<number, boolean>): boolean {
    if (!poEligible(row)) return false;
    return overrides[row.sku_id] ?? poSuggested(row);
}

/**
 * A row added by hand has no suggested quantity, so one is derived from the
 * same target-cover sum the suggestion uses — never zero, or the line would go
 * to the supplier asking for nothing.
 */
export function poQtyOf(row: RestockRow, quantities: Record<number, number>, settings: RestockSettings): number {
    const override = quantities[row.sku_id];
    if (override !== undefined && override > 0) return override;
    if (row.suggested_qty > 0) return row.suggested_qty;

    const step = Math.max(1, settings.round || 1);
    const need = Math.ceil((settings.target || 60) * row.velocity) - Math.max(row.stock, 0) - row.incoming;

    return Math.ceil(Math.max(need, step) / step) * step;
}

export interface PoLine {
    row: RestockRow;
    qty: number;
    value: number;
    /** null when no budget is set, otherwise whether the line fits under it. */
    withinBudget: boolean | null;
}

/**
 * The order in priority sequence, with the budget cap applied down the list, so
 * a cap always keeps the most urgent lines rather than whichever sorted first.
 */
export function buildPoLines(
    rows: RestockRow[],
    overrides: Record<number, boolean>,
    quantities: Record<number, number>,
    settings: RestockSettings,
): { lines: PoLine[]; budget: number; fittedValue: number; fittedCount: number } {
    // The buyer types a budget in rupees; every value on this page is in paise.
    const budget = Math.max(0, Math.round((settings.budget || 0) * 100));
    const selected = rows.filter((row) => poIncluded(row, overrides)).sort(byAttention);

    let cumulative = 0;
    let capped = false;
    let fittedCount = 0;

    const lines = selected.map((row) => {
        const qty = poQtyOf(row, quantities, settings);
        const value = qty * row.unit_cost;
        let withinBudget: boolean | null = null;

        if (budget > 0) {
            if (!capped && cumulative + value <= budget) {
                cumulative += value;
                fittedCount++;
                withinBudget = true;
            } else {
                capped = true;
                withinBudget = false;
            }
        }

        return { row, qty, value, withinBudget };
    });

    return { lines, budget, fittedValue: cumulative, fittedCount };
}

/* ---------- PO column registry ---------- */

export interface PoColumn {
    key: string;
    label: string;
    /** On by default: everything a supplier needs and nothing they do not. */
    vendorSafe: boolean;
    /** Our own numbers. Off by default so they never reach a supplier by accident. */
    internal?: boolean;
    align?: 'right';
}

export const PO_COLUMNS: PoColumn[] = [
    { key: 'photo', label: 'Photo', vendorSafe: true },
    { key: 'sku', label: 'SKU', vendorSafe: true },
    { key: 'product', label: 'Product', vendorSafe: true },
    { key: 'type', label: 'Type', vendorSafe: false },
    { key: 'vendor', label: 'Supplier', vendorSafe: false },
    { key: 'stock', label: 'Stock on hand', vendorSafe: false, internal: true, align: 'right' },
    { key: 'incoming', label: 'Incoming', vendorSafe: false, internal: true, align: 'right' },
    { key: 'velocity', label: 'Sold / day', vendorSafe: false, internal: true, align: 'right' },
    { key: 'cover', label: 'Cover days', vendorSafe: false, internal: true, align: 'right' },
    { key: 'bestRank', label: 'Bestseller rank', vendorSafe: false },
    { key: 'orderQty', label: 'Order qty', vendorSafe: true, align: 'right' },
    { key: 'unitCost', label: 'Unit cost', vendorSafe: false, internal: true, align: 'right' },
    { key: 'orderValue', label: 'Order value', vendorSafe: false, internal: true, align: 'right' },
    { key: 'urgency', label: 'Priority', vendorSafe: false },
    { key: 'withinBudget', label: 'Within budget', vendorSafe: false },
];

export const PO_COLUMNS_STORAGE_KEY = 'restock_po_columns';

export function vendorSafeColumns(): string[] {
    return PO_COLUMNS.filter((column) => column.vendorSafe).map((column) => column.key);
}

export function loadPoColumns(): string[] {
    try {
        const saved = localStorage.getItem(PO_COLUMNS_STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved) as unknown;
            if (Array.isArray(parsed) && parsed.length > 0) {
                return parsed.filter((key): key is string => typeof key === 'string');
            }
        }
    } catch {
        /* a browser refusing storage should not cost the user their column choice */
    }

    return vendorSafeColumns();
}

export function savePoColumns(keys: string[]): void {
    try {
        localStorage.setItem(PO_COLUMNS_STORAGE_KEY, JSON.stringify(keys));
    } catch {
        /* see above */
    }
}

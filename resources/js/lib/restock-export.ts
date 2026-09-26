/**
 * The files that leave the restock desk: the working CSV a buyer keeps, the
 * purchase order in two CSV shapes, and a printable photo PO a supplier can
 * actually read.
 *
 * Column choice matters here beyond layout — cost, stock and velocity are our
 * numbers, not a supplier's, so every export takes the chosen column set rather
 * than assuming one.
 */
import { formatCurrency, formatNumber } from '@/lib/format';
import { BUCKET_LABEL, PO_COLUMNS, type PoLine, type RestockRow, type RestockSettings } from '@/lib/restock';

const rupees = (paise: number): string => (paise / 100).toFixed(2);

function csvCell(value: string | number | null | undefined): string {
    const text = String(value ?? '');
    return /[",\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}

function csvOf(header: string[], lines: (string | number | null | undefined)[][]): string {
    return [header, ...lines].map((line) => line.map(csvCell).join(',')).join('\n');
}

const stamp = (): string => new Date().toISOString().slice(0, 10);

/**
 * A blob download, with a new-tab fallback: the desk is sometimes opened inside
 * a preview frame where `download` is blocked, and silently doing nothing would
 * read as a broken button.
 */
export function downloadFile(name: string, text: string, mime = 'text/csv'): boolean {
    const body = mime.includes('csv') ? `﻿${text}` : text;

    try {
        const url = URL.createObjectURL(new Blob([body], { type: `${mime};charset=utf-8` }));
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = name;
        anchor.rel = 'noopener';
        anchor.style.display = 'none';
        document.body.appendChild(anchor);
        anchor.click();
        setTimeout(() => {
            anchor.remove();
            URL.revokeObjectURL(url);
        }, 4000);

        return true;
    } catch {
        /* fall through to the window fallback below */
    }

    try {
        const opened = window.open('', '_blank');
        if (opened) {
            opened.document.open(mime.includes('html') ? 'text/html' : 'text/plain');
            opened.document.write(text);
            opened.document.close();

            return true;
        }
    } catch {
        /* nothing else to try */
    }

    return false;
}

/** The buyer's own working copy: every column the table can show, filtered as on screen. */
export function exportViewCsv(rows: RestockRow[], qtyFor: (row: RestockRow) => number): boolean {
    const csv = csvOf(
        [
            'SKU', 'Product', 'Variant', 'Type', 'Supplier', 'Status', 'ABC', 'Bestseller rank', 'Bestseller units',
            'Stock', 'Incoming', 'Sold in window', 'Sold per day', 'Velocity basis', 'Confidence', 'Cover days',
            'Days since last sale', 'Bucket', 'Order qty', 'Unit cost', 'Cost source', 'Order value', 'Rs blocked', 'Stock value',
        ],
        rows.map((row) => [
            row.sku_code, row.name, row.variant_title ?? '', row.type, row.supplier_name ?? '', row.product_status ?? '',
            row.abc, row.bestseller_rank ?? '', row.bestseller_units,
            row.stock, row.incoming, row.units_window, row.velocity.toFixed(3),
            row.velocity_adjusted ? 'stockout-adjusted' : 'standard',
            row.low_confidence ? 'low (under 5 sales)' : 'ok',
            row.stock <= 0 ? 0 : (row.cover_days ?? ''),
            row.days_since_sale ?? '',
            BUCKET_LABEL[row.bucket] ?? row.bucket,
            qtyFor(row) || '', rupees(row.unit_cost), row.cost_estimated ? 'estimated' : 'actual',
            qtyFor(row) ? rupees(qtyFor(row) * row.unit_cost) : '',
            row.blocked_value ? rupees(row.blocked_value) : '',
            rupees(row.stock_value),
        ]),
    );

    return downloadFile(`restock-view-${stamp()}.csv`, csv);
}

/** Product occupies two columns, so the header is built from the same walk as the cells. */
function poCsvHeader(columns: string[]): string[] {
    return PO_COLUMNS.filter((column) => columns.includes(column.key)).flatMap((column) => {
        if (column.key === 'photo') return ['Image URL'];
        if (column.key === 'product') return ['Product', 'Variant'];

        return [column.label];
    });
}

function poCsvCells(line: PoLine, columns: string[], budget: number): (string | number)[] {
    const { row, qty, value, withinBudget } = line;

    return PO_COLUMNS.filter((column) => columns.includes(column.key)).flatMap((column): (string | number)[] => {
        switch (column.key) {
            case 'photo': return [row.image_url ?? ''];
            case 'sku': return [row.sku_code];
            case 'product': return [row.name, row.variant_title ?? ''];
            case 'type': return [row.type];
            case 'vendor': return [row.supplier_name ?? ''];
            case 'stock': return [row.stock];
            case 'incoming': return [row.incoming];
            case 'velocity': return [row.velocity.toFixed(3)];
            case 'cover': return [row.stock <= 0 ? 0 : Math.round(row.cover_days ?? 0)];
            case 'bestRank': return [row.bestseller_rank ? `#${row.bestseller_rank}` : ''];
            case 'orderQty': return [qty];
            case 'unitCost': return [rupees(row.unit_cost)];
            case 'orderValue': return [rupees(value)];
            case 'urgency':
                return [row.stock <= 0 ? 'STOCKED OUT' : row.bucket === 'reorder' ? 'Reorder now' : row.bucket === 'soon' ? 'Order soon' : 'Added by hand'];
            case 'withinBudget': return [budget > 0 ? (withinBudget ? 'Y' : 'N') : ''];
            default: return [''];
        }
    });
}

export function exportPoCsv(lines: PoLine[], columns: string[], budget: number): boolean {
    const header = poCsvHeader(columns);
    const body = lines.map((line) => poCsvCells(line, columns, budget));

    const totalQty = lines.reduce((sum, line) => sum + line.qty, 0);
    const totalValue = lines.reduce((sum, line) => sum + line.value, 0);

    // The totals row is laid out against the chosen columns, so it lands under
    // the quantity and value wherever the buyer put them.
    const totals = header.map((label) =>
        label === 'Order qty' ? String(totalQty) : label === 'Order value' ? rupees(totalValue) : label === 'SKU' || label === 'Product' ? 'TOTAL' : '',
    );

    return downloadFile(`purchase-order-${stamp()}.csv`, csvOf(header, [...body, totals]));
}

/** A supplier's fixed sheet — five columns, in their order, nothing else. */
export function exportAnjaniPo(lines: PoLine[]): boolean {
    const totalQty = lines.reduce((sum, line) => sum + line.qty, 0);

    const csv = csvOf(
        ['SKU', 'IMAGE-NAME', 'DESCRIPTION', 'PO QTY', 'REMARKS'],
        [
            ...lines.map(({ row, qty }) => [
                row.sku_code,
                row.image_url ?? '',
                [row.name, row.variant_title].filter(Boolean).join(' - '),
                qty,
                '',
            ]),
            ['TOTAL', '', '', totalQty, ''],
        ],
    );

    return downloadFile(`anjani-po-${stamp()}.csv`, csv);
}

const escapeHtml = (value: string | null | undefined): string =>
    String(value ?? '').replace(/[&<>"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[character] as string);

/**
 * The printable PO. Grouped by supplier because that is how it gets sent, and
 * photo-first because a supplier recognises the product long before the SKU.
 */
export function exportPoPhotos(
    lines: PoLine[],
    columns: string[],
    options: { budget: number; fittedValue: number; hasEstimatedCost: boolean; settings: RestockSettings },
): boolean {
    const showMoney = columns.includes('orderValue');
    const groups = new Map<string, PoLine[]>();

    for (const line of lines) {
        const vendor = (line.row.supplier_name ?? '').trim() || 'Unspecified supplier';
        groups.set(vendor, [...(groups.get(vendor) ?? []), line]);
    }

    const cell = (line: PoLine, key: string): string => {
        const { row, qty, value, withinBudget } = line;
        const right = PO_COLUMNS.find((column) => column.key === key)?.align === 'right' ? ' class="r"' : '';

        switch (key) {
            case 'photo':
                return row.image_url
                    ? `<td class="thumb"><a href="${escapeHtml(row.image_url)}" target="_blank" rel="noopener"><img src="${escapeHtml(row.image_url)}" loading="lazy" referrerpolicy="no-referrer"></a></td>`
                    : '<td class="thumb ni"></td>';
            case 'sku': return `<td class="sku">${escapeHtml(row.sku_code)}</td>`;
            case 'product': return `<td><div class="nm">${escapeHtml(row.name)}</div>${row.variant_title ? `<div class="vt">${escapeHtml(row.variant_title)}</div>` : ''}</td>`;
            case 'type': return `<td>${escapeHtml(row.type)}</td>`;
            case 'vendor': return `<td>${escapeHtml(row.supplier_name)}</td>`;
            case 'stock': return `<td${right}>${formatNumber(row.stock)}</td>`;
            case 'incoming': return `<td${right}>${row.incoming ? formatNumber(row.incoming) : ''}</td>`;
            case 'velocity': return `<td${right}>${row.velocity.toFixed(2)}</td>`;
            case 'cover': return `<td${right}>${row.stock <= 0 ? '0' : Math.round(row.cover_days ?? 0)}d</td>`;
            case 'bestRank': return `<td>${row.bestseller_rank ? `#${formatNumber(row.bestseller_rank)}` : ''}</td>`;
            case 'orderQty': return `<td class="r q">${formatNumber(qty)}</td>`;
            case 'unitCost': return `<td${right}>${formatCurrency(row.unit_cost)}</td>`;
            case 'orderValue': return `<td class="r ov">${formatCurrency(value)}</td>`;
            case 'urgency':
                return `<td>${row.stock <= 0 ? '<b class="out">STOCKED OUT</b>' : row.bucket === 'reorder' ? 'Reorder now' : row.bucket === 'soon' ? '<span class="soon">Order soon</span>' : 'Added by hand'}${options.budget > 0 && withinBudget === false ? ' <span class="nb">over budget</span>' : ''}</td>`;
            case 'withinBudget':
                return `<td>${options.budget > 0 ? (withinBudget ? '<span class="yb">yes</span>' : '<span class="nb">over</span>') : ''}</td>`;
            default: return '<td></td>';
        }
    };

    const head = PO_COLUMNS.filter((column) => columns.includes(column.key))
        .map((column) => `<th${column.align === 'right' ? ' class="r"' : ''}>${escapeHtml(column.label)}</th>`)
        .join('');

    let grandValue = 0;
    let grandQty = 0;
    let grandItems = 0;
    let body = '';

    for (const [vendor, group] of groups) {
        const subQty = group.reduce((sum, line) => sum + line.qty, 0);
        const subValue = group.reduce((sum, line) => sum + line.value, 0);
        grandQty += subQty;
        grandValue += subValue;
        grandItems += group.length;

        const rows = group
            .map((line) => `<tr${line.withinBudget === false ? ' class="dim"' : ''}>${PO_COLUMNS.filter((c) => columns.includes(c.key)).map((c) => cell(line, c.key)).join('')}</tr>`)
            .join('');

        body += `<section class="grp"><div class="gh"><h2>${escapeHtml(vendor)}</h2><span>${group.length} items · ${formatNumber(subQty)} units${showMoney ? ` · <b>${formatCurrency(subValue)}</b>` : ''}</span></div><table><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table></section>`;
    }

    const notes = [
        options.hasEstimatedCost && (showMoney || columns.includes('unitCost'))
            ? `<div class="note">Some unit costs are estimated (${options.settings.cost_mode === 'percent' ? `${options.settings.cost_value}% of price` : `₹${options.settings.cost_value}/unit`}) where cost price is missing in Catalog — those figures are approximate.</div>`
            : '',
        options.budget > 0
            ? `<div class="note">Budget cap ${formatCurrency(options.budget)}: lines beyond it are greyed out, in priority order (stocked out first). ${formatCurrency(options.fittedValue)} fits inside the cap.</div>`
            : '',
    ].join('');

    const date = stamp();
    const doc = `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Purchase Order ${date}</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f3ef;color:#17251d;font:13px/1.4 -apple-system,Segoe UI,Roboto,Arial,sans-serif}
.pg{max-width:1000px;margin:0 auto;padding:24px}
header{display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap;border-bottom:2px solid #17251d;padding-bottom:14px}
h1{font-size:22px;margin:0}.sub{color:#5d665f;font-size:12.5px;margin-top:3px}
.tot{margin-left:auto;text-align:right}.tot .big{font:700 22px ui-monospace,Menlo,monospace}.tot .lab{font-size:11px;color:#5d665f;text-transform:uppercase;letter-spacing:.08em}
.actions{margin:14px 0}.actions button{font:600 13px inherit;border:1px solid #17251d;background:#17251d;color:#fff;border-radius:7px;padding:8px 16px;cursor:pointer}
.note{background:#f4ead3;color:#7a5a12;border-radius:7px;padding:8px 12px;font-size:12px;margin:8px 0}
.grp{background:#fff;border:1px solid #ded9ce;border-radius:10px;overflow:hidden;margin:14px 0}
.gh{display:flex;align-items:baseline;gap:12px;padding:10px 14px;background:#eef1ec;border-bottom:1px solid #ded9ce}.gh h2{font-size:15px;margin:0}.gh span{margin-left:auto;color:#5d665f;font-size:12.5px}
table{width:100%;border-collapse:collapse}th{font:600 10px ui-monospace,monospace;letter-spacing:.05em;text-transform:uppercase;color:#5d665f;text-align:left;padding:7px 8px;border-bottom:1.5px solid #ded9ce;background:#fbfaf6}
th.r{text-align:right}td{padding:6px 8px;border-bottom:1px solid #ece8df;vertical-align:middle}td.r{text-align:right;font-variant-numeric:tabular-nums;font-family:ui-monospace,Menlo,monospace}
tr{page-break-inside:avoid}.thumb{width:56px}.thumb img{width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #ded9ce;display:block;background:#f4f3ef}
.thumb.ni::after{content:"no photo";display:flex;align-items:center;justify-content:center;width:48px;height:48px;border:1px dashed #ded9ce;border-radius:6px;color:#a8a29a;font-size:8.5px}
.sku{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;color:#46679b}.nm{font-weight:600}.vt{color:#5d665f;font-size:11px}
.q{font-weight:700}.ov{font-weight:600}.soon{color:#a96f14}.out{color:#b3321f}.nb{color:#b3321f;font-size:11px}.yb{color:#1e7a46;font-size:11px}.dim{opacity:.5}
.grand{background:#17251d;color:#fff;border-radius:10px;padding:14px 18px;display:flex;align-items:center;margin:14px 0;font-size:14px}.grand .big{margin-left:auto;font:700 22px ui-monospace,monospace}
footer{color:#8a857c;font-size:11px;text-align:center;margin-top:16px}
@media print{body{background:#fff}.actions{display:none}.pg{max-width:none;padding:0}}
</style></head><body><div class="pg">
<header><div><h1>Purchase Order</h1><div class="sub">Generated ${date} · ${groups.size} supplier(s)</div></div>
<div class="tot"><div class="lab">${showMoney ? 'Total order value' : 'Total units'}</div><div class="big">${showMoney ? formatCurrency(grandValue) : `${formatNumber(grandQty)} units`}</div><div class="sub">${formatNumber(grandItems)} items · ${formatNumber(grandQty)} units</div></div></header>
<div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
${notes}${body}
<div class="grand"><span>${showMoney ? `Grand total — ${formatNumber(grandItems)} items, ${formatNumber(grandQty)} units` : `${formatNumber(grandItems)} items · ${formatNumber(grandQty)} units`}</span>${showMoney ? `<span class="big">${formatCurrency(grandValue)}</span>` : ''}</div>
<footer>Photos load from your store, so displaying them needs internet. Tap a photo to open the full-size image — the links stay clickable in a saved PDF.</footer>
</div></body></html>`;

    return downloadFile(`purchase-order-photos-${date}.html`, doc, 'text/html');
}

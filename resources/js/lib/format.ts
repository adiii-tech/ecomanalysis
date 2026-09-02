/**
 * All money crosses the wire as integer paise. Formatting — including Indian
 * lakh/crore grouping — happens here at the edge and nowhere else.
 */

const PAISE = 100;

function num(value: unknown): number {
    const parsed = typeof value === 'number' ? value : Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
}

export function toRupees(paise: number): number {
    return (paise ?? 0) / PAISE;
}

function groupIndian(whole: string): string {
    if (whole.length <= 3) return whole;
    const last3 = whole.slice(-3);
    const rest = whole.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',');
    return `${rest},${last3}`;
}

export function formatCurrency(paise: number, options: { decimals?: number; symbol?: boolean } = {}): string {
    const { decimals = 0, symbol = true } = options;
    const value = num(paise);
    const negative = value < 0;
    const rupees = Math.abs(value) / PAISE;

    const whole = groupIndian(Math.floor(rupees).toString());
    const fraction = decimals > 0 ? `.${Math.round((rupees % 1) * 10 ** decimals).toString().padStart(decimals, '0')}` : '';

    return `${negative ? '-' : ''}${symbol ? '₹' : ''}${whole}${fraction}`;
}

function trim(value: number): string {
    const rounded = Math.round(value * 10) / 10;
    return Number.isInteger(rounded) ? rounded.toString() : rounded.toFixed(1);
}

/** ₹9.6L, ₹1.2Cr — the compact form used in tight card headers. */
export function formatCompactCurrency(paise: number, symbol = true): string {
    const value = num(paise);
    const negative = value < 0;
    const rupees = Math.abs(value) / PAISE;
    const prefix = `${negative ? '-' : ''}${symbol ? '₹' : ''}`;

    if (rupees >= 1e7) return `${prefix}${trim(rupees / 1e7)}Cr`;
    if (rupees >= 1e5) return `${prefix}${trim(rupees / 1e5)}L`;
    if (rupees >= 1e3) return `${prefix}${trim(rupees / 1e3)}K`;
    return `${prefix}${trim(rupees)}`;
}

export function formatNumber(value: number, decimals = 0): string {
    const parsed = num(value);
    const negative = parsed < 0;
    const abs = Math.abs(parsed);
    const whole = groupIndian(Math.floor(abs).toString());
    const fraction = decimals > 0 ? `.${(abs % 1).toFixed(decimals).slice(2)}` : '';
    return `${negative ? '-' : ''}${whole}${fraction}`;
}

export function formatCompactNumber(value: number): string {
    const parsed = num(value);
    const abs = Math.abs(parsed);
    const sign = parsed < 0 ? '-' : '';
    if (abs >= 1e7) return `${sign}${trim(abs / 1e7)}Cr`;
    if (abs >= 1e5) return `${sign}${trim(abs / 1e5)}L`;
    if (abs >= 1e3) return `${sign}${trim(abs / 1e3)}K`;
    return `${sign}${Math.round(abs)}`;
}

export function formatPercent(value: number, decimals = 1): string {
    return `${num(value).toFixed(decimals)}%`;
}

export function formatRatio(value: number, decimals = 2): string {
    return `${num(value).toFixed(decimals)}×`;
}

export type MetricFormat = 'currency' | 'number' | 'percent' | 'ratio' | 'days' | 'seconds';

export function formatMetric(value: number, format: MetricFormat, compact = false): string {
    switch (format) {
        case 'currency':
            return compact ? formatCompactCurrency(value) : formatCurrency(value);
        case 'percent':
            return formatPercent(value);
        case 'ratio':
            return formatRatio(value);
        case 'days':
            return `${formatNumber(value, 1)}d`;
        case 'seconds':
            return `${num(value).toFixed(1)}s`;
        default:
            return compact ? formatCompactNumber(value) : formatNumber(value);
    }
}

export function formatDelta(deltaPct: number | null): string {
    if (deltaPct === null || deltaPct === undefined) return '—';
    const parsed = num(deltaPct);
    return `${parsed > 0 ? '+' : ''}${parsed.toFixed(1)}%`;
}

const DATE_FMT = new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short' });
const DATE_TIME_FMT = new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
const LONG_DATE_FMT = new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });

export function formatDate(value: string | Date): string {
    return DATE_FMT.format(new Date(value));
}

export function formatLongDate(value: string | Date): string {
    return LONG_DATE_FMT.format(new Date(value));
}

export function formatDateTime(value: string | Date): string {
    return DATE_TIME_FMT.format(new Date(value));
}

export function toDateInput(date: Date): string {
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60_000).toISOString().slice(0, 10);
}

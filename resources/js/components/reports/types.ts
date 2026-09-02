import type { Caveat, Metric, Verdict } from '@/types';

export type ColumnFormat =
    | 'text'
    | 'currency'
    | 'number'
    | 'percent'
    | 'ratio'
    | 'delta_percent'
    | 'date'
    | 'datetime'
    | 'badge'
    /** Uses the `format` field on the row itself, for tables that mix units. */
    | 'row_format';

export interface ReportColumn {
    key: string;
    label: string;
    format?: ColumnFormat;
    align?: 'left' | 'right' | 'center';
    tooltip?: string | null;
    tone?: 'inverse';
}

export interface ReportSeries {
    key: string;
    label: string;
    format?: 'currency' | 'number' | 'percent' | 'ratio';
    color?: string;
}

export interface ReportSection {
    type: 'table' | 'line' | 'bar' | 'stacked' | 'area' | 'waterfall' | 'callouts' | 'narrative';
    title: string;
    subtitle: string | null;
    rows: Record<string, unknown>[];
    columns: ReportColumn[];
    config: {
        /** Makes each row open the orders behind it. */
        drilldown?: { dimension: string; value_key: string; label_key?: string };
        x?: string;
        series?: ReportSeries[];
        heatmap?: boolean;
        heatmap_from?: string;
        statement?: boolean;
        format?: string;
    };
    verdict: Verdict | null;
    caveat: Caveat | null;
    export_key: string | null;
}

export interface ReportMeta {
    key: string;
    slug: string;
    label: string;
    category: string;
    description: string;
    permission: string;
    exports: string[];
    uses_period: boolean;
    is_favourite?: boolean;
    last_used_at?: string | null;
}

export interface ReportPayload {
    report: ReportMeta;
    kpis: Metric[];
    sections: ReportSection[];
    verdict: Verdict | null;
    caveats: Caveat[];
}

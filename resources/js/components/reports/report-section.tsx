import { useMemo } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip as RechartsTooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ChartCard } from '@/components/app/chart-card';
import { DataTable, type Column } from '@/components/app/data-table';
import { VerdictNote } from '@/components/app/verdict-note';
import { CaveatNote } from '@/components/app/caveat-note';
import { WaterfallChart, type WaterfallStep } from '@/components/charts/waterfall-chart';
import {
    AXIS_PROPS,
    CHART_COLORS,
    ChartLegend,
    ChartTooltip,
    GRID_PROPS,
    axisCurrency,
    axisDate,
    axisNumber,
    axisPercent,
} from '@/components/charts/chart-primitives';
import {
    formatCurrency,
    formatDate,
    formatDateTime,
    formatNumber,
    formatPercent,
    formatRatio,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MetricFormat } from '@/types';
import type { DrilldownTarget } from '@/components/app/orders-drilldown';
import type { ColumnFormat, ReportColumn, ReportSection as Section } from './types';

type Row = Record<string, unknown>;

function num(value: unknown): number {
    const parsed = typeof value === 'number' ? value : Number(value ?? 0);
    return Number.isFinite(parsed) ? parsed : 0;
}

/** Cell rendering is shared by every report, so a rupee always looks like a rupee. */
export function formatCell(value: unknown, format: ColumnFormat | undefined, row?: Row): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const effective = format === 'row_format' ? ((row?.format as ColumnFormat) ?? 'text') : format;

    switch (effective) {
        case 'currency':
            return formatCurrency(num(value));
        case 'number':
            return formatNumber(num(value), Number.isInteger(num(value)) ? 0 : 1);
        case 'percent':
            return formatPercent(num(value));
        case 'ratio':
            return formatRatio(num(value));
        case 'delta_percent':
            return `${num(value) > 0 ? '+' : ''}${formatPercent(num(value))}`;
        case 'date':
            return formatDate(String(value));
        case 'datetime':
            return formatDateTime(String(value));
        default:
            return String(value);
    }
}

const NUMERIC: ColumnFormat[] = ['currency', 'number', 'percent', 'ratio', 'delta_percent'];

function toDataTableColumns(columns: ReportColumn[], config: Section['config']): Column<Row>[] {
    const heatmapFrom = config.heatmap ? (config.heatmap_from ?? null) : null;
    let heatmapReached = heatmapFrom === null;

    return columns.map((column) => {
        const numeric = NUMERIC.includes(column.format ?? 'text');
        const isHeatmapCell = config.heatmap === true && (heatmapReached ||= column.key === heatmapFrom);

        return {
            key: column.key,
            header: column.label,
            tooltip: column.tooltip ?? undefined,
            align: column.align ?? (numeric ? 'right' : 'left'),
            sortable: true,
            value: (row) => {
                const raw = row[column.key];
                if (raw === null || raw === undefined) {
                    return null;
                }
                return numeric || column.format === 'row_format' ? num(raw) : String(raw);
            },
            render: (row) => {
                const raw = row[column.key];

                if (column.format === 'badge') {
                    return raw ? <Badge variant="muted">{String(raw)}</Badge> : <span className="text-muted-foreground">—</span>;
                }

                const text = formatCell(raw, column.format, row);

                if (isHeatmapCell && raw !== null && raw !== undefined) {
                    // Retention reads as a shape before it reads as numbers.
                    const intensity = Math.min(1, num(raw) / 60);
                    return (
                        <span
                            className="inline-block w-full rounded px-1.5 py-0.5 tnum"
                            style={{ backgroundColor: `color-mix(in oklab, var(--chart-1) ${Math.round(intensity * 70)}%, transparent)` }}
                        >
                            {text}
                        </span>
                    );
                }

                const negative = numeric && num(raw) < 0;
                const flagged = column.tone === 'inverse' && num(raw) > 0;

                return (
                    <span
                        className={cn(
                            numeric && 'tnum',
                            negative && 'text-bad',
                            flagged && column.format === 'percent' && num(raw) > 20 && 'text-bad',
                            (row.kind === 'total' || row.kind === 'subtotal') && 'font-semibold',
                        )}
                    >
                        {text}
                    </span>
                );
            },
            className: row_indent_class(column),
        };
    });
}

/** Statement-style tables indent their deduction lines. */
function row_indent_class(column: ReportColumn): string | undefined {
    return column.key === 'label' ? 'max-w-[22rem]' : undefined;
}

function axisFormatter(format: string | undefined) {
    switch (format) {
        case 'currency':
            return axisCurrency;
        case 'percent':
            return axisPercent;
        case 'ratio':
            return (value: number) => formatRatio(value);
        default:
            return axisNumber;
    }
}

function ChartBody({ section }: { section: Section }) {
    const series = section.config.series ?? [];
    const x = section.config.x ?? 'label';
    const rows = section.rows;
    const isDateAxis = x === 'date';
    const leftFormat = series[0]?.format ?? 'number';

    const common = (
        <>
            <CartesianGrid {...GRID_PROPS} />
            <XAxis
                dataKey={x}
                {...AXIS_PROPS}
                tickFormatter={isDateAxis ? axisDate : undefined}
                interval="preserveStartEnd"
                minTickGap={24}
                angle={isDateAxis ? 0 : -20}
                textAnchor={isDateAxis ? 'middle' : 'end'}
                height={isDateAxis ? 28 : 60}
            />
            <YAxis {...AXIS_PROPS} tickFormatter={axisFormatter(leftFormat)} width={62} />
        </>
    );

    // Each series carries its own unit, so the tooltip is told per data key
    // rather than formatting an entire chart as one type.
    const formats = Object.fromEntries(series.map((item) => [item.key, (item.format ?? 'number') as MetricFormat]));

    const tooltip = (
        <RechartsTooltip
            content={
                <ChartTooltip
                    format={(series[0]?.format ?? 'number') as MetricFormat}
                    formats={formats}
                    labelFormatter={isDateAxis ? (value: string) => formatDate(value) : (value: string) => value}
                />
            }
            cursor={{ fill: 'var(--accent)', opacity: 0.4 }}
        />
    );

    if (section.type === 'line') {
        return (
            <ResponsiveContainer width="100%" height={300}>
                <LineChart data={rows} margin={{ top: 8, right: 8, bottom: 4, left: 4 }}>
                    {common}
                    {tooltip}
                    {series.map((item, index) => (
                        <Line
                            key={item.key}
                            type="monotone"
                            dataKey={item.key}
                            name={item.label}
                            stroke={item.color ?? CHART_COLORS[index % CHART_COLORS.length]}
                            strokeWidth={2}
                            dot={false}
                            connectNulls={false}
                            isAnimationActive={false}
                        />
                    ))}
                </LineChart>
            </ResponsiveContainer>
        );
    }

    if (section.type === 'area') {
        return (
            <ResponsiveContainer width="100%" height={300}>
                <AreaChart data={rows} margin={{ top: 8, right: 8, bottom: 4, left: 4 }}>
                    {common}
                    {tooltip}
                    {series.map((item, index) => (
                        <Area
                            key={item.key}
                            type="monotone"
                            dataKey={item.key}
                            name={item.label}
                            stackId="1"
                            stroke={item.color ?? CHART_COLORS[index % CHART_COLORS.length]}
                            fill={item.color ?? CHART_COLORS[index % CHART_COLORS.length]}
                            fillOpacity={0.18}
                            isAnimationActive={false}
                        />
                    ))}
                </AreaChart>
            </ResponsiveContainer>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={320}>
            <BarChart data={rows} margin={{ top: 8, right: 8, bottom: 4, left: 4 }}>
                {common}
                {tooltip}
                {series.map((item, index) => (
                    <Bar
                        key={item.key}
                        dataKey={item.key}
                        name={item.label}
                        stackId={section.type === 'stacked' ? 'a' : undefined}
                        fill={item.color ?? CHART_COLORS[index % CHART_COLORS.length]}
                        radius={3}
                        isAnimationActive={false}
                    />
                ))}
            </BarChart>
        </ResponsiveContainer>
    );
}

function Callouts({ section }: { section: Section }) {
    return (
        <div className="space-y-3">
            {section.subtitle && <p className="text-xs text-muted-foreground">{section.subtitle}</p>}
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {section.rows.map((row, index) => (
                    <Card key={index} className="p-4">
                        <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{String(row.label)}</p>
                        <p
                            className={cn(
                                'mt-1.5 text-xl font-semibold tnum',
                                row.tone === 'good' && 'text-good',
                                row.tone === 'bad' && 'text-bad',
                                row.tone === 'watch' && 'text-warn',
                            )}
                        >
                            {formatCell(row.value, (row.format as ColumnFormat) ?? 'number')}
                        </p>
                        {row.note != null && <p className="mt-1 text-xs leading-snug text-muted-foreground">{String(row.note)}</p>}
                    </Card>
                ))}
            </div>
        </div>
    );
}

export function ReportSectionView({
    section,
    readOnly = false,
    onDrilldown,
}: {
    section: Section;
    readOnly?: boolean;
    onDrilldown?: (target: DrilldownTarget) => void;
}) {
    const waterfallSteps = useMemo<WaterfallStep[]>(
        () =>
            section.type === 'waterfall'
                ? section.rows.map((row, index) => ({
                      key: `${String(row.label)}-${index}`,
                      label: String(row.label),
                      delta: num(row.value),
                      start: num(row.start),
                      end: num(row.end),
                      is_total: row.type === 'total',
                  }))
                : [],
        [section],
    );

    if (section.type === 'narrative') {
        return (
            <Card className="p-4">
                <h3 className="text-sm font-semibold">{section.title}</h3>
                {section.rows.map((row, index) => (
                    <p key={index} className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        {String(row.text)}
                    </p>
                ))}
            </Card>
        );
    }

    if (section.type === 'callouts') {
        return (
            <section className="space-y-3">
                <h3 className="text-sm font-semibold">{section.title}</h3>
                <Callouts section={section} />
                <VerdictNote verdict={section.verdict} />
            </section>
        );
    }

    if (section.type === 'table') {
        const columns = toDataTableColumns(section.columns, section.config);
        const drilldown = readOnly ? undefined : section.config.drilldown;

        return (
            <ChartCard
                title={section.title}
                subtitle={section.subtitle ?? undefined}
                verdict={section.verdict}
                caveat={section.caveat}
                exportDataset={readOnly ? undefined : (section.export_key ?? undefined)}
                empty={section.rows.length === 0}
                bodyClassName="p-0"
            >
                <DataTable
                    columns={columns}
                    rows={section.rows}
                    searchable={section.rows.length > 12 && section.config.statement !== true}
                    rowKey={(_row, index) => index}
                    maxHeight={section.rows.length > 25 ? '32rem' : undefined}
                    onRowClick={
                        drilldown && onDrilldown
                            ? (row) =>
                                  onDrilldown({
                                      dimension: drilldown.dimension,
                                      value: row[drilldown.value_key] as string | number | null,
                                      title: String(row[drilldown.label_key ?? drilldown.value_key] ?? 'Orders'),
                                      description: `Every order behind this row of "${section.title}".`,
                                  })
                            : undefined
                    }
                    dense
                />
            </ChartCard>
        );
    }

    if (section.type === 'waterfall') {
        return (
            <ChartCard
                title={section.title}
                subtitle={section.subtitle ?? undefined}
                verdict={section.verdict}
                caveat={section.caveat}
                empty={section.rows.length === 0}
            >
                <WaterfallChart steps={waterfallSteps} />
            </ChartCard>
        );
    }

    return (
        <ChartCard
            title={section.title}
            subtitle={section.subtitle ?? undefined}
            verdict={section.verdict}
            caveat={section.caveat}
            empty={section.rows.length === 0}
        >
            <ChartBody section={section} />
            <ChartLegend
                items={(section.config.series ?? []).map((item, index) => ({
                    label: item.label,
                    color: item.color ?? CHART_COLORS[index % CHART_COLORS.length],
                }))}
            />
        </ChartCard>
    );
}

export { CaveatNote };

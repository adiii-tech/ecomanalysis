import { t as cn } from "./utils-BVTyW6jK.js";
import { c as formatDateTime, f as formatNumber, m as formatRatio, o as formatCurrency, p as formatPercent, s as formatDate, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { i as VerdictNote, t as ChartCard } from "./chart-card-CZPTjzl-.js";
import "./caveat-note-Dr_rbZyQ.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as GRID_PROPS, c as axisNumber, i as ChartTooltip, l as axisPercent, n as CHART_COLORS, o as axisCurrency, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as WaterfallChart } from "./waterfall-chart-CcaRf_l7.js";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useMemo } from "react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/components/reports/report-section.tsx
function num(value) {
	const parsed = typeof value === "number" ? value : Number(value ?? 0);
	return Number.isFinite(parsed) ? parsed : 0;
}
/** Cell rendering is shared by every report, so a rupee always looks like a rupee. */
function formatCell(value, format, row) {
	if (value === null || value === void 0 || value === "") return "—";
	switch (format === "row_format" ? row?.format ?? "text" : format) {
		case "currency": return formatCurrency(num(value));
		case "number": return formatNumber(num(value), Number.isInteger(num(value)) ? 0 : 1);
		case "percent": return formatPercent(num(value));
		case "ratio": return formatRatio(num(value));
		case "delta_percent": return `${num(value) > 0 ? "+" : ""}${formatPercent(num(value))}`;
		case "date": return formatDate(String(value));
		case "datetime": return formatDateTime(String(value));
		default: return String(value);
	}
}
var NUMERIC = [
	"currency",
	"number",
	"percent",
	"ratio",
	"delta_percent"
];
function toDataTableColumns(columns, config) {
	const heatmapFrom = config.heatmap ? config.heatmap_from ?? null : null;
	let heatmapReached = heatmapFrom === null;
	return columns.map((column) => {
		const numeric = NUMERIC.includes(column.format ?? "text");
		const isHeatmapCell = config.heatmap === true && (heatmapReached ||= column.key === heatmapFrom);
		return {
			key: column.key,
			header: column.label,
			tooltip: column.tooltip ?? void 0,
			align: column.align ?? (numeric ? "right" : "left"),
			sortable: true,
			value: (row) => {
				const raw = row[column.key];
				if (raw === null || raw === void 0) return null;
				return numeric || column.format === "row_format" ? num(raw) : String(raw);
			},
			render: (row) => {
				const raw = row[column.key];
				if (column.format === "badge") return raw ? /* @__PURE__ */ jsx(Badge, {
					variant: "muted",
					children: String(raw)
				}) : /* @__PURE__ */ jsx("span", {
					className: "text-muted-foreground",
					children: "—"
				});
				const text = formatCell(raw, column.format, row);
				if (isHeatmapCell && raw !== null && raw !== void 0) {
					const intensity = Math.min(1, num(raw) / 60);
					return /* @__PURE__ */ jsx("span", {
						className: "inline-block w-full rounded px-1.5 py-0.5 tnum",
						style: { backgroundColor: `color-mix(in oklab, var(--chart-1) ${Math.round(intensity * 70)}%, transparent)` },
						children: text
					});
				}
				const negative = numeric && num(raw) < 0;
				const flagged = column.tone === "inverse" && num(raw) > 0;
				return /* @__PURE__ */ jsx("span", {
					className: cn(numeric && "tnum", negative && "text-bad", flagged && column.format === "percent" && num(raw) > 20 && "text-bad", (row.kind === "total" || row.kind === "subtotal") && "font-semibold"),
					children: text
				});
			},
			className: row_indent_class(column)
		};
	});
}
/** Statement-style tables indent their deduction lines. */
function row_indent_class(column) {
	return column.key === "label" ? "max-w-[22rem]" : void 0;
}
function axisFormatter(format) {
	switch (format) {
		case "currency": return axisCurrency;
		case "percent": return axisPercent;
		case "ratio": return (value) => formatRatio(value);
		default: return axisNumber;
	}
}
function ChartBody({ section }) {
	const series = section.config.series ?? [];
	const x = section.config.x ?? "label";
	const rows = section.rows;
	const isDateAxis = x === "date";
	const leftFormat = series[0]?.format ?? "number";
	const common = /* @__PURE__ */ jsxs(Fragment, { children: [
		/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
		/* @__PURE__ */ jsx(XAxis, {
			dataKey: x,
			...AXIS_PROPS,
			tickFormatter: isDateAxis ? axisDate : void 0,
			interval: "preserveStartEnd",
			minTickGap: 24,
			angle: isDateAxis ? 0 : -20,
			textAnchor: isDateAxis ? "middle" : "end",
			height: isDateAxis ? 28 : 60
		}),
		/* @__PURE__ */ jsx(YAxis, {
			...AXIS_PROPS,
			tickFormatter: axisFormatter(leftFormat),
			width: 62
		})
	] });
	const formats = Object.fromEntries(series.map((item) => [item.key, item.format ?? "number"]));
	const tooltip = /* @__PURE__ */ jsx(Tooltip, {
		content: /* @__PURE__ */ jsx(ChartTooltip, {
			format: series[0]?.format ?? "number",
			formats,
			labelFormatter: isDateAxis ? (value) => formatDate(value) : (value) => value
		}),
		cursor: {
			fill: "var(--accent)",
			opacity: .4
		}
	});
	if (section.type === "line") return /* @__PURE__ */ jsx(ResponsiveContainer, {
		width: "100%",
		height: 300,
		children: /* @__PURE__ */ jsxs(LineChart, {
			data: rows,
			margin: {
				top: 8,
				right: 8,
				bottom: 4,
				left: 4
			},
			children: [
				common,
				tooltip,
				series.map((item, index) => /* @__PURE__ */ jsx(Line, {
					type: "monotone",
					dataKey: item.key,
					name: item.label,
					stroke: item.color ?? CHART_COLORS[index % CHART_COLORS.length],
					strokeWidth: 2,
					dot: false,
					connectNulls: false,
					isAnimationActive: false
				}, item.key))
			]
		})
	});
	if (section.type === "area") return /* @__PURE__ */ jsx(ResponsiveContainer, {
		width: "100%",
		height: 300,
		children: /* @__PURE__ */ jsxs(AreaChart, {
			data: rows,
			margin: {
				top: 8,
				right: 8,
				bottom: 4,
				left: 4
			},
			children: [
				common,
				tooltip,
				series.map((item, index) => /* @__PURE__ */ jsx(Area, {
					type: "monotone",
					dataKey: item.key,
					name: item.label,
					stackId: "1",
					stroke: item.color ?? CHART_COLORS[index % CHART_COLORS.length],
					fill: item.color ?? CHART_COLORS[index % CHART_COLORS.length],
					fillOpacity: .18,
					isAnimationActive: false
				}, item.key))
			]
		})
	});
	return /* @__PURE__ */ jsx(ResponsiveContainer, {
		width: "100%",
		height: 320,
		children: /* @__PURE__ */ jsxs(BarChart, {
			data: rows,
			margin: {
				top: 8,
				right: 8,
				bottom: 4,
				left: 4
			},
			children: [
				common,
				tooltip,
				series.map((item, index) => /* @__PURE__ */ jsx(Bar, {
					dataKey: item.key,
					name: item.label,
					stackId: section.type === "stacked" ? "a" : void 0,
					fill: item.color ?? CHART_COLORS[index % CHART_COLORS.length],
					radius: 3,
					isAnimationActive: false
				}, item.key))
			]
		})
	});
}
function Callouts({ section }) {
	return /* @__PURE__ */ jsxs("div", {
		className: "space-y-3",
		children: [section.subtitle && /* @__PURE__ */ jsx("p", {
			className: "text-xs text-muted-foreground",
			children: section.subtitle
		}), /* @__PURE__ */ jsx("div", {
			className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-4",
			children: section.rows.map((row, index) => /* @__PURE__ */ jsxs(Card, {
				className: "p-4",
				children: [
					/* @__PURE__ */ jsx("p", {
						className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
						children: String(row.label)
					}),
					/* @__PURE__ */ jsx("p", {
						className: cn("mt-1.5 text-xl font-semibold tnum", row.tone === "good" && "text-good", row.tone === "bad" && "text-bad", row.tone === "watch" && "text-warn"),
						children: formatCell(row.value, row.format ?? "number")
					}),
					row.note != null && /* @__PURE__ */ jsx("p", {
						className: "mt-1 text-xs leading-snug text-muted-foreground",
						children: String(row.note)
					})
				]
			}, index))
		})]
	});
}
function ReportSectionView({ section, readOnly = false, onDrilldown }) {
	const waterfallSteps = useMemo(() => section.type === "waterfall" ? section.rows.map((row, index) => ({
		key: `${String(row.label)}-${index}`,
		label: String(row.label),
		delta: num(row.value),
		start: num(row.start),
		end: num(row.end),
		is_total: row.type === "total"
	})) : [], [section]);
	if (section.type === "narrative") return /* @__PURE__ */ jsxs(Card, {
		className: "p-4",
		children: [/* @__PURE__ */ jsx("h3", {
			className: "text-sm font-semibold",
			children: section.title
		}), section.rows.map((row, index) => /* @__PURE__ */ jsx("p", {
			className: "mt-2 text-sm leading-relaxed text-muted-foreground",
			children: String(row.text)
		}, index))]
	});
	if (section.type === "callouts") return /* @__PURE__ */ jsxs("section", {
		className: "space-y-3",
		children: [
			/* @__PURE__ */ jsx("h3", {
				className: "text-sm font-semibold",
				children: section.title
			}),
			/* @__PURE__ */ jsx(Callouts, { section }),
			/* @__PURE__ */ jsx(VerdictNote, { verdict: section.verdict })
		]
	});
	if (section.type === "table") {
		const columns = toDataTableColumns(section.columns, section.config);
		const drilldown = readOnly ? void 0 : section.config.drilldown;
		return /* @__PURE__ */ jsx(ChartCard, {
			title: section.title,
			subtitle: section.subtitle ?? void 0,
			verdict: section.verdict,
			caveat: section.caveat,
			exportDataset: readOnly ? void 0 : section.export_key ?? void 0,
			empty: section.rows.length === 0,
			bodyClassName: "p-0",
			children: /* @__PURE__ */ jsx(DataTable, {
				columns,
				rows: section.rows,
				searchable: section.rows.length > 12 && section.config.statement !== true,
				rowKey: (_row, index) => index,
				maxHeight: section.rows.length > 25 ? "32rem" : void 0,
				onRowClick: drilldown && onDrilldown ? (row) => onDrilldown({
					dimension: drilldown.dimension,
					value: row[drilldown.value_key],
					title: String(row[drilldown.label_key ?? drilldown.value_key] ?? "Orders"),
					description: `Every order behind this row of "${section.title}".`
				}) : void 0,
				dense: true
			})
		});
	}
	if (section.type === "waterfall") return /* @__PURE__ */ jsx(ChartCard, {
		title: section.title,
		subtitle: section.subtitle ?? void 0,
		verdict: section.verdict,
		caveat: section.caveat,
		empty: section.rows.length === 0,
		children: /* @__PURE__ */ jsx(WaterfallChart, { steps: waterfallSteps })
	});
	return /* @__PURE__ */ jsxs(ChartCard, {
		title: section.title,
		subtitle: section.subtitle ?? void 0,
		verdict: section.verdict,
		caveat: section.caveat,
		empty: section.rows.length === 0,
		children: [/* @__PURE__ */ jsx(ChartBody, { section }), /* @__PURE__ */ jsx(ChartLegend, { items: (section.config.series ?? []).map((item, index) => ({
			label: item.label,
			color: item.color ?? CHART_COLORS[index % CHART_COLORS.length]
		})) })]
	});
}
//#endregion
export { ReportSectionView as t };

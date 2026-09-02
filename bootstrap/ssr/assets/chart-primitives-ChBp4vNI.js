import { t as cn } from "./utils-BVTyW6jK.js";
import { a as formatCompactNumber, d as formatMetric, i as formatCompactCurrency, s as formatDate } from "./card-DJDNvUnK.js";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/components/charts/chart-primitives.tsx
var CHART_COLORS = [
	"var(--chart-1)",
	"var(--chart-2)",
	"var(--chart-3)",
	"var(--chart-4)",
	"var(--chart-5)",
	"var(--chart-6)",
	"var(--chart-7)"
];
var AXIS_PROPS = {
	tick: {
		fontSize: 11,
		fill: "var(--muted-foreground)"
	},
	tickLine: false,
	axisLine: false
};
var GRID_PROPS = {
	stroke: "var(--border)",
	strokeDasharray: "3 3",
	vertical: false
};
function axisCurrency(value) {
	return formatCompactCurrency(value);
}
function axisNumber(value) {
	return formatCompactNumber(value);
}
function axisPercent(value) {
	return `${Math.round(value)}%`;
}
function axisDate(value) {
	return formatDate(value);
}
/**
* One tooltip for every chart in the product, so a number always reads the same
* way regardless of which widget it came from.
*/
function ChartTooltip({ active, payload, label, format = "currency", labelFormatter = formatDate, formats, footer }) {
	if (!active || !payload?.length) return null;
	return /* @__PURE__ */ jsxs("div", {
		className: "rounded-lg border border-border bg-popover px-2.5 py-2 text-xs shadow-lg",
		children: [
			label !== void 0 && /* @__PURE__ */ jsx("p", {
				className: "mb-1 font-medium text-popover-foreground",
				children: labelFormatter(String(label))
			}),
			/* @__PURE__ */ jsx("div", {
				className: "space-y-0.5",
				children: payload.map((entry, index) => /* @__PURE__ */ jsxs("div", {
					className: "flex items-center justify-between gap-4",
					children: [/* @__PURE__ */ jsxs("span", {
						className: "flex items-center gap-1.5 text-muted-foreground",
						children: [/* @__PURE__ */ jsx("span", {
							className: "size-2 rounded-full",
							style: { background: entry.color }
						}), entry.name]
					}), /* @__PURE__ */ jsx("span", {
						className: "font-medium tnum text-popover-foreground",
						children: formatMetric(entry.value ?? 0, formats?.[String(entry.dataKey)] ?? format, true)
					})]
				}, index))
			}),
			footer?.(payload)
		]
	});
}
function ChartLegend({ items, className }) {
	return /* @__PURE__ */ jsx("div", {
		className: cn("flex flex-wrap items-center gap-x-3 gap-y-1", className),
		children: items.map((item) => /* @__PURE__ */ jsxs("span", {
			className: "flex items-center gap-1.5 text-[11px] text-muted-foreground",
			children: [
				/* @__PURE__ */ jsx("span", {
					className: "size-2 rounded-full",
					style: { background: item.color }
				}),
				item.label,
				item.value && /* @__PURE__ */ jsx("span", {
					className: "font-medium tnum text-foreground",
					children: item.value
				})
			]
		}, item.label))
	});
}
//#endregion
export { GRID_PROPS as a, axisNumber as c, ChartTooltip as i, axisPercent as l, CHART_COLORS as n, axisCurrency as o, ChartLegend as r, axisDate as s, AXIS_PROPS as t };

import { o as formatCurrency } from "./card-DJDNvUnK.js";
import { a as GRID_PROPS, o as axisCurrency, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, XAxis, YAxis } from "recharts";
//#region resources/js/components/charts/waterfall-chart.tsx
/**
* The gross → contribution-margin chain. Floating bars show each deduction as
* the distance it takes off, so the leak is visible rather than inferred.
*/
function WaterfallChart({ steps, height = 300 }) {
	const data = steps.map((step) => ({
		...step,
		range: step.is_total ? [0, step.end] : [Math.min(step.start, step.end), Math.max(step.start, step.end)]
	}));
	return /* @__PURE__ */ jsx(ResponsiveContainer, {
		width: "100%",
		height,
		children: /* @__PURE__ */ jsxs(BarChart, {
			data,
			margin: {
				top: 8,
				right: 4,
				bottom: 4,
				left: 4
			},
			children: [
				/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
				/* @__PURE__ */ jsx(XAxis, {
					dataKey: "label",
					...AXIS_PROPS,
					interval: 0,
					angle: -28,
					textAnchor: "end",
					height: 62
				}),
				/* @__PURE__ */ jsx(YAxis, {
					...AXIS_PROPS,
					tickFormatter: axisCurrency,
					width: 58
				}),
				/* @__PURE__ */ jsx(Bar, {
					dataKey: "range",
					radius: 3,
					isAnimationActive: false,
					children: data.map((step) => /* @__PURE__ */ jsx(Cell, {
						fill: step.is_total ? "var(--chart-1)" : step.delta >= 0 ? "var(--good)" : "var(--bad)",
						fillOpacity: step.is_total ? 1 : .85
					}, step.key))
				})
			]
		})
	});
}
function WaterfallLegend({ steps }) {
	const total = steps.find((step) => step.is_total);
	return /* @__PURE__ */ jsxs("div", {
		className: "flex flex-wrap items-center justify-between gap-2 text-xs",
		children: [/* @__PURE__ */ jsxs("div", {
			className: "flex items-center gap-3",
			children: [/* @__PURE__ */ jsxs("span", {
				className: "flex items-center gap-1.5 text-muted-foreground",
				children: [/* @__PURE__ */ jsx("span", { className: "size-2 rounded-full bg-good" }), " adds"]
			}), /* @__PURE__ */ jsxs("span", {
				className: "flex items-center gap-1.5 text-muted-foreground",
				children: [/* @__PURE__ */ jsx("span", { className: "size-2 rounded-full bg-bad" }), " takes away"]
			})]
		}), total && /* @__PURE__ */ jsxs("span", {
			className: "font-medium tnum",
			children: ["Contribution margin ", /* @__PURE__ */ jsx("span", {
				className: total.end >= 0 ? "text-good" : "text-bad",
				children: formatCurrency(total.end)
			})]
		})]
	});
}
//#endregion
export { WaterfallLegend as n, WaterfallChart as t };

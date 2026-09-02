import { t as cn } from "./utils-BVTyW6jK.js";
import { d as formatMetric, t as Card } from "./card-DJDNvUnK.js";
import { a as Tooltip, o as TooltipContent, s as TooltipTrigger } from "./chart-card-CZPTjzl-.js";
import { t as Skeleton } from "./skeleton-DUakt-29.js";
import { t as DeltaChip } from "./delta-chip-CWzaPomV.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { Info } from "lucide-react";
//#region resources/js/components/app/stat-strip.tsx
function deltaOf(stat) {
	const prev = stat.prev_value;
	if (prev === null || prev === void 0) return {
		pct: null,
		direction: "flat",
		isGood: null
	};
	if (Math.abs(prev) < 1e-7) return {
		pct: Math.abs(stat.value) < 1e-7 ? 0 : null,
		direction: "flat",
		isGood: null
	};
	const pct = Math.round((stat.value - prev) / Math.abs(prev) * 1e4) / 100;
	const direction = pct > .05 ? "up" : pct < -.05 ? "down" : "flat";
	const higherIsBetter = stat.higher_is_better ?? true;
	return {
		pct,
		direction,
		isGood: direction === "flat" ? null : higherIsBetter ? direction === "up" : direction === "down"
	};
}
/**
* KPI strip for endpoints that return a keyed record of stats rather than the
* full Metric shape with sparklines.
*/
function StatStrip({ stats, loading, columns = 4 }) {
	const entries = stats ? Object.entries(stats).filter((entry) => typeof entry[1] === "object" && entry[1] !== null && "value" in entry[1]) : [];
	const gridClass = cn("grid gap-3", columns >= 6 ? "grid-cols-2 md:grid-cols-3 xl:grid-cols-6" : columns === 5 ? "grid-cols-2 md:grid-cols-3 xl:grid-cols-5" : "grid-cols-2 lg:grid-cols-4");
	if (loading || entries.length === 0) return /* @__PURE__ */ jsx("div", {
		className: gridClass,
		children: Array.from({ length: columns }).map((_, index) => /* @__PURE__ */ jsxs(Card, {
			className: "p-4",
			children: [
				/* @__PURE__ */ jsx(Skeleton, { className: "h-3 w-24" }),
				/* @__PURE__ */ jsx(Skeleton, { className: "mt-3 h-7 w-28" }),
				/* @__PURE__ */ jsx(Skeleton, { className: "mt-2 h-4 w-16" })
			]
		}, index))
	});
	return /* @__PURE__ */ jsx("div", {
		className: gridClass,
		children: entries.map(([key, stat]) => {
			const delta = deltaOf(stat);
			return /* @__PURE__ */ jsxs(Card, {
				className: "p-4",
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "flex items-start justify-between gap-2",
						children: [/* @__PURE__ */ jsx("span", {
							className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
							children: stat.label
						}), stat.tooltip && /* @__PURE__ */ jsxs(Tooltip, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
							asChild: true,
							children: /* @__PURE__ */ jsx("button", {
								type: "button",
								className: "text-muted-foreground/60 hover:text-muted-foreground",
								"aria-label": `About ${stat.label}`,
								children: /* @__PURE__ */ jsx(Info, { className: "size-3" })
							})
						}), /* @__PURE__ */ jsx(TooltipContent, { children: stat.tooltip })] })]
					}),
					/* @__PURE__ */ jsx("p", {
						className: "mt-2 text-xl font-semibold leading-tight tracking-tight tnum",
						children: formatMetric(stat.value, stat.format)
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "mt-1.5 flex flex-wrap items-center gap-1.5",
						children: [/* @__PURE__ */ jsx(DeltaChip, {
							deltaPct: delta.pct,
							isGood: delta.isGood,
							direction: delta.direction
						}), stat.badge && /* @__PURE__ */ jsx("span", {
							className: "text-[10px] text-muted-foreground",
							children: stat.badge
						})]
					})
				]
			}, key);
		})
	});
}
//#endregion
export { StatStrip as t };

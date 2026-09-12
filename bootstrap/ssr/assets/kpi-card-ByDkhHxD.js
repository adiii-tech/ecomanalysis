import { t as cn } from "./utils-BVTyW6jK.js";
import { d as formatMetric, t as Card } from "./card-DJDNvUnK.js";
import { a as Tooltip$1, o as TooltipContent, s as TooltipTrigger } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { t as Skeleton } from "./skeleton-DUakt-29.js";
import { t as DeltaChip } from "./delta-chip-CWzaPomV.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { Info } from "lucide-react";
import { Area, AreaChart, ResponsiveContainer } from "recharts";
//#region resources/js/components/app/kpi-card.tsx
function KpiCard({ metric, onDrilldown, className }) {
	const strokeColor = metric.is_good === null ? "var(--chart-7)" : metric.is_good ? "var(--good)" : "var(--bad)";
	const clickable = Boolean(onDrilldown && metric.drilldown);
	return /* @__PURE__ */ jsxs(Card, {
		className: cn("group relative flex flex-col justify-between overflow-hidden p-4 transition-shadow", clickable && "cursor-pointer hover:shadow-md", className),
		onClick: clickable ? () => onDrilldown?.(metric) : void 0,
		role: clickable ? "button" : void 0,
		tabIndex: clickable ? 0 : void 0,
		onKeyDown: clickable ? (event) => {
			if (event.key === "Enter" || event.key === " ") {
				event.preventDefault();
				onDrilldown?.(metric);
			}
		} : void 0,
		children: [
			/* @__PURE__ */ jsxs("div", {
				className: "flex items-start justify-between gap-2",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "flex items-center gap-1",
					children: [/* @__PURE__ */ jsx("span", {
						className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
						children: metric.label
					}), metric.tooltip && /* @__PURE__ */ jsxs(Tooltip$1, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
						asChild: true,
						children: /* @__PURE__ */ jsx("button", {
							type: "button",
							className: "text-muted-foreground/60 transition hover:text-muted-foreground",
							"aria-label": `About ${metric.label}`,
							children: /* @__PURE__ */ jsx(Info, { className: "size-3" })
						})
					}), /* @__PURE__ */ jsx(TooltipContent, { children: metric.tooltip })] })]
				}), metric.badge && /* @__PURE__ */ jsx("span", {
					className: "rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground tnum",
					children: metric.badge
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-2",
				children: [/* @__PURE__ */ jsx("p", {
					className: "text-xl font-semibold leading-tight tracking-tight tnum",
					children: formatMetric(metric.value, metric.format)
				}), /* @__PURE__ */ jsxs("div", {
					className: "mt-1.5 flex items-center gap-1.5",
					children: [/* @__PURE__ */ jsx(DeltaChip, {
						deltaPct: metric.delta_pct,
						isGood: metric.is_good,
						direction: metric.direction
					}), /* @__PURE__ */ jsx("span", {
						className: "text-[10px] text-muted-foreground",
						children: "vs prev"
					})]
				})]
			}),
			metric.sparkline.length > 1 && /* @__PURE__ */ jsx("div", {
				className: "pointer-events-none mt-2 h-8",
				children: /* @__PURE__ */ jsx(ResponsiveContainer, {
					width: "100%",
					height: "100%",
					children: /* @__PURE__ */ jsxs(AreaChart, {
						data: metric.sparkline,
						margin: {
							top: 2,
							right: 0,
							bottom: 0,
							left: 0
						},
						children: [/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
							id: `spark-${metric.key}`,
							x1: "0",
							y1: "0",
							x2: "0",
							y2: "1",
							children: [/* @__PURE__ */ jsx("stop", {
								offset: "0%",
								stopColor: strokeColor,
								stopOpacity: .35
							}), /* @__PURE__ */ jsx("stop", {
								offset: "100%",
								stopColor: strokeColor,
								stopOpacity: 0
							})]
						}) }), /* @__PURE__ */ jsx(Area, {
							type: "monotone",
							dataKey: "value",
							stroke: strokeColor,
							strokeWidth: 1.5,
							fill: `url(#spark-${metric.key})`,
							isAnimationActive: false,
							dot: false
						})]
					})
				})
			}),
			metric.caveat && /* @__PURE__ */ jsx(CaveatNote, {
				caveat: metric.caveat,
				className: "mt-2"
			})
		]
	});
}
function KpiCardSkeleton() {
	return /* @__PURE__ */ jsxs(Card, {
		className: "p-4",
		children: [/* @__PURE__ */ jsx(Skeleton, { className: "h-3 w-24" }), /* @__PURE__ */ jsxs("div", {
			className: "mt-3 space-y-2",
			children: [/* @__PURE__ */ jsx(Skeleton, { className: "h-7 w-32" }), /* @__PURE__ */ jsx(Skeleton, { className: "h-4 w-20" })]
		})]
	});
}
function KpiStrip({ metrics, loading, columns = 6, onDrilldown }) {
	const gridClass = cn("grid gap-3", columns >= 6 ? "grid-cols-2 md:grid-cols-3 xl:grid-cols-6" : columns === 5 ? "grid-cols-2 md:grid-cols-3 xl:grid-cols-5" : "grid-cols-2 lg:grid-cols-4");
	if (loading || !metrics) return /* @__PURE__ */ jsx("div", {
		className: gridClass,
		children: Array.from({ length: columns }).map((_, index) => /* @__PURE__ */ jsx(KpiCardSkeleton, {}, index))
	});
	return /* @__PURE__ */ jsx("div", {
		className: gridClass,
		children: metrics.map((metric) => /* @__PURE__ */ jsx(KpiCard, {
			metric,
			onDrilldown
		}, metric.key))
	});
}
//#endregion
export { KpiStrip as n, KpiCard as t };

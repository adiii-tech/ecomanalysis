import { t as cn } from "./utils-BVTyW6jK.js";
import { f as formatNumber, p as formatPercent } from "./card-DJDNvUnK.js";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/components/app/funnel.tsx
function Funnel({ steps, overall }) {
	if (steps.length === 0) return /* @__PURE__ */ jsx("p", {
		className: "py-6 text-center text-xs text-muted-foreground",
		children: "No funnel data in this period."
	});
	const top = Math.max(steps[0]?.value ?? 1, 1);
	return /* @__PURE__ */ jsxs("div", {
		className: "space-y-2",
		children: [steps.map((step, index) => {
			const width = step.value / top * 100;
			const rate = step.step_rate ?? step.pct;
			const isWeak = index > 0 && rate !== void 0 && rate < 40;
			return /* @__PURE__ */ jsxs("div", {
				className: "space-y-1",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "flex items-baseline justify-between gap-2 text-xs",
					children: [/* @__PURE__ */ jsx("span", {
						className: "font-medium",
						children: step.label
					}), /* @__PURE__ */ jsxs("span", {
						className: "tnum text-muted-foreground",
						children: [formatNumber(step.value), rate !== void 0 && index > 0 && /* @__PURE__ */ jsx("span", {
							className: cn("ml-1.5 font-medium", isWeak ? "text-warn" : "text-muted-foreground"),
							children: formatPercent(rate)
						})]
					})]
				}), /* @__PURE__ */ jsx("div", {
					className: "h-6 overflow-hidden rounded-md bg-muted",
					children: /* @__PURE__ */ jsx("div", {
						className: "h-full rounded-md transition-all",
						style: {
							width: `${Math.max(width, 2)}%`,
							background: `color-mix(in oklch, var(--chart-1) ${100 - index * 14}%, var(--chart-2))`
						}
					})
				})]
			}, step.key);
		}), overall !== void 0 && /* @__PURE__ */ jsxs("p", {
			className: "pt-1 text-xs text-muted-foreground",
			children: ["Overall conversion ", /* @__PURE__ */ jsx("span", {
				className: "font-semibold tnum text-foreground",
				children: formatPercent(overall, 2)
			})]
		})]
	});
}
//#endregion
export { Funnel as t };

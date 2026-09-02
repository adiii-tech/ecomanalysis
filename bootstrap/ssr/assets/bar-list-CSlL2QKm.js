import { t as cn } from "./utils-BVTyW6jK.js";
import { d as formatMetric } from "./card-DJDNvUnK.js";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/components/app/bar-list.tsx
/**
* A ranked horizontal bar list — the densest honest way to show "who is
* biggest" without spending a whole chart on it.
*/
function BarList({ rows, format = "currency", emptyLabel = "No data in this period." }) {
	if (rows.length === 0) return /* @__PURE__ */ jsx("p", {
		className: "py-6 text-center text-xs text-muted-foreground",
		children: emptyLabel
	});
	const max = Math.max(...rows.map((row) => Math.abs(row.value)), 1);
	return /* @__PURE__ */ jsx("div", {
		className: "space-y-2.5",
		children: rows.map((row) => {
			const width = row.share !== void 0 ? row.share : Math.abs(row.value) / max * 100;
			return /* @__PURE__ */ jsxs("div", {
				className: "space-y-1",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "flex items-baseline justify-between gap-2 text-xs",
					children: [/* @__PURE__ */ jsxs("span", {
						className: "flex min-w-0 items-center gap-1.5 font-medium",
						children: [row.color && /* @__PURE__ */ jsx("span", {
							className: "size-2 shrink-0 rounded-full",
							style: { background: row.color }
						}), /* @__PURE__ */ jsx("span", {
							className: "truncate",
							children: row.label
						})]
					}), /* @__PURE__ */ jsxs("span", {
						className: "shrink-0 tnum text-muted-foreground",
						children: [formatMetric(row.value, format, true), row.secondary && /* @__PURE__ */ jsx("span", {
							className: cn("ml-1.5", row.tone === "bad" && "text-bad", row.tone === "good" && "text-good"),
							children: row.secondary
						})]
					})]
				}), /* @__PURE__ */ jsx("div", {
					className: "h-1.5 overflow-hidden rounded-full bg-muted",
					children: /* @__PURE__ */ jsx("div", {
						className: "h-full rounded-full transition-all",
						style: {
							width: `${Math.max(Math.min(width, 100), 1)}%`,
							background: row.color ?? "var(--chart-1)"
						}
					})
				})]
			}, row.label);
		})
	});
}
//#endregion
export { BarList as t };

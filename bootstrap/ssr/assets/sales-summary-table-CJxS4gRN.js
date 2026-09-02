import { t as cn } from "./utils-BVTyW6jK.js";
import { f as formatNumber, o as formatCurrency } from "./card-DJDNvUnK.js";
import { a as Tooltip, o as TooltipContent, s as TooltipTrigger } from "./chart-card-CZPTjzl-.js";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/components/dashboard/sales-summary-table.tsx
/**
* The gross → net chain as a table. Subtotals and the final total are visually
* separated so the eye lands on the two numbers that matter.
*/
function SalesSummaryTable({ rows }) {
	return /* @__PURE__ */ jsx("div", {
		className: "overflow-x-auto scrollbar-thin",
		children: /* @__PURE__ */ jsxs("table", {
			className: "w-full text-sm",
			children: [/* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsxs("tr", {
				className: "border-b border-border",
				children: [
					/* @__PURE__ */ jsx("th", {
						className: "py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
						children: "Line"
					}),
					/* @__PURE__ */ jsx("th", {
						className: "py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
						children: "Orders"
					}),
					/* @__PURE__ */ jsx("th", {
						className: "py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
						children: "Amount"
					})
				]
			}) }), /* @__PURE__ */ jsx("tbody", { children: rows.map((row) => /* @__PURE__ */ jsxs("tr", {
				className: cn("border-b border-border/50 last:border-0", row.kind === "subtotal" && "bg-muted/40 font-medium", row.kind === "total" && "border-t-2 border-border bg-accent/40 font-semibold"),
				children: [
					/* @__PURE__ */ jsx("td", {
						className: "py-2 pl-1",
						children: /* @__PURE__ */ jsxs(Tooltip, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
							asChild: true,
							children: /* @__PURE__ */ jsx("span", {
								className: "cursor-help border-b border-dotted border-muted-foreground/40",
								children: row.label
							})
						}), /* @__PURE__ */ jsx(TooltipContent, { children: row.tooltip })] })
					}),
					/* @__PURE__ */ jsx("td", {
						className: "py-2 text-right tnum text-muted-foreground",
						children: row.orders > 0 ? formatNumber(row.orders) : "—"
					}),
					/* @__PURE__ */ jsx("td", {
						className: cn("py-2 pr-1 text-right tnum", row.kind === "negative" && row.amount !== 0 && "text-bad", row.kind === "total" && (row.amount >= 0 ? "text-good" : "text-bad")),
						children: formatCurrency(row.amount)
					})
				]
			}, row.key)) })]
		})
	});
}
//#endregion
export { SalesSummaryTable as t };

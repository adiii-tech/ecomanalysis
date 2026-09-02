import { o as useFilters } from "./empty-state-DjIQjBC7.js";
import { a as Tooltip, o as TooltipContent, s as TooltipTrigger } from "./chart-card-CZPTjzl-.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { Info } from "lucide-react";
//#region resources/js/components/app/returns-basis-toggle.tsx
/**
* Order-date basis attributes a return to the order's cohort; return-date basis
* attributes it to the day it came back. It changes every returns number in the
* app, not just the headline.
*/
function ReturnsBasisToggle() {
	const { filters, setFilters } = useFilters();
	return /* @__PURE__ */ jsxs("div", {
		className: "flex items-center gap-1.5",
		children: [/* @__PURE__ */ jsx(Tabs, {
			value: filters.returns_basis,
			onValueChange: (value) => setFilters({ returns_basis: value }),
			children: /* @__PURE__ */ jsxs(TabsList, { children: [/* @__PURE__ */ jsx(TabsTrigger, {
				value: "order_date",
				children: "Order date"
			}), /* @__PURE__ */ jsx(TabsTrigger, {
				value: "return_date",
				children: "Return date"
			})] })
		}), /* @__PURE__ */ jsxs(Tooltip, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
			asChild: true,
			children: /* @__PURE__ */ jsx("button", {
				type: "button",
				className: "text-muted-foreground/60 transition hover:text-muted-foreground",
				"aria-label": "About the returns basis",
				children: /* @__PURE__ */ jsx(Info, { className: "size-3.5" })
			})
		}), /* @__PURE__ */ jsxs(TooltipContent, { children: [
			/* @__PURE__ */ jsx("p", {
				className: "font-medium",
				children: "Returns basis"
			}),
			/* @__PURE__ */ jsxs("p", {
				className: "mt-1 opacity-90",
				children: [/* @__PURE__ */ jsx("b", { children: "Order date" }), " counts a return against the day the order was placed — cohort accounting, so a month’s true return rate settles over time."]
			}),
			/* @__PURE__ */ jsxs("p", {
				className: "mt-1 opacity-90",
				children: [/* @__PURE__ */ jsx("b", { children: "Return date" }), " counts it on the day it came back — what your warehouse actually handled this week."]
			})
		] })] })]
	});
}
//#endregion
export { ReturnsBasisToggle as t };

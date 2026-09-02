import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { TrendingDown, TrendingUp } from "lucide-react";
//#region resources/js/layouts/auth-layout.tsx
var CHAIN = [
	{
		label: "Gross Sales",
		tone: "muted"
	},
	{
		label: "− Discounts",
		tone: "down"
	},
	{
		label: "− Cancelled",
		tone: "down"
	},
	{
		label: "= Invoiced",
		tone: "mid"
	},
	{
		label: "− Returns & RTO",
		tone: "down"
	},
	{
		label: "= Net Sales",
		tone: "mid"
	},
	{
		label: "− COGS, fees, logistics",
		tone: "down"
	},
	{
		label: "= Contribution Margin",
		tone: "up"
	}
];
function AuthLayout({ title, description, children }) {
	return /* @__PURE__ */ jsxs("div", {
		className: "grid min-h-screen lg:grid-cols-2",
		children: [
			/* @__PURE__ */ jsx(Head, { title }),
			/* @__PURE__ */ jsx("div", {
				className: "flex items-center justify-center px-6 py-12",
				children: /* @__PURE__ */ jsxs("div", {
					className: "w-full max-w-sm space-y-6",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [
							/* @__PURE__ */ jsx("div", {
								className: "mb-6 flex size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground",
								children: /* @__PURE__ */ jsx(TrendingUp, { className: "size-5" })
							}),
							/* @__PURE__ */ jsx("h1", {
								className: "text-xl font-semibold tracking-tight",
								children: title
							}),
							description && /* @__PURE__ */ jsx("p", {
								className: "text-sm text-muted-foreground",
								children: description
							})
						]
					}), children]
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "relative hidden items-center justify-center overflow-hidden bg-sidebar px-12 lg:flex",
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "relative z-10 max-w-sm space-y-6",
						children: [
							/* @__PURE__ */ jsx("p", {
								className: "text-2xl font-semibold leading-snug tracking-tight",
								children: "Am I actually making money?"
							}),
							/* @__PURE__ */ jsx("p", {
								className: "text-sm leading-relaxed text-muted-foreground",
								children: "Every number here resolves down the chain — no gross revenue on its own, no vanity metric without the cost sitting next to it."
							}),
							/* @__PURE__ */ jsx("div", {
								className: "space-y-1.5",
								children: CHAIN.map((step) => /* @__PURE__ */ jsxs("div", {
									className: "flex items-center gap-2 rounded-lg border border-sidebar-border bg-card/60 px-3 py-2 text-xs",
									children: [
										step.tone === "down" && /* @__PURE__ */ jsx(TrendingDown, { className: "size-3.5 text-bad" }),
										step.tone === "up" && /* @__PURE__ */ jsx(TrendingUp, { className: "size-3.5 text-good" }),
										/* @__PURE__ */ jsx("span", {
											className: step.tone === "up" ? "font-semibold text-good" : step.tone === "mid" ? "font-medium" : "text-muted-foreground",
											children: step.label
										})
									]
								}, step.label))
							})
						]
					}),
					/* @__PURE__ */ jsx("div", { className: "pointer-events-none absolute -right-24 top-1/4 size-96 rounded-full bg-primary/10 blur-3xl" }),
					/* @__PURE__ */ jsx("div", { className: "pointer-events-none absolute -bottom-24 -left-16 size-80 rounded-full bg-chart-3/10 blur-3xl" })
				]
			})
		]
	});
}
//#endregion
export { AuthLayout as t };

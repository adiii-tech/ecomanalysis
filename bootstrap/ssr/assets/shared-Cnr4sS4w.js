import { t as EmptyState } from "./empty-state-DjIQjBC7.js";
import { u as formatLongDate } from "./card-DJDNvUnK.js";
import { i as VerdictNote } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { t as KpiCard } from "./kpi-card-ByDkhHxD.js";
import { t as ReportSectionView } from "./report-section-CBf1J4XS.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/pages/reports/shared.tsx
/**
* The public face of a shared report. No navigation, no filters, no way into
* the rest of the tenant — just the snapshot the sender froze into the link.
*/
function SharedReport({ expired, report, payload, tenant, filters, expires_at }) {
	if (expired || !report || !payload) return /* @__PURE__ */ jsxs("div", {
		className: "mx-auto flex min-h-dvh max-w-lg items-center justify-center p-6",
		children: [/* @__PURE__ */ jsx(Head, { title: "Link expired" }), /* @__PURE__ */ jsx(EmptyState, {
			title: "This link is no longer active",
			description: "Shared report links expire, and the person who created it can revoke it at any time. Ask them for a fresh link."
		})]
	});
	return /* @__PURE__ */ jsxs("div", {
		className: "min-h-dvh bg-background",
		children: [
			/* @__PURE__ */ jsx(Head, { title: `${report.label} · ${tenant?.name ?? "Shared report"}` }),
			/* @__PURE__ */ jsx("header", {
				className: "border-b border-border bg-card",
				children: /* @__PURE__ */ jsxs("div", {
					className: "mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4",
					children: [/* @__PURE__ */ jsxs("div", { children: [
						/* @__PURE__ */ jsxs("p", {
							className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
							children: [tenant?.name, " · shared report"]
						}),
						/* @__PURE__ */ jsx("h1", {
							className: "mt-0.5 text-lg font-semibold",
							children: report.label
						}),
						/* @__PURE__ */ jsx("p", {
							className: "text-xs text-muted-foreground",
							children: report.description
						})
					] }), /* @__PURE__ */ jsxs("div", {
						className: "text-right text-xs text-muted-foreground",
						children: [
							filters && /* @__PURE__ */ jsxs("p", { children: [
								formatLongDate(filters.from),
								" — ",
								formatLongDate(filters.to)
							] }),
							/* @__PURE__ */ jsxs("p", {
								className: "mt-0.5",
								children: [filters?.channel === "all" ? "All channels" : filters?.channel, filters?.returns_basis === "return_date" ? " · return-date basis" : " · order-date basis"]
							}),
							expires_at && /* @__PURE__ */ jsxs("p", {
								className: "mt-0.5",
								children: ["Link expires ", formatLongDate(expires_at)]
							})
						]
					})]
				})
			}),
			/* @__PURE__ */ jsxs("main", {
				className: "mx-auto max-w-6xl space-y-4 px-5 py-6",
				children: [
					/* @__PURE__ */ jsx(VerdictNote, { verdict: payload.verdict }),
					payload.kpis.length > 0 && /* @__PURE__ */ jsx("div", {
						className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-4",
						children: payload.kpis.map((metric) => /* @__PURE__ */ jsx(KpiCard, { metric }, metric.key))
					}),
					payload.caveats.map((caveat, index) => /* @__PURE__ */ jsx(CaveatNote, { caveat }, index)),
					payload.sections.map((section, index) => /* @__PURE__ */ jsx(ReportSectionView, {
						section,
						readOnly: true
					}, `${section.type}-${index}`)),
					/* @__PURE__ */ jsx("p", {
						className: "pt-4 text-center text-[11px] text-muted-foreground",
						children: "A read-only snapshot. Numbers reflect the data at the time this page was opened."
					})
				]
			})
		]
	});
}
//#endregion
export { SharedReport as default };

import { t as cn } from "./utils-BVTyW6jK.js";
import { t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { t as EmptyState } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, f as formatNumber, o as formatCurrency, s as formatDate, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as Skeleton } from "./skeleton-DUakt-29.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/pages/customers/show.tsx
function CustomerShow({ customerId }) {
	const profile = useWidget(`customers/customer/${customerId}`);
	const customer = profile.data?.customer;
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: customer?.name ?? "Customer",
		description: customer?.email ?? void 0,
		showFilters: false,
		breadcrumb: {
			label: "Customers",
			href: "/customers"
		},
		children: [
			/* @__PURE__ */ jsx(Head, { title: customer?.name ?? "Customer" }),
			profile.loading && /* @__PURE__ */ jsx(Skeleton, { className: "h-32 w-full" }),
			profile.error && /* @__PURE__ */ jsx(EmptyState, {
				kind: "error",
				title: "Could not load this customer",
				description: profile.error
			}),
			customer && /* @__PURE__ */ jsxs(Fragment, { children: [
				/* @__PURE__ */ jsx("div", {
					className: "grid gap-3 grid-cols-2 lg:grid-cols-6",
					children: [
						["Orders", formatNumber(customer.orders_count)],
						["Total spent", formatCurrency(customer.total_spent)],
						["AOV", formatCurrency(customer.aov)],
						["Margin earned", formatCurrency(customer.total_margin)],
						["Returns", formatNumber(customer.returns_count)],
						["Days since order", customer.days_since_last_order === null ? "—" : `${customer.days_since_last_order}d`]
					].map(([label, value]) => /* @__PURE__ */ jsxs(Card, {
						className: "p-4",
						children: [/* @__PURE__ */ jsx("p", {
							className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
							children: label
						}), /* @__PURE__ */ jsx("p", {
							className: "mt-2 text-lg font-semibold tnum",
							children: value
						})]
					}, label))
				}),
				/* @__PURE__ */ jsxs(Card, {
					className: "flex flex-wrap items-center gap-3 p-4",
					children: [
						profile.data?.segment && /* @__PURE__ */ jsx(Badge, {
							variant: customer.is_vip ? "good" : "default",
							children: profile.data.segment
						}),
						customer.churn_risk_score !== null && /* @__PURE__ */ jsxs(Badge, {
							variant: customer.churn_risk_score >= 60 ? "bad" : "muted",
							children: ["Churn risk ", customer.churn_risk_score]
						}),
						/* @__PURE__ */ jsxs("span", {
							className: "text-xs text-muted-foreground",
							children: [[customer.city, customer.state].filter(Boolean).join(", ") || "Location unknown", customer.first_order_at && ` · first order ${formatDate(customer.first_order_at)}`]
						}),
						profile.data?.playbook && /* @__PURE__ */ jsxs("p", {
							className: "w-full text-xs leading-snug text-muted-foreground",
							children: ["→ ", profile.data.playbook]
						})
					]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "grid gap-4 xl:grid-cols-3",
					children: [/* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "Orders",
						subtitle: "Every order this customer has placed",
						children: /* @__PURE__ */ jsx(DataTable, {
							rows: profile.data?.orders ?? [],
							rowKey: (row) => row.id,
							columns: [
								{
									key: "order",
									header: "Order",
									value: (r) => r.order_number,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "font-medium",
										children: r.order_number
									})
								},
								{
									key: "when",
									header: "Placed",
									value: (r) => r.placed_at,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "text-muted-foreground",
										children: formatDateTime(r.placed_at)
									})
								},
								{
									key: "channel",
									header: "Channel",
									value: (r) => r.channel_name,
									render: (r) => r.channel_name ?? "—"
								},
								{
									key: "status",
									header: "Status",
									value: (r) => r.status,
									render: (r) => /* @__PURE__ */ jsx(Badge, {
										variant: "outline",
										children: r.status
									})
								},
								{
									key: "net",
									header: "Net",
									align: "right",
									sortable: true,
									value: (r) => r.net_amount,
									render: (r) => formatCurrency(r.net_amount)
								},
								{
									key: "margin",
									header: "Margin",
									align: "right",
									sortable: true,
									value: (r) => r.contribution_margin,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: r.contribution_margin < 0 ? "font-medium text-bad" : "",
										children: formatCurrency(r.contribution_margin)
									})
								}
							]
						})
					}), /* @__PURE__ */ jsx(ChartCard, {
						title: "Timeline",
						subtitle: "Orders and returns, newest first",
						children: /* @__PURE__ */ jsx("div", {
							className: "space-y-2",
							children: (profile.data?.timeline ?? []).slice(0, 25).map((event, index) => /* @__PURE__ */ jsxs("div", {
								className: "flex items-start gap-2.5 border-b border-border/50 pb-2 last:border-0",
								children: [
									/* @__PURE__ */ jsx("span", { className: cn("mt-1.5 size-2 shrink-0 rounded-full", event.type === "return" ? "bg-bad" : "bg-good") }),
									/* @__PURE__ */ jsxs("div", {
										className: "min-w-0 flex-1",
										children: [/* @__PURE__ */ jsx("p", {
											className: "truncate text-xs font-medium",
											children: event.title
										}), /* @__PURE__ */ jsxs("p", {
											className: "text-[11px] text-muted-foreground",
											children: [formatDate(event.at), event.meta ? ` · ${event.meta}` : ""]
										})]
									}),
									/* @__PURE__ */ jsx("span", {
										className: cn("shrink-0 text-xs tnum", event.amount < 0 ? "text-bad" : ""),
										children: formatCurrency(event.amount)
									})
								]
							}, index))
						})
					})]
				}),
				(profile.data?.returns.length ?? 0) > 0 && /* @__PURE__ */ jsx(ChartCard, {
					title: "Returns",
					subtitle: "What came back and why",
					children: /* @__PURE__ */ jsx(DataTable, {
						dense: true,
						rows: profile.data?.returns ?? [],
						rowKey: (row) => row.id,
						columns: [
							{
								key: "order",
								header: "Order",
								value: (r) => r.order_number,
								render: (r) => r.order_number
							},
							{
								key: "type",
								header: "Type",
								value: (r) => r.type,
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: r.type === "rto" ? "bad" : "warn",
									children: r.type.replace("_", " ")
								})
							},
							{
								key: "reason",
								header: "Reason",
								value: (r) => r.reason_text,
								render: (r) => r.reason_text ?? "—"
							},
							{
								key: "when",
								header: "Initiated",
								value: (r) => r.initiated_at,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: formatDate(r.initiated_at)
								})
							},
							{
								key: "refund",
								header: "Refund",
								align: "right",
								sortable: true,
								value: (r) => r.refund_amount,
								render: (r) => formatCurrency(r.refund_amount)
							}
						]
					})
				})
			] })
		]
	});
}
//#endregion
export { CustomerShow as default };

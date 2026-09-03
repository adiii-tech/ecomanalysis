import { t as cn } from "./utils-BVTyW6jK.js";
import { t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { c as formatDateTime, f as formatNumber, i as formatCompactCurrency, o as formatCurrency, p as formatPercent } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { a as GRID_PROPS, i as ChartTooltip, n as CHART_COLORS, o as axisCurrency, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { n as KpiStrip } from "./kpi-card-CF3GcgfJ.js";
import { n as WaterfallLegend, t as WaterfallChart } from "./waterfall-chart-CcaRf_l7.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { Area, AreaChart, CartesianGrid, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/pages/finance/index.tsx
function Finance() {
	const kpis = useWidget("finance/kpis");
	const salesOverTime = useWidget("finance/sales-over-time");
	const aov = useWidget("finance/aov-trend");
	const breakdown = useWidget("finance/revenue-breakdown");
	const geo = useWidget("finance/geographic-sales");
	const topSkus = useWidget("finance/top-skus");
	const payments = useWidget("finance/payment-method-split");
	const refunds = useWidget("finance/refunds");
	const pnl = useWidget("finance/pnl");
	const cash = useWidget("finance/cash-flow");
	const gst = useWidget("finance/gst");
	const ledger = useWidget("finance/transactions", { per_page: 50 });
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Finance",
		description: "What you billed, what you kept, and what it cost you",
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Finance" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "finance.kpi_strip.view",
				children: /* @__PURE__ */ jsx(KpiStrip, {
					metrics: kpis.data,
					loading: kpis.loading,
					columns: 6
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "finance.sales_over_time.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Net sales over time",
						subtitle: "Current period against the one before it",
						widgetKey: "finance.sales_over_time",
						loading: salesOverTime.loading,
						error: salesOverTime.error,
						onRetry: salesOverTime.reload,
						insightPayload: salesOverTime.data,
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 270,
							children: /* @__PURE__ */ jsxs(AreaChart, {
								data: salesOverTime.data ?? [],
								margin: {
									top: 4,
									right: 6,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
										id: "fin-net",
										x1: "0",
										y1: "0",
										x2: "0",
										y2: "1",
										children: [/* @__PURE__ */ jsx("stop", {
											offset: "0%",
											stopColor: "var(--chart-1)",
											stopOpacity: .3
										}), /* @__PURE__ */ jsx("stop", {
											offset: "100%",
											stopColor: "var(--chart-1)",
											stopOpacity: 0
										})]
									}) }),
									/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
									/* @__PURE__ */ jsx(XAxis, {
										dataKey: "date",
										...AXIS_PROPS,
										tickFormatter: axisDate,
										minTickGap: 28
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 56
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, {}) }),
									/* @__PURE__ */ jsx(Area, {
										type: "monotone",
										dataKey: "net_sales",
										name: "Net sales",
										stroke: "var(--chart-1)",
										strokeWidth: 2,
										fill: "url(#fin-net)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										type: "monotone",
										dataKey: "prev_net_sales",
										name: "Previous period",
										stroke: "var(--chart-7)",
										strokeWidth: 1.5,
										strokeDasharray: "4 4",
										dot: false,
										isAnimationActive: false
									})
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [{
							label: "Net sales",
							color: "var(--chart-1)"
						}, {
							label: "Previous period",
							color: "var(--chart-7)"
						}] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "finance.cash_flow.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Cash flow",
						subtitle: "Money in vs money stuck",
						widgetKey: "finance.cash_flow",
						loading: cash.loading,
						error: cash.error,
						onRetry: cash.reload,
						caveat: cash.data?.caveat,
						children: /* @__PURE__ */ jsxs("div", {
							className: "space-y-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "rounded-lg border border-good/25 bg-good-soft/40 p-3",
									children: [
										/* @__PURE__ */ jsx("p", {
											className: "text-[11px] uppercase tracking-wide text-good/80",
											children: "Money in"
										}),
										/* @__PURE__ */ jsx("p", {
											className: "text-lg font-semibold tnum text-good",
											children: formatCurrency(cash.data?.money_in ?? 0)
										}),
										/* @__PURE__ */ jsxs("p", {
											className: "mt-0.5 text-[11px] text-muted-foreground",
											children: [
												formatCompactCurrency(cash.data?.prepaid_settled ?? 0),
												" prepaid · ",
												formatCompactCurrency(cash.data?.cod_remitted ?? 0),
												" COD remitted"
											]
										})
									]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "rounded-lg border border-warn/25 bg-warn-soft/40 p-3",
									children: [
										/* @__PURE__ */ jsx("p", {
											className: "text-[11px] uppercase tracking-wide text-warn/90",
											children: "Working capital locked"
										}),
										/* @__PURE__ */ jsx("p", {
											className: "text-lg font-semibold tnum text-warn",
											children: formatCurrency(cash.data?.working_capital_locked ?? 0)
										}),
										/* @__PURE__ */ jsx("dl", {
											className: "mt-1.5 space-y-1 text-[11px]",
											children: [
												["COD collected, not remitted", cash.data?.cod_awaiting_remittance ?? 0],
												["COD still in transit", cash.data?.cod_in_transit ?? 0],
												["Refund liability on open returns", cash.data?.returns_liability ?? 0]
											].map(([label, value]) => /* @__PURE__ */ jsxs("div", {
												className: "flex justify-between gap-2",
												children: [/* @__PURE__ */ jsx("dt", {
													className: "text-muted-foreground",
													children: label
												}), /* @__PURE__ */ jsx("dd", {
													className: "tnum",
													children: formatCompactCurrency(value)
												})]
											}, label))
										})
									]
								}),
								/* @__PURE__ */ jsxs("p", {
									className: "text-xs text-muted-foreground",
									children: [
										"COD takes ",
										/* @__PURE__ */ jsxs("span", {
											className: "font-semibold tnum text-foreground",
											children: [cash.data?.avg_remittance_days ?? 0, " days"]
										}),
										" on average to reach your account."
									]
								})
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "finance.pnl.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "P&L statement",
					subtitle: "Revenue down to EBITDA, month by month",
					widgetKey: "finance.pnl",
					tooltip: "Contribution margin comes from order-level economics; ad spend and fixed opex are added here because neither belongs to a single order.",
					loading: pnl.loading,
					error: pnl.error,
					onRetry: pnl.reload,
					verdict: pnl.data?.verdict,
					caveat: pnl.data?.caveat,
					insightPayload: cash.data,
					children: /* @__PURE__ */ jsx("div", {
						className: "overflow-x-auto scrollbar-thin",
						children: /* @__PURE__ */ jsxs("table", {
							className: "w-full min-w-[640px] text-sm",
							children: [/* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsxs("tr", {
								className: "border-b border-border",
								children: [
									/* @__PURE__ */ jsx("th", {
										className: "sticky left-0 bg-card py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
										children: "Line"
									}),
									(pnl.data?.columns ?? []).map((column) => /* @__PURE__ */ jsx("th", {
										className: "px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
										children: column.month
									}, column.key)),
									/* @__PURE__ */ jsx("th", {
										className: "px-3 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-foreground",
										children: "Total"
									})
								]
							}) }), /* @__PURE__ */ jsx("tbody", { children: (pnl.data?.rows ?? []).map((row) => /* @__PURE__ */ jsxs("tr", {
								className: cn("border-b border-border/50 last:border-0", row.kind === "subtotal" && "bg-muted/40 font-medium", row.kind === "total" && "border-t-2 border-border bg-accent/40 font-semibold"),
								children: [
									/* @__PURE__ */ jsx("td", {
										className: cn("sticky left-0 bg-inherit py-2 pr-3", row.indent && "pl-4 text-muted-foreground"),
										children: row.label
									}),
									(pnl.data?.columns ?? []).map((column) => {
										const value = Number(column[row.key] ?? 0);
										return /* @__PURE__ */ jsx("td", {
											className: cn("px-3 py-2 text-right tnum", row.kind === "negative" && value !== 0 && "text-bad", row.kind === "total" && (value >= 0 ? "text-good" : "text-bad")),
											children: formatCompactCurrency(value)
										}, column.key);
									}),
									/* @__PURE__ */ jsx("td", {
										className: cn("px-3 py-2 text-right font-medium tnum", row.kind === "negative" && "text-bad", row.kind === "total" && ((pnl.data?.totals[row.key] ?? 0) >= 0 ? "text-good" : "text-bad")),
										children: formatCompactCurrency(pnl.data?.totals[row.key] ?? 0)
									})
								]
							}, row.key)) })]
						})
					})
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "finance.revenue_breakdown.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Revenue breakdown",
						subtitle: "Gross to contribution margin",
						widgetKey: "finance.revenue_breakdown",
						loading: breakdown.loading,
						error: breakdown.error,
						onRetry: breakdown.reload,
						exportDataset: "sales_summary",
						children: [/* @__PURE__ */ jsx(WaterfallChart, { steps: breakdown.data?.steps ?? [] }), /* @__PURE__ */ jsx(WaterfallLegend, { steps: breakdown.data?.steps ?? [] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "finance.aov_trend.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "AOV over time",
						subtitle: "Current vs previous period",
						widgetKey: "finance.aov_trend",
						loading: aov.loading,
						error: aov.error,
						onRetry: aov.reload,
						children: /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 250,
							children: /* @__PURE__ */ jsxs(AreaChart, {
								data: aov.data ?? [],
								margin: {
									top: 4,
									right: 6,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
										id: "fin-aov",
										x1: "0",
										y1: "0",
										x2: "0",
										y2: "1",
										children: [/* @__PURE__ */ jsx("stop", {
											offset: "0%",
											stopColor: "var(--chart-2)",
											stopOpacity: .3
										}), /* @__PURE__ */ jsx("stop", {
											offset: "100%",
											stopColor: "var(--chart-2)",
											stopOpacity: 0
										})]
									}) }),
									/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
									/* @__PURE__ */ jsx(XAxis, {
										dataKey: "date",
										...AXIS_PROPS,
										tickFormatter: axisDate,
										minTickGap: 28
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 56
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, {}) }),
									/* @__PURE__ */ jsx(Area, {
										type: "monotone",
										dataKey: "aov",
										name: "AOV",
										stroke: "var(--chart-2)",
										strokeWidth: 2,
										fill: "url(#fin-aov)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										type: "monotone",
										dataKey: "prev_aov",
										name: "Previous",
										stroke: "var(--chart-7)",
										strokeWidth: 1.5,
										strokeDasharray: "4 4",
										dot: false,
										isAnimationActive: false
									})
								]
							})
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "finance.geographic_sales.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Geographic sales",
							subtitle: "Realised net sales by state",
							widgetKey: "finance.geographic_sales",
							loading: geo.loading,
							error: geo.error,
							onRetry: geo.reload,
							exportDataset: "geo_states",
							children: /* @__PURE__ */ jsx(BarList, { rows: (geo.data?.rows ?? []).slice(0, 12).map((row) => ({
								label: row.state,
								value: row.net_sales,
								share: row.share_pct,
								secondary: formatPercent(row.margin_pct),
								tone: row.margin_pct < 0 ? "bad" : "neutral"
							})) })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "finance.payment_method_split.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Payment method split",
							subtitle: "Where the money came from",
							widgetKey: "finance.payment_method_split",
							loading: payments.loading,
							error: payments.error,
							onRetry: payments.reload,
							children: /* @__PURE__ */ jsx(BarList, { rows: (payments.data?.rows ?? []).map((row, index) => ({
								label: row.method,
								value: row.amount,
								share: row.share_pct,
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: `${formatNumber(row.transactions)} txns`
							})) })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "finance.gst.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "GST summary",
							subtitle: "Output tax by HSN",
							widgetKey: "finance.gst",
							loading: gst.loading,
							error: gst.error,
							onRetry: gst.reload,
							caveat: gst.data?.caveat,
							exportDataset: "gst_summary",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: gst.data?.rows ?? [],
								rowKey: (row) => `${row.hsn}-${row.gst_rate}`,
								columns: [
									{
										key: "hsn",
										header: "HSN",
										value: (r) => r.hsn,
										render: (r) => /* @__PURE__ */ jsx("span", {
											className: "font-medium",
											children: r.hsn
										})
									},
									{
										key: "rate",
										header: "Rate",
										align: "right",
										value: (r) => r.gst_rate,
										render: (r) => `${r.gst_rate}%`
									},
									{
										key: "taxable",
										header: "Taxable",
										align: "right",
										sortable: true,
										value: (r) => r.taxable_value,
										render: (r) => formatCompactCurrency(r.taxable_value)
									},
									{
										key: "tax",
										header: "Tax",
										align: "right",
										sortable: true,
										value: (r) => r.tax_amount,
										render: (r) => formatCompactCurrency(r.tax_amount)
									}
								],
								footer: /* @__PURE__ */ jsxs("tr", { children: [
									/* @__PURE__ */ jsx("td", {
										className: "px-2.5 py-2 text-xs font-semibold",
										colSpan: 2,
										children: "Total"
									}),
									/* @__PURE__ */ jsx("td", {
										className: "px-2.5 py-2 text-right text-xs font-semibold tnum",
										children: formatCompactCurrency(gst.data?.total_taxable ?? 0)
									}),
									/* @__PURE__ */ jsx("td", {
										className: "px-2.5 py-2 text-right text-xs font-semibold tnum",
										children: formatCompactCurrency(gst.data?.total_tax ?? 0)
									})
								] })
							})
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "finance.top_skus.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Top SKUs by net sales",
						subtitle: "Units, orders and the margin behind them",
						widgetKey: "finance.top_skus",
						loading: topSkus.loading,
						error: topSkus.error,
						onRetry: topSkus.reload,
						exportDataset: "top_skus",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: topSkus.data?.rows ?? [],
							rowKey: (row) => row.sku_code,
							columns: [
								{
									key: "sku",
									header: "SKU",
									value: (r) => r.sku_code,
									render: (r) => /* @__PURE__ */ jsxs("div", {
										className: "min-w-0",
										children: [/* @__PURE__ */ jsx("p", {
											className: "truncate font-medium",
											children: r.sku_code
										}), /* @__PURE__ */ jsx("p", {
											className: "truncate text-[11px] text-muted-foreground",
											children: r.name
										})]
									})
								},
								{
									key: "units",
									header: "Units",
									align: "right",
									sortable: true,
									value: (r) => Number(r.units),
									render: (r) => formatNumber(Number(r.units))
								},
								{
									key: "net",
									header: "Net sales",
									align: "right",
									sortable: true,
									value: (r) => r.net_sales,
									render: (r) => formatCompactCurrency(r.net_sales)
								},
								{
									key: "margin",
									header: "Margin",
									align: "right",
									sortable: true,
									value: (r) => r.margin,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: r.margin < 0 ? "font-medium text-bad" : "",
										children: [formatCompactCurrency(r.margin), /* @__PURE__ */ jsx("span", {
											className: "ml-1 text-[10px] text-muted-foreground",
											children: formatPercent(r.margin_pct)
										})]
									})
								}
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "finance.refunds.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Refunds",
						subtitle: `${formatNumber(refunds.data?.count ?? 0)} refunds · ${formatCurrency(refunds.data?.total ?? 0)}`,
						widgetKey: "finance.refunds",
						loading: refunds.loading,
						error: refunds.error,
						onRetry: refunds.reload,
						exportDataset: "transactions",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: refunds.data?.rows ?? [],
							rowKey: (row, index) => `${row.order_number}-${index}`,
							columns: [
								{
									key: "order",
									header: "Order",
									value: (r) => r.order_number,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "font-medium",
										children: r.order_number ?? "—"
									})
								},
								{
									key: "gateway",
									header: "Gateway",
									value: (r) => r.gateway,
									render: (r) => r.gateway ?? "—"
								},
								{
									key: "when",
									header: "Processed",
									value: (r) => r.processed_at,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "text-muted-foreground",
										children: formatDateTime(r.processed_at)
									})
								},
								{
									key: "amount",
									header: "Amount",
									align: "right",
									sortable: true,
									value: (r) => r.amount,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "text-bad",
										children: formatCurrency(r.amount)
									})
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "finance.transaction_ledger.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Transaction ledger",
					subtitle: "Every payment, refund and failure",
					widgetKey: "finance.transaction_ledger",
					loading: ledger.loading,
					error: ledger.error,
					onRetry: ledger.reload,
					exportDataset: "transactions",
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						searchPlaceholder: "Search by order number…",
						rows: ledger.data?.data ?? [],
						rowKey: (row) => row.id,
						columns: [
							{
								key: "order",
								header: "Order",
								value: (r) => r.order_number,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium",
									children: r.order_number ?? "—"
								})
							},
							{
								key: "gateway",
								header: "Gateway",
								value: (r) => r.gateway,
								render: (r) => r.gateway ?? "—"
							},
							{
								key: "method",
								header: "Method",
								value: (r) => r.method,
								render: (r) => r.method ?? "—"
							},
							{
								key: "kind",
								header: "Kind",
								value: (r) => r.kind,
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: r.kind === "refund" ? "bad" : "muted",
									children: r.kind
								})
							},
							{
								key: "status",
								header: "Status",
								value: (r) => r.status,
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: r.status === "success" ? "good" : "bad",
									children: r.status
								})
							},
							{
								key: "when",
								header: "Processed",
								value: (r) => r.processed_at,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: formatDateTime(r.processed_at)
								})
							},
							{
								key: "fee",
								header: "Fee",
								align: "right",
								sortable: true,
								value: (r) => r.fee,
								render: (r) => formatCurrency(r.fee)
							},
							{
								key: "amount",
								header: "Amount",
								align: "right",
								sortable: true,
								value: (r) => r.amount,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: r.amount < 0 ? "text-bad" : "",
									children: formatCurrency(r.amount)
								})
							}
						]
					})
				})
			})
		]
	});
}
//#endregion
export { Finance as default };

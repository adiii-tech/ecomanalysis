import { t as cn } from "./utils-BVTyW6jK.js";
import { r as Button } from "./input-C0zE_xzz.js";
import { t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { f as formatNumber, i as formatCompactCurrency, o as formatCurrency, p as formatPercent, s as formatDate, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { a as GRID_PROPS, c as axisNumber, i as ChartTooltip, n as CHART_COLORS, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { t as StatStrip } from "./stat-strip-Bf4c-qAu.js";
import { Head, Link } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { Filter } from "lucide-react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/pages/customers/index.tsx
function retentionColor(pct) {
	const alpha = Math.min(Math.max(pct / 60, .06), 1);
	return `color-mix(in oklch, var(--chart-1) ${Math.round(alpha * 100)}%, transparent)`;
}
function Customers() {
	const kpis = useWidget("customers/kpis");
	const list = useWidget("customers/list", { per_page: 50 });
	const rfm = useWidget("customers/rfm");
	const cohorts = useWidget("customers/cohorts");
	const repeat = useWidget("customers/repeat-metrics");
	const churn = useWidget("customers/churn");
	const vip = useWidget("customers/vip");
	const ltv = useWidget("customers/ltv-distribution");
	const interval = useWidget("customers/purchase-interval");
	const returners = useWidget("customers/per-customer-returns");
	const geo = useWidget("customers/geo");
	const reviews = useWidget("reviews/summary");
	const recentReviews = useWidget("reviews/recent", { per_page: 20 });
	const correlation = useWidget("reviews/return-correlation");
	const customerColumns = [
		{
			key: "name",
			header: "Customer",
			value: (r) => r.name ?? r.email ?? "",
			render: (r) => /* @__PURE__ */ jsxs(Link, {
				href: `/customers/${r.id}`,
				className: "min-w-0 hover:underline",
				children: [/* @__PURE__ */ jsx("p", {
					className: "truncate font-medium",
					children: r.name ?? "—"
				}), /* @__PURE__ */ jsx("p", {
					className: "truncate text-[11px] text-muted-foreground",
					children: r.email ?? "—"
				})]
			})
		},
		{
			key: "location",
			header: "Location",
			value: (r) => `${r.city ?? ""} ${r.state ?? ""}`,
			render: (r) => /* @__PURE__ */ jsx("span", {
				className: "text-muted-foreground",
				children: [r.city, r.state].filter(Boolean).join(", ") || "—"
			})
		},
		{
			key: "segment",
			header: "Segment",
			value: (r) => r.rfm_label,
			render: (r) => r.rfm_label ? /* @__PURE__ */ jsx(Badge, {
				variant: r.is_vip ? "good" : "muted",
				children: r.rfm_label
			}) : "—"
		},
		{
			key: "orders",
			header: "Orders",
			align: "right",
			sortable: true,
			value: (r) => r.orders_count,
			render: (r) => formatNumber(r.orders_count)
		},
		{
			key: "aov",
			header: "AOV",
			align: "right",
			sortable: true,
			value: (r) => r.aov,
			render: (r) => formatCurrency(r.aov)
		},
		{
			key: "spent",
			header: "Total spent",
			align: "right",
			sortable: true,
			value: (r) => r.total_spent,
			render: (r) => /* @__PURE__ */ jsx("span", {
				className: "font-medium",
				children: formatCurrency(r.total_spent)
			})
		},
		{
			key: "last",
			header: "Last order",
			align: "right",
			sortable: true,
			value: (r) => r.last_order_at,
			render: (r) => /* @__PURE__ */ jsx("span", {
				className: "text-muted-foreground",
				children: r.last_order_at ? formatDate(r.last_order_at) : "—"
			})
		}
	];
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Customers & Reviews",
		description: "Who buys again, who is about to leave, and what they say",
		actions: /* @__PURE__ */ jsx(PermissionGuard, {
			permission: "customer_intelligence.explorer.view",
			children: /* @__PURE__ */ jsx(Button, {
				variant: "outline",
				size: "sm",
				asChild: true,
				children: /* @__PURE__ */ jsxs(Link, {
					href: "/customers/explorer",
					children: [/* @__PURE__ */ jsx(Filter, { className: "size-3.5" }), " Explorer"]
				})
			})
		}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Customers & Reviews" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "customer_intelligence.kpi_strip.view",
				children: /* @__PURE__ */ jsxs("div", {
					className: "space-y-2",
					children: [/* @__PURE__ */ jsx(StatStrip, {
						stats: kpis.data ?? null,
						loading: kpis.loading,
						columns: 6
					}), /* @__PURE__ */ jsx(CaveatNote, { caveat: kpis.data?.caveat ?? null })]
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "customer_intelligence.cohorts.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Cohort retention",
					subtitle: "Acquisition month × months since first order",
					widgetKey: "customer_intelligence.cohorts",
					tooltip: "Each row is the customers who first bought in that month. Each cell is the share of them who ordered again that many months later.",
					loading: cohorts.loading,
					error: cohorts.error,
					onRetry: cohorts.reload,
					verdict: cohorts.data?.verdict,
					caveat: cohorts.data?.caveat,
					exportDataset: "cohort_retention",
					empty: (cohorts.data?.cohorts.length ?? 0) === 0,
					children: /* @__PURE__ */ jsx("div", {
						className: "overflow-x-auto scrollbar-thin",
						children: /* @__PURE__ */ jsxs("table", {
							className: "w-full min-w-[620px] border-collapse text-xs",
							children: [/* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsxs("tr", { children: [
								/* @__PURE__ */ jsx("th", {
									className: "py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
									children: "Cohort"
								}),
								/* @__PURE__ */ jsx("th", {
									className: "px-2 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
									children: "Size"
								}),
								Array.from({ length: (cohorts.data?.months ?? 12) + 1 }).map((_, index) => /* @__PURE__ */ jsxs("th", {
									className: "px-1 py-2 text-center text-[11px] font-semibold text-muted-foreground",
									children: ["M", index]
								}, index))
							] }) }), /* @__PURE__ */ jsx("tbody", { children: (cohorts.data?.cohorts ?? []).map((cohort) => /* @__PURE__ */ jsxs("tr", { children: [
								/* @__PURE__ */ jsx("td", {
									className: "py-1 pr-2 font-medium",
									children: cohort.cohort_month
								}),
								/* @__PURE__ */ jsx("td", {
									className: "px-2 py-1 text-right tnum text-muted-foreground",
									children: formatNumber(cohort.cohort_size)
								}),
								cohort.cells.map((cell, index) => /* @__PURE__ */ jsx("td", {
									className: "p-0.5",
									children: cell ? /* @__PURE__ */ jsx("div", {
										className: "rounded px-1 py-1.5 text-center tnum",
										style: { background: retentionColor(cell.retention_pct) },
										title: `${formatNumber(cell.active_customers)} customers · avg LTV ${formatCompactCurrency(cell.avg_ltv)}`,
										children: cell.retention_pct >= .5 ? `${Math.round(cell.retention_pct)}%` : "·"
									}) : /* @__PURE__ */ jsx("div", {
										className: "py-1.5 text-center text-muted-foreground/30",
										children: "–"
									})
								}, index))
							] }, cohort.cohort_month)) })]
						})
					})
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "customer_intelligence.rfm.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "RFM segmentation",
						subtitle: "Recency, frequency and monetary quintiles",
						widgetKey: "customer_intelligence.rfm",
						loading: rfm.loading,
						error: rfm.error,
						onRetry: rfm.reload,
						verdict: rfm.data?.verdict,
						caveat: rfm.data?.caveat,
						exportDataset: "customers",
						children: /* @__PURE__ */ jsx("div", {
							className: "grid gap-2 sm:grid-cols-2",
							children: (rfm.data?.rows ?? []).map((row) => /* @__PURE__ */ jsxs("div", {
								className: "rounded-lg border border-border p-3",
								children: [
									/* @__PURE__ */ jsxs("div", {
										className: "flex items-baseline justify-between gap-2",
										children: [/* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-1.5 text-xs font-semibold",
											children: [/* @__PURE__ */ jsx("span", {
												className: "size-2 rounded-full",
												style: { background: row.color }
											}), row.label]
										}), /* @__PURE__ */ jsx("span", {
											className: "text-[11px] tnum text-muted-foreground",
											children: formatNumber(row.customers)
										})]
									}),
									/* @__PURE__ */ jsx("p", {
										className: "mt-1 text-sm font-semibold tnum",
										children: formatCompactCurrency(row.revenue)
									}),
									/* @__PURE__ */ jsxs("p", {
										className: "text-[11px] text-muted-foreground",
										children: [
											formatPercent(row.revenue_share_pct, 0),
											" of revenue · avg LTV ",
											formatCompactCurrency(row.avg_ltv)
										]
									}),
									/* @__PURE__ */ jsx("p", {
										className: "mt-1.5 text-[11px] leading-snug text-muted-foreground",
										children: row.playbook
									})
								]
							}, row.segment))
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "customer_intelligence.repeat_metrics.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Repeat metrics",
						widgetKey: "customer_intelligence.repeat_metrics",
						loading: repeat.loading,
						error: repeat.error,
						onRetry: repeat.reload,
						caveat: repeat.data?.caveat,
						children: /* @__PURE__ */ jsx("dl", {
							className: "space-y-2.5",
							children: [
								["Repeat purchase rate", formatPercent(repeat.data?.repeat_purchase_rate ?? 0)],
								["Avg orders / customer", (repeat.data?.avg_orders_per_customer ?? 0).toFixed(2)],
								["2nd order within 90 days", formatPercent(repeat.data?.second_order_within_90d_pct ?? 0)],
								["Avg days to 2nd order", `${repeat.data?.avg_days_to_second_order ?? 0}d`]
							].map(([label, value]) => /* @__PURE__ */ jsxs("div", {
								className: "flex items-baseline justify-between gap-3 border-b border-border/50 pb-2 last:border-0",
								children: [/* @__PURE__ */ jsx("dt", {
									className: "text-xs text-muted-foreground",
									children: label
								}), /* @__PURE__ */ jsx("dd", {
									className: "text-sm font-semibold tnum",
									children: value
								})]
							}, label))
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "customer_intelligence.ltv_distribution.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "LTV distribution",
							widgetKey: "customer_intelligence.ltv_distribution",
							loading: ltv.loading,
							error: ltv.error,
							onRetry: ltv.reload,
							children: /* @__PURE__ */ jsx(ResponsiveContainer, {
								width: "100%",
								height: 220,
								children: /* @__PURE__ */ jsxs(BarChart, {
									data: ltv.data?.rows ?? [],
									margin: {
										top: 4,
										right: 4,
										bottom: 0,
										left: 4
									},
									children: [
										/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
										/* @__PURE__ */ jsx(XAxis, {
											dataKey: "label",
											...AXIS_PROPS,
											interval: 0,
											angle: -25,
											textAnchor: "end",
											height: 54
										}),
										/* @__PURE__ */ jsx(YAxis, {
											...AXIS_PROPS,
											tickFormatter: axisNumber,
											width: 40
										}),
										/* @__PURE__ */ jsx(Tooltip, {
											content: /* @__PURE__ */ jsx(ChartTooltip, {
												format: "number",
												labelFormatter: (l) => l
											}),
											cursor: {
												fill: "var(--accent)",
												opacity: .4
											}
										}),
										/* @__PURE__ */ jsx(Bar, {
											dataKey: "customers",
											name: "Customers",
											fill: "var(--chart-1)",
											radius: [
												3,
												3,
												0,
												0
											],
											isAnimationActive: false
										})
									]
								})
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "customer_intelligence.purchase_interval.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Purchase interval",
							subtitle: "Average gap between orders",
							widgetKey: "customer_intelligence.purchase_interval",
							loading: interval.loading,
							error: interval.error,
							onRetry: interval.reload,
							caveat: interval.data?.caveat,
							children: /* @__PURE__ */ jsx(BarList, {
								format: "number",
								rows: (interval.data?.rows ?? []).map((row, index) => ({
									label: row.label,
									value: row.customers,
									color: CHART_COLORS[index % CHART_COLORS.length]
								})),
								emptyLabel: "No customer has two orders yet."
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "customer_intelligence.geo.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Customers by state",
							widgetKey: "customer_intelligence.geo",
							loading: geo.loading,
							error: geo.error,
							onRetry: geo.reload,
							exportDataset: "customers",
							children: /* @__PURE__ */ jsx(BarList, { rows: (geo.data?.rows ?? []).slice(0, 10).map((row) => ({
								label: row.state,
								value: row.revenue,
								secondary: `${formatNumber(row.customers)} cust`
							})) })
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "customer_intelligence.churn.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Churn risk",
						subtitle: "Repeat customers overdue on their own cadence",
						widgetKey: "customer_intelligence.churn",
						loading: churn.loading,
						error: churn.error,
						onRetry: churn.reload,
						verdict: churn.data?.verdict,
						caveat: churn.data?.caveat,
						exportDataset: "customers",
						empty: (churn.data?.count ?? 0) === 0,
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: churn.data?.rows ?? [],
							rowKey: (row) => row.id,
							columns: [
								customerColumns[0],
								{
									key: "risk",
									header: "Risk",
									align: "right",
									sortable: true,
									value: (r) => r.churn_risk_score ?? 0,
									render: (r) => /* @__PURE__ */ jsx(Badge, {
										variant: (r.churn_risk_score ?? 0) >= 80 ? "bad" : "warn",
										children: r.churn_risk_score
									})
								},
								customerColumns[5],
								customerColumns[6]
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "customer_intelligence.vip.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "VIP customers",
						subtitle: `${formatNumber(vip.data?.count ?? 0)} champions worth ${formatCompactCurrency(vip.data?.total_value ?? 0)}`,
						widgetKey: "customer_intelligence.vip",
						loading: vip.loading,
						error: vip.error,
						onRetry: vip.reload,
						exportDataset: "customers",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: vip.data?.rows ?? [],
							rowKey: (row) => row.id,
							columns: [
								customerColumns[0],
								customerColumns[3],
								customerColumns[5],
								customerColumns[6]
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "customer_intelligence.top_customers.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "All customers",
					subtitle: "Sorted by lifetime spend",
					widgetKey: "customer_intelligence.top_customers",
					loading: list.loading,
					error: list.error,
					onRetry: list.reload,
					exportDataset: "customers",
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						searchPlaceholder: "Search name, email, city…",
						rows: list.data?.data ?? [],
						rowKey: (row) => row.id,
						columns: customerColumns
					})
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "customer_intelligence.serial_returners.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Serial returners",
					subtitle: "Two or more returns",
					widgetKey: "customer_intelligence.serial_returners",
					loading: returners.loading,
					error: returners.error,
					onRetry: returners.reload,
					verdict: returners.data?.verdict,
					exportDataset: "customers",
					empty: (returners.data?.count ?? 0) === 0,
					children: /* @__PURE__ */ jsx(DataTable, {
						dense: true,
						rows: returners.data?.rows ?? [],
						rowKey: (row) => row.id,
						columns: [
							customerColumns[0],
							{
								key: "orders",
								header: "Orders",
								align: "right",
								sortable: true,
								value: (r) => r.orders_count,
								render: (r) => formatNumber(r.orders_count)
							},
							{
								key: "returns",
								header: "Returns",
								align: "right",
								sortable: true,
								value: (r) => r.returns_count,
								render: (r) => formatNumber(r.returns_count)
							},
							{
								key: "rate",
								header: "Return rate",
								align: "right",
								sortable: true,
								value: (r) => r.return_rate_pct,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium text-bad",
									children: formatPercent(r.return_rate_pct)
								})
							}
						]
					})
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "reviews.summary.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Rating summary",
						subtitle: `${formatNumber(reviews.data?.total ?? 0)} reviews`,
						widgetKey: "reviews.summary",
						loading: reviews.loading,
						error: reviews.error,
						onRetry: reviews.reload,
						caveat: reviews.data?.caveat,
						children: [
							/* @__PURE__ */ jsxs("div", {
								className: "flex items-baseline gap-2",
								children: [/* @__PURE__ */ jsx("p", {
									className: "text-3xl font-semibold tracking-tight tnum",
									children: (reviews.data?.avg_rating ?? 0).toFixed(2)
								}), /* @__PURE__ */ jsx("p", {
									className: "text-xs text-muted-foreground",
									children: "average rating"
								})]
							}),
							/* @__PURE__ */ jsx("div", {
								className: "space-y-1.5",
								children: (reviews.data?.distribution ?? []).map((row) => /* @__PURE__ */ jsxs("div", {
									className: "flex items-center gap-2",
									children: [
										/* @__PURE__ */ jsxs("span", {
											className: "w-6 text-[11px] tnum text-muted-foreground",
											children: [row.rating, "★"]
										}),
										/* @__PURE__ */ jsx("div", {
											className: "h-1.5 flex-1 overflow-hidden rounded-full bg-muted",
											children: /* @__PURE__ */ jsx("div", {
												className: "h-full rounded-full",
												style: {
													width: `${Math.max(row.pct, .5)}%`,
													background: row.rating >= 4 ? "var(--good)" : row.rating === 3 ? "var(--warn)" : "var(--bad)"
												}
											})
										}),
										/* @__PURE__ */ jsx("span", {
											className: "w-10 text-right text-[11px] tnum text-muted-foreground",
											children: formatNumber(row.count)
										})
									]
								}, row.rating))
							}),
							/* @__PURE__ */ jsxs("p", {
								className: "text-xs text-muted-foreground",
								children: [
									formatPercent(reviews.data?.positive_pct ?? 0),
									" positive · ",
									formatPercent(reviews.data?.with_photos_pct ?? 0),
									" with photos"
								]
							})
						]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "reviews.return_correlation.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "Review vs return",
						subtitle: "Well-rated products that still come back",
						widgetKey: "reviews.return_correlation",
						tooltip: "A high rating with a high return rate usually means the product is fine but the listing set the wrong expectation.",
						loading: correlation.loading,
						error: correlation.error,
						onRetry: correlation.reload,
						verdict: correlation.data?.verdict,
						caveat: correlation.data?.caveat,
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: correlation.data?.rows ?? [],
							rowKey: (row) => row.sku_code,
							columns: [
								{
									key: "sku",
									header: "SKU",
									value: (r) => r.sku_code,
									render: (r) => /* @__PURE__ */ jsxs("div", {
										className: "min-w-0",
										children: [/* @__PURE__ */ jsxs("p", {
											className: "flex items-center gap-1.5 truncate font-medium",
											children: [r.sku_code, r.expectation_gap && /* @__PURE__ */ jsx(Badge, {
												variant: "warn",
												children: "expectation gap"
											})]
										}), /* @__PURE__ */ jsx("p", {
											className: "truncate text-[11px] text-muted-foreground",
											children: r.name
										})]
									})
								},
								{
									key: "rating",
									header: "Rating",
									align: "right",
									sortable: true,
									value: (r) => r.avg_rating,
									render: (r) => `${r.avg_rating.toFixed(2)}★`
								},
								{
									key: "reviews",
									header: "Reviews",
									align: "right",
									sortable: true,
									value: (r) => r.review_count,
									render: (r) => formatNumber(r.review_count)
								},
								{
									key: "return",
									header: "Return %",
									align: "right",
									sortable: true,
									value: (r) => r.return_rate,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: r.return_rate >= 15 ? "font-medium text-bad" : "",
										children: formatPercent(r.return_rate)
									})
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "reviews.recent.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Recent reviews",
					widgetKey: "reviews.recent",
					loading: recentReviews.loading,
					error: recentReviews.error,
					onRetry: recentReviews.reload,
					children: /* @__PURE__ */ jsx("div", {
						className: "grid gap-2 md:grid-cols-2",
						children: (recentReviews.data?.data ?? []).map((review) => /* @__PURE__ */ jsxs(Card, {
							className: "p-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "flex items-start justify-between gap-2",
									children: [/* @__PURE__ */ jsxs("div", {
										className: "min-w-0",
										children: [/* @__PURE__ */ jsxs("p", {
											className: "flex items-center gap-1.5 text-xs font-medium",
											children: [/* @__PURE__ */ jsxs("span", {
												className: cn(review.rating >= 4 ? "text-good" : review.rating === 3 ? "text-warn" : "text-bad"),
												children: ["★".repeat(review.rating), "☆".repeat(5 - review.rating)]
											}), review.verified && /* @__PURE__ */ jsx(Badge, {
												variant: "muted",
												children: "verified"
											})]
										}), /* @__PURE__ */ jsx("p", {
											className: "mt-1 truncate text-xs font-semibold",
											children: review.title ?? "—"
										})]
									}), /* @__PURE__ */ jsx("span", {
										className: "shrink-0 text-[10px] text-muted-foreground",
										children: formatDate(review.reviewed_at)
									})]
								}),
								/* @__PURE__ */ jsx("p", {
									className: "mt-1 line-clamp-2 text-[11px] leading-snug text-muted-foreground",
									children: review.body
								}),
								/* @__PURE__ */ jsxs("p", {
									className: "mt-1.5 text-[10px] text-muted-foreground",
									children: [review.reviewer ?? "Anonymous", review.sku_code ? ` · ${review.sku_code}` : ""]
								})
							]
						}, review.id))
					})
				})
			})
		]
	});
}
//#endregion
export { Customers as default };

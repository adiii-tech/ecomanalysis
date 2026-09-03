import { t as cn } from "./utils-BVTyW6jK.js";
import { r as Button } from "./input-C0zE_xzz.js";
import { t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { t as EmptyState } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, f as formatNumber, i as formatCompactCurrency, o as formatCurrency, p as formatPercent, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as Skeleton } from "./skeleton-DUakt-29.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { a as GRID_PROPS, c as axisNumber, i as ChartTooltip, l as axisPercent, n as CHART_COLORS, o as axisCurrency, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { n as KpiStrip } from "./kpi-card-CF3GcgfJ.js";
import { t as ReturnsBasisToggle } from "./returns-basis-toggle-Hux1TInn.js";
import { t as DrilldownDrawer } from "./drilldown-drawer-B3VTT9x2.js";
import { t as SalesSummaryTable } from "./sales-summary-table-CJxS4gRN.js";
import { n as WaterfallLegend, t as WaterfallChart } from "./waterfall-chart-CcaRf_l7.js";
import { Head, Link } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useEffect, useState } from "react";
import { AlertTriangle, ArrowRight, ChevronRight, Play, RotateCcw, ShieldCheck } from "lucide-react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, ComposedChart, Line, ReferenceLine, ResponsiveContainer, Scatter, ScatterChart, Tooltip, XAxis, YAxis, ZAxis } from "recharts";
//#region resources/js/components/dashboard/health-flags.tsx
function HealthFlags({ flags, loading }) {
	if (loading) return /* @__PURE__ */ jsx("div", {
		className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-3",
		children: Array.from({ length: 3 }).map((_, index) => /* @__PURE__ */ jsxs(Card, {
			className: "p-4",
			children: [/* @__PURE__ */ jsx(Skeleton, { className: "h-4 w-40" }), /* @__PURE__ */ jsx(Skeleton, { className: "mt-2 h-3 w-full" })]
		}, index))
	});
	if (!flags || flags.length === 0) return /* @__PURE__ */ jsxs(Card, {
		className: "flex items-center gap-3 border-good/25 bg-good-soft/50 p-4",
		children: [/* @__PURE__ */ jsx(ShieldCheck, { className: "size-5 shrink-0 text-good" }), /* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
			className: "text-sm font-medium text-good",
			children: "Nothing needs your attention right now."
		}), /* @__PURE__ */ jsx("p", {
			className: "text-xs text-good/80",
			children: "No margin drops, RTO spikes, stockouts or SLA breaches in this window."
		})] })]
	});
	return /* @__PURE__ */ jsx("div", {
		className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-3",
		children: flags.map((flag) => /* @__PURE__ */ jsx(Link, {
			href: flag.link,
			children: /* @__PURE__ */ jsx(Card, {
				className: cn("group h-full p-4 transition-shadow hover:shadow-md", flag.severity === "critical" ? "border-bad/30 bg-bad-soft/40" : "border-warn/30 bg-warn-soft/40"),
				children: /* @__PURE__ */ jsxs("div", {
					className: "flex items-start gap-2.5",
					children: [
						/* @__PURE__ */ jsx(AlertTriangle, { className: cn("mt-0.5 size-4 shrink-0", flag.severity === "critical" ? "text-bad" : "text-warn") }),
						/* @__PURE__ */ jsxs("div", {
							className: "min-w-0 flex-1",
							children: [
								/* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold leading-snug",
									children: flag.title
								}),
								/* @__PURE__ */ jsx("p", {
									className: "mt-0.5 text-xs leading-snug text-muted-foreground",
									children: flag.body
								}),
								flag.impact_amount ? /* @__PURE__ */ jsxs("p", {
									className: cn("mt-1.5 text-xs font-semibold tnum", flag.severity === "critical" ? "text-bad" : "text-warn"),
									children: [formatCompactCurrency(flag.impact_amount), " at stake"]
								}) : null
							]
						}),
						/* @__PURE__ */ jsx(ChevronRight, { className: "size-4 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100" })
					]
				})
			})
		}, flag.key))
	});
}
//#endregion
//#region resources/js/components/dashboard/sales-journey.tsx
/**
* A guided walkthrough of the gross → net chain. It advances one step at a time
* so an owner who has never read a P&L can follow where the money went.
*/
function SalesJourney({ steps }) {
	const [current, setCurrent] = useState(0);
	const [playing, setPlaying] = useState(false);
	useEffect(() => {
		if (!playing) return;
		const timer = setTimeout(() => {
			setCurrent((value) => {
				if (value >= steps.length - 1) {
					setPlaying(false);
					return value;
				}
				return value + 1;
			});
		}, 2200);
		return () => clearTimeout(timer);
	}, [
		playing,
		current,
		steps.length
	]);
	if (steps.length === 0) return null;
	const step = steps[current];
	return /* @__PURE__ */ jsxs("div", {
		className: "space-y-4",
		children: [
			/* @__PURE__ */ jsx("div", {
				className: "flex items-center gap-1",
				children: steps.map((item, index) => /* @__PURE__ */ jsx("button", {
					type: "button",
					onClick: () => {
						setPlaying(false);
						setCurrent(index);
					},
					"aria-label": `Step ${item.step}: ${item.title}`,
					className: cn("h-1 flex-1 rounded-full transition-colors", index < current ? "bg-primary/40" : index === current ? "bg-primary" : "bg-muted")
				}, item.key))
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "min-h-[132px] rounded-xl border border-border bg-accent/30 p-4",
				children: [
					/* @__PURE__ */ jsxs("p", {
						className: "text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
						children: [
							"Step ",
							step.step,
							" of ",
							steps.length
						]
					}),
					/* @__PURE__ */ jsx("p", {
						className: "mt-1 text-sm font-semibold",
						children: step.title
					}),
					/* @__PURE__ */ jsx("p", {
						className: cn("mt-1.5 text-2xl font-semibold tracking-tight tnum", step.is_milestone ? "text-primary" : step.value < 0 ? "text-bad" : "text-foreground"),
						children: formatCurrency(step.value)
					}),
					/* @__PURE__ */ jsx("p", {
						className: "mt-1.5 text-xs leading-relaxed text-muted-foreground",
						children: step.narrative
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "flex items-center justify-between gap-2",
				children: [/* @__PURE__ */ jsxs(Button, {
					variant: "ghost",
					size: "sm",
					onClick: () => {
						setCurrent(0);
						setPlaying(false);
					},
					className: "gap-1.5 text-xs",
					disabled: current === 0 && !playing,
					children: [/* @__PURE__ */ jsx(RotateCcw, { className: "size-3" }), "Restart"]
				}), /* @__PURE__ */ jsxs("div", {
					className: "flex items-center gap-1.5",
					children: [/* @__PURE__ */ jsxs(Button, {
						variant: "outline",
						size: "sm",
						onClick: () => setPlaying((value) => !value),
						className: "gap-1.5 text-xs",
						children: [/* @__PURE__ */ jsx(Play, { className: "size-3" }), playing ? "Pause" : "Play"]
					}), /* @__PURE__ */ jsxs(Button, {
						size: "sm",
						onClick: () => setCurrent((value) => Math.min(value + 1, steps.length - 1)),
						disabled: current === steps.length - 1,
						className: "gap-1.5 text-xs",
						children: ["Next", /* @__PURE__ */ jsx(ArrowRight, { className: "size-3" })]
					})]
				})]
			})
		]
	});
}
//#endregion
//#region resources/js/pages/dashboard/index.tsx
var QUADRANT_COLOR = {
	scale: "var(--good)",
	grow: "var(--chart-2)",
	fix: "var(--warn)",
	restrict: "var(--bad)"
};
function Dashboard() {
	const [salesMetric, setSalesMetric] = useState("invoiced_sales");
	const [summaryTab, setSummaryTab] = useState("table");
	const [drilldown, setDrilldown] = useState(null);
	const kpis = useWidget("dashboard/kpis");
	const flags = useWidget("dashboard/health-flags");
	const channelDaily = useWidget("dashboard/sales-by-channel-daily", { metric: salesMetric });
	const summary = useWidget("dashboard/sales-summary");
	const waterfall = useWidget("dashboard/sales-summary");
	const journey = useWidget("dashboard/sales-journey");
	const marginTrend = useWidget("dashboard/revenue-margin-trend");
	const payment = useWidget("dashboard/payment-mode-economics");
	const channelMix = useWidget("dashboard/channel-mix");
	const topStates = useWidget("dashboard/top-states");
	const categories = useWidget("dashboard/top-categories");
	const rtoStates = useWidget("dashboard/top-rto-states");
	const matrix = useWidget("dashboard/state-action-matrix");
	const pareto = useWidget("dashboard/sku-pareto");
	const lossOrders = useWidget("dashboard/top-loss-orders");
	const recentOrders = useWidget("dashboard/recent-orders");
	const returnsByChannel = useWidget("dashboard/returns-by-channel");
	const returnReasons = useWidget("dashboard/top-return-reasons");
	const returnSkus = useWidget("dashboard/high-return-products");
	const pacing = useWidget("dashboard/revenue-pacing");
	const inventory = useWidget("dashboard/critical-inventory");
	const cohort = useWidget("dashboard/ltv-cohort");
	const orderColumns = [
		{
			key: "order",
			header: "Order",
			sortable: true,
			value: (r) => r.order_number,
			render: (r) => /* @__PURE__ */ jsx("span", {
				className: "font-medium",
				children: r.order_number
			})
		},
		{
			key: "placed",
			header: "Placed",
			sortable: true,
			value: (r) => r.placed_at,
			render: (r) => /* @__PURE__ */ jsx("span", {
				className: "text-muted-foreground",
				children: formatDateTime(r.placed_at)
			})
		},
		{
			key: "channel",
			header: "Channel",
			value: (r) => r.channel?.name ?? "",
			render: (r) => /* @__PURE__ */ jsxs("span", {
				className: "flex items-center gap-1.5",
				children: [/* @__PURE__ */ jsx("span", {
					className: "size-2 rounded-full",
					style: { background: r.channel?.color ?? "var(--muted-foreground)" }
				}), r.channel?.name ?? "—"]
			})
		},
		{
			key: "state",
			header: "State",
			value: (r) => r.shipping_state,
			render: (r) => r.shipping_state ?? "—"
		},
		{
			key: "payment",
			header: "Payment",
			value: (r) => r.payment_mode,
			render: (r) => /* @__PURE__ */ jsx(Badge, {
				variant: r.payment_mode === "cod" ? "warn" : "muted",
				children: r.payment_mode === "cod" ? "COD" : "Prepaid"
			})
		},
		{
			key: "status",
			header: "Status",
			value: (r) => r.status_label,
			render: (r) => /* @__PURE__ */ jsx(Badge, {
				variant: r.is_rto ? "bad" : "outline",
				children: r.status_label
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
			key: "cogs",
			header: "COGS",
			align: "right",
			sortable: true,
			value: (r) => r.cogs_amount,
			render: (r) => formatCurrency(r.cogs_amount)
		},
		{
			key: "fees",
			header: "Fees",
			align: "right",
			sortable: true,
			value: (r) => r.fees_amount,
			render: (r) => formatCurrency(r.fees_amount)
		},
		{
			key: "margin",
			header: "Margin",
			align: "right",
			sortable: true,
			value: (r) => r.contribution_margin,
			render: (r) => /* @__PURE__ */ jsxs("span", {
				className: r.contribution_margin < 0 ? "font-medium text-bad" : "text-foreground",
				children: [formatCurrency(r.contribution_margin), /* @__PURE__ */ jsx("span", {
					className: "ml-1 text-[10px] text-muted-foreground",
					children: r.margin_pct === null ? "—" : formatPercent(r.margin_pct)
				})]
			})
		}
	];
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Command Centre",
		description: "Am I actually making money?",
		filterExtras: /* @__PURE__ */ jsx(ReturnsBasisToggle, {}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Command Centre" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "dashboard.kpi_strip.view",
				children: /* @__PURE__ */ jsx(KpiStrip, {
					metrics: kpis.data,
					loading: kpis.loading,
					columns: 6
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "dashboard.health_flags.view",
				children: /* @__PURE__ */ jsx(HealthFlags, {
					flags: flags.data?.rows ?? null,
					loading: flags.loading
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.sales_by_channel.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Sales by channel",
						subtitle: "Daily, stacked by channel",
						widgetKey: "dashboard.sales_by_channel",
						tooltip: "Invoiced sales are what you billed — after discounts and cancellations, before returns and RTO.",
						loading: channelDaily.loading,
						error: channelDaily.error,
						onRetry: channelDaily.reload,
						empty: (channelDaily.data?.channels.length ?? 0) === 0,
						insightPayload: channelDaily.data,
						tabs: /* @__PURE__ */ jsx(Tabs, {
							value: salesMetric,
							onValueChange: (value) => setSalesMetric(value),
							children: /* @__PURE__ */ jsxs(TabsList, { children: [
								/* @__PURE__ */ jsx(TabsTrigger, {
									value: "invoiced_sales",
									children: "Invoiced"
								}),
								/* @__PURE__ */ jsx(TabsTrigger, {
									value: "net_sales",
									children: "Net"
								}),
								/* @__PURE__ */ jsx(TabsTrigger, {
									value: "orders_count",
									children: "Orders"
								})
							] })
						}),
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 260,
							children: /* @__PURE__ */ jsxs(BarChart, {
								data: channelDaily.data?.series ?? [],
								margin: {
									top: 4,
									right: 4,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
									/* @__PURE__ */ jsx(XAxis, {
										dataKey: "date",
										...AXIS_PROPS,
										tickFormatter: axisDate,
										minTickGap: 26
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: salesMetric === "orders_count" ? axisNumber : axisCurrency,
										width: 54
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, { format: salesMetric === "orders_count" ? "number" : "currency" }),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									(channelDaily.data?.channels ?? []).map((channel, index) => /* @__PURE__ */ jsx(Bar, {
										dataKey: channel.code,
										name: channel.name,
										stackId: "channel",
										fill: channel.color ?? CHART_COLORS[index % CHART_COLORS.length],
										radius: index === (channelDaily.data?.channels.length ?? 1) - 1 ? [
											3,
											3,
											0,
											0
										] : 0,
										isAnimationActive: false
									}, channel.code))
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: (channelDaily.data?.channels ?? []).map((channel, index) => ({
							label: channel.name,
							color: channel.color ?? CHART_COLORS[index % CHART_COLORS.length]
						})) })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.revenue_pacing.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Revenue pacing",
						subtitle: "Month to date vs target",
						widgetKey: "dashboard.revenue_pacing",
						loading: pacing.loading,
						error: pacing.error,
						onRetry: pacing.reload,
						verdict: pacing.data?.verdict,
						caveat: pacing.data?.caveat,
						children: [/* @__PURE__ */ jsxs("div", {
							className: "grid grid-cols-3 gap-2 text-center",
							children: [
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
									className: "text-[10px] uppercase tracking-wide text-muted-foreground",
									children: "Actual"
								}), /* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold tnum",
									children: formatCompactCurrency(pacing.data?.actual ?? 0)
								})] }),
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
									className: "text-[10px] uppercase tracking-wide text-muted-foreground",
									children: "Projected"
								}), /* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold tnum text-primary",
									children: formatCompactCurrency(pacing.data?.projected ?? 0)
								})] }),
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
									className: "text-[10px] uppercase tracking-wide text-muted-foreground",
									children: "Target"
								}), /* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold tnum text-muted-foreground",
									children: formatCompactCurrency(pacing.data?.target ?? 0)
								})] })
							]
						}), /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 168,
							children: /* @__PURE__ */ jsxs(AreaChart, {
								data: pacing.data?.series ?? [],
								margin: {
									top: 8,
									right: 4,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
										id: "pacing-fill",
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
										minTickGap: 30
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 50
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, {}) }),
									/* @__PURE__ */ jsx(Area, {
										type: "monotone",
										dataKey: "actual",
										name: "Actual",
										stroke: "var(--chart-1)",
										strokeWidth: 2,
										fill: "url(#pacing-fill)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										type: "monotone",
										dataKey: "target",
										name: "Target",
										stroke: "var(--muted-foreground)",
										strokeDasharray: "4 4",
										strokeWidth: 1.5,
										dot: false,
										isAnimationActive: false
									})
								]
							})
						})]
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.sales_summary.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "Sales summary",
						subtitle: "Gross → net, line by line",
						widgetKey: "dashboard.sales_summary",
						tooltip: "Every deduction between what customers agreed to pay and what you actually keep.",
						loading: summary.loading,
						error: summary.error,
						onRetry: summary.reload,
						verdict: summary.data?.verdict,
						insightPayload: pacing.data,
						tabs: /* @__PURE__ */ jsx(Tabs, {
							value: summaryTab,
							onValueChange: (value) => setSummaryTab(value),
							children: /* @__PURE__ */ jsxs(TabsList, { children: [/* @__PURE__ */ jsx(TabsTrigger, {
								value: "table",
								children: "Table"
							}), /* @__PURE__ */ jsx(TabsTrigger, {
								value: "waterfall",
								children: "Waterfall"
							})] })
						}),
						children: summaryTab === "table" ? /* @__PURE__ */ jsx(SalesSummaryTable, { rows: summary.data?.rows ?? [] }) : /* @__PURE__ */ jsx(WaterfallSection, {})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.sales_journey.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Sales journey",
						subtitle: "Where the money actually went",
						widgetKey: "dashboard.sales_journey",
						loading: journey.loading,
						error: journey.error,
						onRetry: journey.reload,
						empty: (journey.data?.length ?? 0) === 0,
						children: /* @__PURE__ */ jsx(SalesJourney, { steps: journey.data ?? [] })
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.payment_mode_economics.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "COD vs prepaid economics",
						subtitle: "The number most Indian D2C brands get wrong",
						widgetKey: "dashboard.payment_mode_economics",
						tooltip: "COD looks like revenue until you subtract RTO. This is the same money, resolved down to margin.",
						loading: payment.loading,
						error: payment.error,
						onRetry: payment.reload,
						verdict: payment.data?.verdict,
						insightPayload: journey.data,
						children: /* @__PURE__ */ jsx("div", {
							className: "grid grid-cols-2 gap-3",
							children: (payment.data?.rows ?? []).map((row) => /* @__PURE__ */ jsxs("div", {
								className: "rounded-xl border border-border p-3",
								children: [/* @__PURE__ */ jsxs("div", {
									className: "flex items-center justify-between",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-xs font-semibold",
										children: row.label
									}), /* @__PURE__ */ jsxs(Badge, {
										variant: row.mode === "cod" ? "warn" : "default",
										children: [formatPercent(row.share_of_orders, 0), " of orders"]
									})]
								}), /* @__PURE__ */ jsxs("dl", {
									className: "mt-2 space-y-1 text-xs",
									children: [
										[
											["Orders", formatNumber(row.orders)],
											["Net sales", formatCompactCurrency(row.net_sales)],
											["COGS", formatCompactCurrency(row.cogs)],
											["Fees", formatCompactCurrency(row.fees)],
											["Logistics", formatCompactCurrency(row.logistics)],
											["AOV", formatCompactCurrency(row.aov)]
										].map(([label, value]) => /* @__PURE__ */ jsxs("div", {
											className: "flex justify-between",
											children: [/* @__PURE__ */ jsx("dt", {
												className: "text-muted-foreground",
												children: label
											}), /* @__PURE__ */ jsx("dd", {
												className: "tnum",
												children: value
											})]
										}, label)),
										/* @__PURE__ */ jsxs("div", {
											className: "flex justify-between border-t border-border pt-1 font-medium",
											children: [/* @__PURE__ */ jsx("dt", { children: "Net margin" }), /* @__PURE__ */ jsxs("dd", {
												className: cn("tnum", row.net_margin < 0 ? "text-bad" : "text-good"),
												children: [
													formatCompactCurrency(row.net_margin),
													" · ",
													formatPercent(row.net_margin_pct)
												]
											})]
										}),
										/* @__PURE__ */ jsxs("div", {
											className: "flex justify-between",
											children: [/* @__PURE__ */ jsx("dt", {
												className: "text-muted-foreground",
												children: "RTO"
											}), /* @__PURE__ */ jsxs("dd", {
												className: cn("tnum", row.rto_pct > 15 ? "text-bad" : "text-muted-foreground"),
												children: [
													row.rto_orders,
													" · ",
													formatPercent(row.rto_pct)
												]
											})]
										})
									]
								})]
							}, row.mode))
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.net_sales_vs_margin.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Net sales vs margin",
						subtitle: "Revenue and the profit inside it",
						widgetKey: "dashboard.net_sales_vs_margin",
						loading: marginTrend.loading,
						error: marginTrend.error,
						onRetry: marginTrend.reload,
						insightPayload: marginTrend.data,
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 260,
							children: /* @__PURE__ */ jsxs(ComposedChart, {
								data: marginTrend.data ?? [],
								margin: {
									top: 4,
									right: 8,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
									/* @__PURE__ */ jsx(XAxis, {
										dataKey: "date",
										...AXIS_PROPS,
										tickFormatter: axisDate,
										minTickGap: 26
									}),
									/* @__PURE__ */ jsx(YAxis, {
										yAxisId: "left",
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 54
									}),
									/* @__PURE__ */ jsx(YAxis, {
										yAxisId: "right",
										orientation: "right",
										...AXIS_PROPS,
										tickFormatter: axisPercent,
										width: 40
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, { formats: { margin_pct: "percent" } }) }),
									/* @__PURE__ */ jsx(Area, {
										yAxisId: "left",
										type: "monotone",
										dataKey: "net_sales",
										name: "Net sales",
										stroke: "var(--chart-1)",
										fill: "var(--chart-1)",
										fillOpacity: .12,
										strokeWidth: 2,
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										yAxisId: "right",
										type: "monotone",
										dataKey: "margin_pct",
										name: "Margin %",
										stroke: "var(--chart-3)",
										strokeWidth: 2,
										dot: false,
										isAnimationActive: false
									})
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [{
							label: "Net sales",
							color: "var(--chart-1)"
						}, {
							label: "Contribution margin %",
							color: "var(--chart-3)"
						}] })]
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "dashboard.channel_mix.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Channel mix",
							subtitle: "Share of net sales and the margin behind it",
							widgetKey: "dashboard.channel_mix",
							loading: channelMix.loading,
							error: channelMix.error,
							onRetry: channelMix.reload,
							verdict: channelMix.data?.verdict,
							exportDataset: "channel_scorecard",
							children: /* @__PURE__ */ jsx("div", {
								className: "space-y-2.5",
								children: (channelMix.data?.rows ?? []).map((row) => /* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsxs("div", {
										className: "flex items-baseline justify-between gap-2 text-xs",
										children: [/* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-1.5 truncate font-medium",
											children: [/* @__PURE__ */ jsx("span", {
												className: "size-2 shrink-0 rounded-full",
												style: { background: row.color ?? "var(--chart-1)" }
											}), row.name]
										}), /* @__PURE__ */ jsxs("span", {
											className: "shrink-0 tnum text-muted-foreground",
											children: [
												formatCompactCurrency(row.net_sales),
												" ·",
												" ",
												/* @__PURE__ */ jsx("span", {
													className: row.margin_pct < 0 ? "text-bad" : row.margin_pct > 30 ? "text-good" : "",
													children: formatPercent(row.margin_pct)
												})
											]
										})]
									}), /* @__PURE__ */ jsx("div", {
										className: "h-1.5 overflow-hidden rounded-full bg-muted",
										children: /* @__PURE__ */ jsx("div", {
											className: "h-full rounded-full transition-all",
											style: {
												width: `${Math.max(row.share_pct, 1)}%`,
												background: row.color ?? "var(--chart-1)"
											}
										})
									})]
								}, row.code))
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "dashboard.top_states.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Top states",
							subtitle: "By net sales",
							widgetKey: "dashboard.top_states",
							loading: topStates.loading,
							error: topStates.error,
							onRetry: topStates.reload,
							exportDataset: "geo_states",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: topStates.data?.rows ?? [],
								rowKey: (row) => row.state,
								columns: [
									{
										key: "state",
										header: "State",
										value: (r) => r.state,
										render: (r) => r.state
									},
									{
										key: "orders",
										header: "Orders",
										align: "right",
										sortable: true,
										value: (r) => r.orders,
										render: (r) => formatNumber(r.orders)
									},
									{
										key: "sales",
										header: "Net sales",
										align: "right",
										sortable: true,
										value: (r) => r.net_sales,
										render: (r) => formatCompactCurrency(r.net_sales)
									},
									{
										key: "share",
										header: "Share",
										align: "right",
										value: (r) => r.share_pct,
										render: (r) => formatPercent(r.share_pct)
									}
								]
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "dashboard.top_categories.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Top categories",
							subtitle: "Share of net sales",
							widgetKey: "dashboard.top_categories",
							loading: categories.loading,
							error: categories.error,
							onRetry: categories.reload,
							exportDataset: "top_skus",
							children: /* @__PURE__ */ jsx("div", {
								className: "space-y-2.5",
								children: (categories.data?.rows ?? []).map((row, index) => /* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsxs("div", {
										className: "flex items-baseline justify-between gap-2 text-xs",
										children: [/* @__PURE__ */ jsx("span", {
											className: "truncate font-medium",
											children: row.category
										}), /* @__PURE__ */ jsxs("span", {
											className: "shrink-0 tnum text-muted-foreground",
											children: [
												formatCompactCurrency(row.net_sales),
												" · ",
												formatPercent(row.share_pct, 0)
											]
										})]
									}), /* @__PURE__ */ jsx("div", {
										className: "h-1.5 overflow-hidden rounded-full bg-muted",
										children: /* @__PURE__ */ jsx("div", {
											className: "h-full rounded-full",
											style: {
												width: `${Math.max(row.share_pct, 1)}%`,
												background: CHART_COLORS[index % CHART_COLORS.length]
											}
										})
									})]
								}, row.category))
							})
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.state_action_matrix.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "State action matrix",
						subtitle: "Sales volume against RTO rate, with a recommended move per state",
						widgetKey: "dashboard.state_action_matrix",
						tooltip: "Top-left is where to scale; bottom-right is where to stop offering COD.",
						loading: matrix.loading,
						error: matrix.error,
						onRetry: matrix.reload,
						exportDataset: "state_roi",
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 280,
							children: /* @__PURE__ */ jsxs(ScatterChart, {
								margin: {
									top: 8,
									right: 12,
									bottom: 20,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx(CartesianGrid, {
										...GRID_PROPS,
										vertical: true
									}),
									/* @__PURE__ */ jsx(XAxis, {
										type: "number",
										dataKey: "net_sales",
										name: "Net sales",
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										label: {
											value: "Net sales",
											position: "insideBottom",
											offset: -12,
											fontSize: 11,
											fill: "var(--muted-foreground)"
										}
									}),
									/* @__PURE__ */ jsx(YAxis, {
										type: "number",
										dataKey: "rto_pct",
										name: "RTO %",
										...AXIS_PROPS,
										tickFormatter: axisPercent,
										width: 44,
										label: {
											value: "RTO %",
											angle: -90,
											position: "insideLeft",
											fontSize: 11,
											fill: "var(--muted-foreground)"
										}
									}),
									/* @__PURE__ */ jsx(ZAxis, {
										type: "number",
										dataKey: "orders",
										range: [40, 380]
									}),
									/* @__PURE__ */ jsx(ReferenceLine, {
										y: matrix.data?.rto_threshold ?? 15,
										stroke: "var(--bad)",
										strokeDasharray: "4 4"
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										cursor: { strokeDasharray: "3 3" },
										content: ({ active, payload }) => {
											if (!active || !payload?.length) return null;
											const row = payload[0].payload;
											return /* @__PURE__ */ jsxs("div", {
												className: "max-w-[240px] rounded-lg border border-border bg-popover px-2.5 py-2 text-xs shadow-lg",
												children: [
													/* @__PURE__ */ jsx("p", {
														className: "font-semibold",
														children: row.state
													}),
													/* @__PURE__ */ jsxs("p", {
														className: "mt-0.5 tnum text-muted-foreground",
														children: [
															formatNumber(row.orders),
															" orders · ",
															formatCompactCurrency(row.net_sales),
															" · ",
															formatPercent(row.rto_pct),
															" RTO"
														]
													}),
													/* @__PURE__ */ jsx("p", {
														className: "mt-1 leading-snug",
														children: row.action
													})
												]
											});
										}
									}),
									/* @__PURE__ */ jsx(Scatter, {
										data: matrix.data?.rows ?? [],
										isAnimationActive: false,
										children: (matrix.data?.rows ?? []).map((row) => /* @__PURE__ */ jsx(Cell, {
											fill: QUADRANT_COLOR[row.quadrant],
											fillOpacity: .72
										}, row.state))
									})
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [
							{
								label: "Scale",
								color: QUADRANT_COLOR.scale
							},
							{
								label: "Grow",
								color: QUADRANT_COLOR.grow
							},
							{
								label: "Fix",
								color: QUADRANT_COLOR.fix
							},
							{
								label: "Restrict",
								color: QUADRANT_COLOR.restrict
							}
						] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.top_rto_states.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Highest RTO states",
						subtitle: "Flagged against your threshold",
						widgetKey: "dashboard.top_rto_states",
						loading: rtoStates.loading,
						error: rtoStates.error,
						onRetry: rtoStates.reload,
						verdict: rtoStates.data?.verdict,
						caveat: rtoStates.data?.caveat,
						exportDataset: "rto_by_state",
						children: /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 230,
							children: /* @__PURE__ */ jsxs(BarChart, {
								data: (rtoStates.data?.rows ?? []).slice(0, 10),
								layout: "vertical",
								margin: {
									top: 0,
									right: 12,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx(CartesianGrid, {
										...GRID_PROPS,
										horizontal: false,
										vertical: true
									}),
									/* @__PURE__ */ jsx(XAxis, {
										type: "number",
										...AXIS_PROPS,
										tickFormatter: axisPercent
									}),
									/* @__PURE__ */ jsx(YAxis, {
										type: "category",
										dataKey: "state",
										...AXIS_PROPS,
										width: 104,
										interval: 0
									}),
									/* @__PURE__ */ jsx(ReferenceLine, {
										x: rtoStates.data?.threshold ?? 15,
										stroke: "var(--bad)",
										strokeDasharray: "4 4"
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, {
											format: "percent",
											labelFormatter: (l) => l
										}),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "rto_pct",
										name: "RTO %",
										radius: [
											0,
											3,
											3,
											0
										],
										isAnimationActive: false,
										children: (rtoStates.data?.rows ?? []).slice(0, 10).map((row) => /* @__PURE__ */ jsx(Cell, { fill: row.rto_pct >= (rtoStates.data?.threshold ?? 15) ? "var(--bad)" : "var(--chart-4)" }, row.state))
									})
								]
							})
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.sku_pareto.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "SKU Pareto",
						subtitle: "Which SKUs actually make the profit",
						widgetKey: "dashboard.sku_pareto",
						tooltip: "SKUs ranked by contribution margin, with the running cumulative share of total profit.",
						loading: pareto.loading,
						error: pareto.error,
						onRetry: pareto.reload,
						verdict: pareto.data?.verdict,
						exportDataset: "top_skus",
						empty: (pareto.data?.rows.length ?? 0) === 0,
						children: /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 250,
							children: /* @__PURE__ */ jsxs(ComposedChart, {
								data: (pareto.data?.rows ?? []).slice(0, 25),
								margin: {
									top: 4,
									right: 8,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
									/* @__PURE__ */ jsx(XAxis, {
										dataKey: "sku_code",
										...AXIS_PROPS,
										interval: 0,
										angle: -40,
										textAnchor: "end",
										height: 62
									}),
									/* @__PURE__ */ jsx(YAxis, {
										yAxisId: "left",
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 54
									}),
									/* @__PURE__ */ jsx(YAxis, {
										yAxisId: "right",
										orientation: "right",
										...AXIS_PROPS,
										tickFormatter: axisPercent,
										width: 40,
										domain: [0, 100]
									}),
									/* @__PURE__ */ jsx(ReferenceLine, {
										yAxisId: "right",
										y: 80,
										stroke: "var(--warn)",
										strokeDasharray: "4 4"
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, {
											formats: { cumulative_pct: "percent" },
											labelFormatter: (l) => l
										}),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									/* @__PURE__ */ jsx(Bar, {
										yAxisId: "left",
										dataKey: "margin",
										name: "Margin",
										fill: "var(--chart-1)",
										radius: [
											3,
											3,
											0,
											0
										],
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										yAxisId: "right",
										type: "monotone",
										dataKey: "cumulative_pct",
										name: "Cumulative %",
										stroke: "var(--chart-4)",
										strokeWidth: 2,
										dot: false,
										isAnimationActive: false
									})
								]
							})
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.ltv_cohort.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Repeat curve",
						subtitle: "Average retention by month since first order",
						widgetKey: "dashboard.ltv_cohort",
						loading: cohort.loading,
						error: cohort.error,
						onRetry: cohort.reload,
						verdict: cohort.data?.verdict,
						children: /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 200,
							children: /* @__PURE__ */ jsxs(AreaChart, {
								data: cohort.data?.series ?? [],
								margin: {
									top: 4,
									right: 4,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
										id: "cohort-fill",
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
										dataKey: "month_index",
										...AXIS_PROPS,
										tickFormatter: (v) => `M${v}`
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisPercent,
										width: 40
									}),
									/* @__PURE__ */ jsx(ReferenceLine, {
										y: cohort.data?.target_repeat_rate ?? 25,
										stroke: "var(--muted-foreground)",
										strokeDasharray: "4 4"
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, {
										format: "percent",
										labelFormatter: (l) => `Month ${l}`
									}) }),
									/* @__PURE__ */ jsx(Area, {
										type: "monotone",
										dataKey: "avg_retention_pct",
										name: "Retention",
										stroke: "var(--chart-2)",
										strokeWidth: 2,
										fill: "url(#cohort-fill)",
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
						permission: "dashboard.returns_by_channel.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Returns by channel",
							subtitle: "Returns and RTO as a share of orders",
							widgetKey: "dashboard.returns_by_channel",
							loading: returnsByChannel.loading,
							error: returnsByChannel.error,
							onRetry: returnsByChannel.reload,
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: returnsByChannel.data?.rows ?? [],
								rowKey: (row) => row.name,
								columns: [
									{
										key: "name",
										header: "Channel",
										value: (r) => r.name,
										render: (r) => /* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-1.5",
											children: [/* @__PURE__ */ jsx("span", {
												className: "size-2 rounded-full",
												style: { background: r.color ?? "var(--chart-1)" }
											}), r.name]
										})
									},
									{
										key: "orders",
										header: "Orders",
										align: "right",
										value: (r) => r.orders,
										render: (r) => formatNumber(r.orders)
									},
									{
										key: "returns",
										header: "Returns",
										align: "right",
										value: (r) => r.returns_total,
										render: (r) => formatNumber(r.returns_total)
									},
									{
										key: "pct",
										header: "Return %",
										align: "right",
										sortable: true,
										value: (r) => r.return_pct,
										render: (r) => /* @__PURE__ */ jsx("span", {
											className: r.return_pct > 10 ? "font-medium text-bad" : "",
											children: formatPercent(r.return_pct)
										})
									}
								]
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "dashboard.top_return_reasons.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Top return reasons",
							subtitle: "Why product comes back",
							widgetKey: "dashboard.top_return_reasons",
							loading: returnReasons.loading,
							error: returnReasons.error,
							onRetry: returnReasons.reload,
							exportDataset: "returns_register",
							children: /* @__PURE__ */ jsx(ResponsiveContainer, {
								width: "100%",
								height: 210,
								children: /* @__PURE__ */ jsxs(BarChart, {
									data: (returnReasons.data?.rows ?? []).slice(0, 7),
									layout: "vertical",
									margin: {
										top: 0,
										right: 12,
										bottom: 0,
										left: 4
									},
									children: [
										/* @__PURE__ */ jsx(CartesianGrid, {
											...GRID_PROPS,
											horizontal: false,
											vertical: true
										}),
										/* @__PURE__ */ jsx(XAxis, {
											type: "number",
											...AXIS_PROPS,
											tickFormatter: axisNumber
										}),
										/* @__PURE__ */ jsx(YAxis, {
											type: "category",
											dataKey: "label",
											...AXIS_PROPS,
											width: 118
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
											dataKey: "count",
											name: "Returns",
											fill: "var(--chart-5)",
											radius: [
												0,
												3,
												3,
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
						permission: "dashboard.top_return_skus.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Most returned SKUs",
							subtitle: "Where the returns concentrate",
							widgetKey: "dashboard.top_return_skus",
							loading: returnSkus.loading,
							error: returnSkus.error,
							onRetry: returnSkus.reload,
							exportDataset: "returns_register",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: returnSkus.data?.rows ?? [],
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
										key: "count",
										header: "Returns",
										align: "right",
										sortable: true,
										value: (r) => r.returns_count,
										render: (r) => formatNumber(r.returns_count)
									},
									{
										key: "value",
										header: "Refunded",
										align: "right",
										sortable: true,
										value: (r) => r.refund_amount,
										render: (r) => formatCompactCurrency(r.refund_amount)
									}
								]
							})
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.loss_orders.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Biggest loss orders",
						subtitle: "Orders where you paid to deliver",
						widgetKey: "dashboard.loss_orders",
						loading: lossOrders.loading,
						error: lossOrders.error,
						onRetry: lossOrders.reload,
						verdict: lossOrders.data?.verdict,
						onDetail: () => setDrilldown("loss_orders"),
						exportDataset: "loss_orders",
						empty: (lossOrders.data?.count ?? 0) === 0,
						emptyState: /* @__PURE__ */ jsx(EmptyState, {
							kind: "celebrate",
							compact: true,
							title: "No loss-making orders in this view 🎉",
							description: "Every order in this window covered its own costs."
						}),
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: lossOrders.data?.rows ?? [],
							rowKey: (row) => row.id,
							columns: orderColumns.filter((c) => [
								"order",
								"channel",
								"payment",
								"net",
								"margin"
							].includes(c.key))
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "dashboard.critical_inventory.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Critical inventory",
						subtitle: "Bestsellers about to run out",
						widgetKey: "dashboard.critical_inventory",
						loading: inventory.loading,
						error: inventory.error,
						onRetry: inventory.reload,
						verdict: inventory.data?.verdict,
						exportDataset: "inventory_health",
						empty: (inventory.data?.count ?? 0) === 0,
						emptyState: /* @__PURE__ */ jsx(EmptyState, {
							kind: "celebrate",
							compact: true,
							title: "Nothing is about to stock out",
							description: "Every selling SKU has cover beyond your threshold."
						}),
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: inventory.data?.rows ?? [],
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
									key: "stock",
									header: "Stock",
									align: "right",
									sortable: true,
									value: (r) => r.stock,
									render: (r) => formatNumber(r.stock)
								},
								{
									key: "cover",
									header: "Days left",
									align: "right",
									sortable: true,
									value: (r) => r.days_of_cover,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: r.days_of_cover < 4 ? "font-medium text-bad" : "text-warn",
										children: [r.days_of_cover.toFixed(1), "d"]
									})
								},
								{
									key: "revenue",
									header: "Rev / 30d",
									align: "right",
									sortable: true,
									value: (r) => r.monthly_revenue,
									render: (r) => formatCompactCurrency(r.monthly_revenue)
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "dashboard.recent_orders.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Recent orders",
					subtitle: "Latest activity across every channel",
					widgetKey: "dashboard.recent_orders",
					loading: recentOrders.loading,
					error: recentOrders.error,
					onRetry: recentOrders.reload,
					onDetail: () => setDrilldown("recent_orders"),
					exportDataset: "orders",
					children: /* @__PURE__ */ jsx(DataTable, {
						rows: recentOrders.data?.rows ?? [],
						rowKey: (row) => row.id,
						columns: orderColumns
					})
				})
			}),
			/* @__PURE__ */ jsx(DrilldownDrawer, {
				open: drilldown !== null,
				onOpenChange: (open) => setDrilldown(open ? drilldown : null),
				title: drilldown === "loss_orders" ? "Loss-making orders" : "Recent orders",
				description: drilldown === "loss_orders" ? "Order-level P&L for every order that lost money in this window." : "Every order in the current window, newest first.",
				columns: orderColumns,
				rows: (drilldown === "loss_orders" ? lossOrders.data?.rows : recentOrders.data?.rows) ?? [],
				loading: drilldown === "loss_orders" ? lossOrders.loading : recentOrders.loading,
				rowKey: (row) => row.id,
				verdict: drilldown === "loss_orders" ? lossOrders.data?.verdict : null,
				exportDataset: drilldown === "loss_orders" ? "loss_orders" : "orders"
			})
		]
	});
	function WaterfallSection() {
		if (!waterfall.data?.rows) return /* @__PURE__ */ jsx(Skeleton, { className: "h-[300px] w-full" });
		const steps = buildWaterfall(summary.data?.rows ?? []);
		return /* @__PURE__ */ jsxs("div", {
			className: "space-y-2",
			children: [/* @__PURE__ */ jsx(WaterfallChart, { steps }), /* @__PURE__ */ jsx(WaterfallLegend, { steps })]
		});
	}
}
/** Derives the floating-bar geometry from the summary rows already on screen. */
function buildWaterfall(rows) {
	const relevant = rows.filter((row) => ![
		"invoiced_sales",
		"net_sales",
		"shipping"
	].includes(row.key));
	let running = 0;
	return [...relevant.map((row) => {
		const start = running;
		running += row.amount;
		return {
			key: row.key,
			label: row.label,
			delta: row.amount,
			start,
			end: running
		};
	}), {
		key: "result",
		label: "Net after leaks",
		delta: running,
		start: 0,
		end: running,
		is_total: true
	}];
}
//#endregion
export { Dashboard as default };

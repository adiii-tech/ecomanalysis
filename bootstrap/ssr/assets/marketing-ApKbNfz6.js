import { t as cn } from "./utils-BVTyW6jK.js";
import { t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { f as formatNumber, i as formatCompactCurrency, m as formatRatio, o as formatCurrency, p as formatPercent, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { r as VerdictBadge, t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { a as GRID_PROPS, i as ChartTooltip, n as CHART_COLORS, o as axisCurrency, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { n as KpiStrip } from "./kpi-card-ByDkhHxD.js";
import { t as Funnel } from "./funnel-CayeUjz3.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { Radio, TrendingDown, TrendingUp } from "lucide-react";
import { Bar, CartesianGrid, ComposedChart, Line, ReferenceDot, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/pages/marketing/index.tsx
var TONE_CLASS = {
	good: "border-good/25 bg-good-soft/40",
	warn: "border-warn/25 bg-warn-soft/40",
	bad: "border-bad/25 bg-bad-soft/40",
	neutral: "border-border bg-accent/30"
};
function Marketing() {
	const kpis = useWidget("marketing/kpis");
	const campaigns = useWidget("marketing/campaigns");
	const insights = useWidget("marketing/insights");
	const trend = useWidget("marketing/trend");
	const funnel = useWidget("marketing/conversion-funnel");
	const carts = useWidget("marketing/abandoned-carts");
	const realtime = useWidget("marketing/realtime-active-users");
	const mer = useWidget("marketing/mer");
	const attribution = useWidget("marketing/attribution-gap");
	const channels = useWidget("marketing/channel-performance");
	const fatigue = useWidget("marketing/creative-fatigue");
	const objectives = useWidget("marketing/spend-by-objective");
	const placements = useWidget("marketing/placements");
	const utm = useWidget("marketing/utm-analysis");
	const pacing = useWidget("marketing/budget-pacing");
	const persona = useWidget("marketing/buyer-persona");
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Marketing",
		description: "What you spent, what it returned, and where to move the budget",
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Marketing" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketing.kpi_strip.view",
				children: /* @__PURE__ */ jsx(KpiStrip, {
					metrics: kpis.data,
					loading: kpis.loading,
					columns: 4
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketing.insights_cards.view",
				children: /* @__PURE__ */ jsx("div", {
					className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-4",
					children: (insights.data?.cards ?? []).map((card) => /* @__PURE__ */ jsxs(Card, {
						className: cn("p-4", TONE_CLASS[card.tone] ?? TONE_CLASS.neutral),
						children: [
							/* @__PURE__ */ jsx("p", {
								className: "text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
								children: card.title
							}),
							/* @__PURE__ */ jsx("p", {
								className: "mt-1 truncate text-sm font-semibold",
								children: card.headline
							}),
							/* @__PURE__ */ jsx("p", {
								className: "mt-1 text-xs leading-snug text-muted-foreground",
								children: card.body
							})
						]
					}, card.kind))
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketing.campaign_table.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Campaign performance",
					subtitle: `Account ROAS ${formatRatio(campaigns.data?.account_roas ?? 0)} · target ${formatRatio(campaigns.data?.target_roas ?? 0)}`,
					widgetKey: "marketing.campaign_table",
					tooltip: "Scale / Hold / Cut is computed against both your target ROAS and the account average, so a campaign is only cut when it is genuinely behind.",
					loading: campaigns.loading,
					error: campaigns.error,
					onRetry: campaigns.reload,
					verdict: campaigns.data?.verdict,
					caveat: campaigns.data?.caveat,
					exportDataset: "campaigns",
					insightPayload: campaigns.data,
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						searchPlaceholder: "Search campaigns…",
						rows: campaigns.data?.rows ?? [],
						rowKey: (row) => row.id,
						initialSort: {
							key: "spend",
							direction: "desc"
						},
						columns: [
							{
								key: "name",
								header: "Campaign",
								value: (r) => r.name,
								render: (r) => /* @__PURE__ */ jsxs("div", {
									className: "min-w-0",
									children: [/* @__PURE__ */ jsx("p", {
										className: "truncate font-medium",
										children: r.name
									}), /* @__PURE__ */ jsxs("p", {
										className: "truncate text-[11px] uppercase tracking-wide text-muted-foreground",
										children: [r.platform.replace("_", " "), r.objective ? ` · ${r.objective.replace("_", " ")}` : ""]
									})]
								})
							},
							{
								key: "spend",
								header: "Spend",
								align: "right",
								sortable: true,
								value: (r) => r.spend,
								render: (r) => formatCompactCurrency(r.spend)
							},
							{
								key: "sales",
								header: "Attributed",
								align: "right",
								sortable: true,
								value: (r) => r.attributed_sales,
								render: (r) => formatCompactCurrency(r.attributed_sales)
							},
							{
								key: "orders",
								header: "Orders",
								align: "right",
								sortable: true,
								value: (r) => r.conversions,
								render: (r) => formatNumber(r.conversions)
							},
							{
								key: "ctr",
								header: "CTR",
								align: "right",
								sortable: true,
								value: (r) => r.ctr,
								render: (r) => formatPercent(r.ctr, 2)
							},
							{
								key: "cac",
								header: "CAC",
								align: "right",
								sortable: true,
								value: (r) => r.cac,
								render: (r) => formatCompactCurrency(r.cac)
							},
							{
								key: "roas",
								header: "ROAS",
								align: "right",
								sortable: true,
								value: (r) => r.roas,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: cn("font-medium", r.roas >= (campaigns.data?.target_roas ?? 3) ? "text-good" : r.roas < 1 ? "text-bad" : ""),
									children: formatRatio(r.roas)
								})
							},
							{
								key: "status",
								header: "Verdict",
								align: "center",
								sortable: true,
								value: (r) => r.verdict.status,
								render: (r) => /* @__PURE__ */ jsx(VerdictBadge, { verdict: r.verdict })
							}
						]
					})
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketing.trend.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Marketing trend",
						subtitle: "Spend by platform against net sales, with anomaly markers",
						widgetKey: "marketing.trend",
						loading: trend.loading,
						error: trend.error,
						onRetry: trend.reload,
						caveat: (trend.data?.anomalies.length ?? 0) > 0 ? `${trend.data?.anomalies.length} day(s) flagged: spend moved more than 2 standard deviations from the period mean.` : void 0,
						insightPayload: trend.data,
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 280,
							children: /* @__PURE__ */ jsxs(ComposedChart, {
								data: trend.data?.series ?? [],
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
										minTickGap: 28
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 56
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, {}) }),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "meta_spend",
										name: "Meta spend",
										stackId: "spend",
										fill: "var(--chart-1)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "google_spend",
										name: "Google spend",
										stackId: "spend",
										fill: "var(--chart-4)",
										radius: [
											3,
											3,
											0,
											0
										],
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										type: "monotone",
										dataKey: "net_sales",
										name: "Net sales",
										stroke: "var(--chart-3)",
										strokeWidth: 2,
										dot: false,
										isAnimationActive: false
									}),
									(trend.data?.anomalies ?? []).map((anomaly) => /* @__PURE__ */ jsx(ReferenceDot, {
										x: anomaly.date,
										y: anomaly.value,
										r: 5,
										fill: "var(--bad)",
										stroke: "var(--card)",
										strokeWidth: 2,
										ifOverflow: "visible"
									}, anomaly.date))
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [
							{
								label: "Meta spend",
								color: "var(--chart-1)"
							},
							{
								label: "Google spend",
								color: "var(--chart-4)"
							},
							{
								label: "Net sales",
								color: "var(--chart-3)"
							},
							{
								label: "Anomaly",
								color: "var(--bad)"
							}
						] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketing.mer.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Marketing efficiency ratio",
						subtitle: "Total net sales ÷ total ad spend",
						widgetKey: "marketing.mer",
						tooltip: "MER is the honest number. Platform ROAS double counts across networks; MER cannot.",
						loading: mer.loading,
						error: mer.error,
						onRetry: mer.reload,
						verdict: mer.data?.verdict,
						children: [
							/* @__PURE__ */ jsx("p", {
								className: "text-3xl font-semibold tracking-tight tnum",
								children: formatRatio(mer.data?.mer ?? 0)
							}),
							/* @__PURE__ */ jsxs("p", {
								className: "text-xs text-muted-foreground",
								children: [
									"against a ",
									formatRatio(mer.data?.target ?? 0),
									" target"
								]
							}),
							/* @__PURE__ */ jsx(ResponsiveContainer, {
								width: "100%",
								height: 150,
								children: /* @__PURE__ */ jsxs(ComposedChart, {
									data: mer.data?.series ?? [],
									margin: {
										top: 8,
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
											minTickGap: 34
										}),
										/* @__PURE__ */ jsx(YAxis, {
											...AXIS_PROPS,
											width: 32
										}),
										/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, { format: "ratio" }) }),
										/* @__PURE__ */ jsx(Line, {
											type: "monotone",
											dataKey: "mer",
											name: "MER",
											stroke: "var(--chart-2)",
											strokeWidth: 2,
											dot: false,
											isAnimationActive: false
										})
									]
								})
							})
						]
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketing.conversion_funnel.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Conversion funnel",
							subtitle: "Sessions → cart → checkout → purchase",
							widgetKey: "marketing.conversion_funnel",
							loading: funnel.loading,
							error: funnel.error,
							onRetry: funnel.reload,
							verdict: funnel.data?.verdict,
							caveat: funnel.data?.caveat,
							empty: (funnel.data?.steps.length ?? 0) === 0,
							children: /* @__PURE__ */ jsx(Funnel, {
								steps: funnel.data?.steps ?? [],
								overall: funnel.data?.overall_conversion_pct
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketing.abandoned_carts.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Abandoned carts",
							subtitle: "What is sitting one step from a sale",
							widgetKey: "marketing.abandoned_carts",
							loading: carts.loading,
							error: carts.error,
							onRetry: carts.reload,
							verdict: carts.data?.verdict,
							children: /* @__PURE__ */ jsx("div", {
								className: "grid grid-cols-2 gap-3",
								children: [
									["Carts", formatNumber(carts.data?.count ?? 0)],
									["Recoverable", formatCompactCurrency(carts.data?.recoverable_value ?? 0)],
									["Recovered", `${formatNumber(carts.data?.recovered ?? 0)} · ${formatPercent(carts.data?.recovery_rate_pct ?? 0)}`],
									["Avg cart", formatCompactCurrency(carts.data?.avg_cart_value ?? 0)]
								].map(([label, value]) => /* @__PURE__ */ jsxs("div", {
									className: "rounded-lg border border-border p-3",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-[11px] uppercase tracking-wide text-muted-foreground",
										children: label
									}), /* @__PURE__ */ jsx("p", {
										className: "mt-0.5 text-base font-semibold tnum",
										children: value
									})]
								}, label))
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketing.realtime_users.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Live active users",
							subtitle: "Right now, from GA4 realtime",
							widgetKey: "marketing.realtime_users",
							loading: realtime.loading,
							error: realtime.error,
							onRetry: realtime.reload,
							caveat: realtime.data?.caveat,
							actions: realtime.data?.active_users !== null && /* @__PURE__ */ jsxs("span", {
								className: "flex items-center gap-1.5 text-[11px] text-muted-foreground",
								children: [/* @__PURE__ */ jsx(Radio, { className: "size-3 animate-pulse text-good" }), "live"]
							}),
							children: realtime.data?.active_users === null ? null : /* @__PURE__ */ jsxs(Fragment, { children: [
								/* @__PURE__ */ jsx("p", {
									className: "text-4xl font-semibold tracking-tight tnum",
									children: formatNumber(realtime.data?.active_users ?? 0)
								}),
								/* @__PURE__ */ jsx("p", {
									className: "text-xs text-muted-foreground",
									children: "active users on site"
								}),
								/* @__PURE__ */ jsx(BarList, {
									format: "number",
									rows: Object.entries(realtime.data?.by_page ?? {}).slice(0, 5).map(([page, users]) => ({
										label: page,
										value: users
									}))
								})
							] })
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketing.attribution_gap.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Attribution gap",
						subtitle: "What platforms claim vs what your store recorded",
						widgetKey: "marketing.attribution_gap",
						loading: attribution.loading,
						error: attribution.error,
						onRetry: attribution.reload,
						verdict: attribution.data?.verdict,
						caveat: attribution.data?.caveat,
						children: [/* @__PURE__ */ jsxs("div", {
							className: "grid grid-cols-2 gap-3",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "rounded-lg border border-border p-3",
								children: [
									/* @__PURE__ */ jsx("p", {
										className: "text-[11px] uppercase tracking-wide text-muted-foreground",
										children: "Platforms claim"
									}),
									/* @__PURE__ */ jsx("p", {
										className: "mt-0.5 text-lg font-semibold tnum",
										children: formatCompactCurrency(attribution.data?.platform_reported_sales ?? 0)
									}),
									/* @__PURE__ */ jsxs("p", {
										className: "mt-0.5 text-[11px] text-muted-foreground",
										children: [formatRatio(attribution.data?.attributed_roas ?? 0), " attributed ROAS"]
									})
								]
							}), /* @__PURE__ */ jsxs("div", {
								className: "rounded-lg border border-primary/30 bg-primary/5 p-3",
								children: [
									/* @__PURE__ */ jsx("p", {
										className: "text-[11px] uppercase tracking-wide text-primary/80",
										children: "Store recorded"
									}),
									/* @__PURE__ */ jsx("p", {
										className: "mt-0.5 text-lg font-semibold tnum text-primary",
										children: formatCompactCurrency(attribution.data?.store_net_sales ?? 0)
									}),
									/* @__PURE__ */ jsxs("p", {
										className: "mt-0.5 text-[11px] text-muted-foreground",
										children: [formatRatio(attribution.data?.blended_roas ?? 0), " blended ROAS"]
									})
								]
							})]
						}), /* @__PURE__ */ jsxs("p", {
							className: "text-xs text-muted-foreground",
							children: [
								"Gap of",
								" ",
								/* @__PURE__ */ jsx("span", {
									className: "font-semibold tnum text-foreground",
									children: formatCompactCurrency(attribution.data?.gap_amount ?? 0)
								}),
								" ",
								"(",
								formatPercent(attribution.data?.gap_pct ?? 0),
								" of store net sales)."
							]
						})]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketing.budget_pacing.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Budget pacing",
						subtitle: "Planned vs actual spend this month",
						widgetKey: "marketing.budget_pacing",
						loading: pacing.loading,
						error: pacing.error,
						onRetry: pacing.reload,
						caveat: pacing.data?.caveat,
						children: [/* @__PURE__ */ jsx("div", {
							className: "grid grid-cols-2 gap-3",
							children: [
								["Planned to date", pacing.data?.planned_to_date ?? 0],
								["Actual to date", pacing.data?.actual_to_date ?? 0],
								["Projected month end", pacing.data?.projected_month_end ?? 0],
								["Planned month end", pacing.data?.planned_month_end ?? 0]
							].map(([label, value]) => /* @__PURE__ */ jsxs("div", {
								className: "rounded-lg border border-border p-3",
								children: [/* @__PURE__ */ jsx("p", {
									className: "text-[11px] uppercase tracking-wide text-muted-foreground",
									children: label
								}), /* @__PURE__ */ jsx("p", {
									className: "mt-0.5 text-base font-semibold tnum",
									children: formatCompactCurrency(value)
								})]
							}, label))
						}), /* @__PURE__ */ jsxs("p", {
							className: cn("flex items-center gap-1.5 text-xs font-medium", (pacing.data?.variance ?? 0) > 0 ? "text-warn" : "text-good"),
							children: [
								(pacing.data?.variance ?? 0) > 0 ? /* @__PURE__ */ jsx(TrendingUp, { className: "size-3.5" }) : /* @__PURE__ */ jsx(TrendingDown, { className: "size-3.5" }),
								formatPercent(Math.abs(pacing.data?.variance_pct ?? 0)),
								" ",
								(pacing.data?.variance ?? 0) > 0 ? "over" : "under",
								" plan"
							]
						})]
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketing.spend_by_objective.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Spend by objective",
							widgetKey: "marketing.spend_by_objective",
							loading: objectives.loading,
							error: objectives.error,
							onRetry: objectives.reload,
							exportDataset: "campaigns",
							children: /* @__PURE__ */ jsx(BarList, { rows: (objectives.data?.rows ?? []).map((row, index) => ({
								label: row.objective,
								value: row.spend,
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: formatRatio(row.roas),
								tone: row.roas >= 3 ? "good" : row.roas < 1 ? "bad" : "neutral"
							})) })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketing.placements.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Placement performance",
							widgetKey: "marketing.placements",
							loading: placements.loading,
							error: placements.error,
							onRetry: placements.reload,
							exportDataset: "campaigns",
							children: /* @__PURE__ */ jsx(BarList, { rows: (placements.data?.rows ?? []).map((row, index) => ({
								label: row.dimension,
								value: row.spend,
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: formatRatio(row.roas),
								tone: row.roas >= 3 ? "good" : row.roas < 1 ? "bad" : "neutral"
							})) })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketing.channel_performance.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Channel performance",
							subtitle: "Sessions, orders and site ROAS",
							widgetKey: "marketing.channel_performance",
							loading: channels.loading,
							error: channels.error,
							onRetry: channels.reload,
							caveat: channels.data?.caveat,
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: channels.data?.rows ?? [],
								rowKey: (row) => row.channel_group,
								columns: [
									{
										key: "group",
										header: "Channel",
										value: (r) => r.channel_group,
										render: (r) => /* @__PURE__ */ jsx("span", {
											className: "font-medium",
											children: r.channel_group
										})
									},
									{
										key: "sessions",
										header: "Sessions",
										align: "right",
										sortable: true,
										value: (r) => r.sessions,
										render: (r) => formatNumber(r.sessions)
									},
									{
										key: "conv",
										header: "Conv %",
										align: "right",
										sortable: true,
										value: (r) => r.conversion_pct,
										render: (r) => formatPercent(r.conversion_pct, 2)
									},
									{
										key: "roas",
										header: "Site ROAS",
										align: "right",
										sortable: true,
										value: (r) => r.site_roas,
										render: (r) => r.ad_spend > 0 ? formatRatio(r.site_roas) : "—"
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
					permission: "marketing.creative_fatigue.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Creative fatigue",
						subtitle: "CTR decay against rising frequency",
						widgetKey: "marketing.creative_fatigue",
						loading: fatigue.loading,
						error: fatigue.error,
						onRetry: fatigue.reload,
						verdict: fatigue.data?.verdict,
						caveat: fatigue.data?.caveat,
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: fatigue.data?.rows ?? [],
							rowKey: (row) => row.ad_id,
							columns: [
								{
									key: "name",
									header: "Ad",
									value: (r) => r.name,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: "flex items-center gap-1.5",
										children: [/* @__PURE__ */ jsx("span", {
											className: "truncate",
											children: r.name
										}), r.is_fatigued && /* @__PURE__ */ jsx(Badge, {
											variant: "warn",
											children: "fatigued"
										})]
									})
								},
								{
									key: "spend",
									header: "Spend",
									align: "right",
									sortable: true,
									value: (r) => r.spend,
									render: (r) => formatCompactCurrency(r.spend)
								},
								{
									key: "freq",
									header: "Freq",
									align: "right",
									sortable: true,
									value: (r) => r.frequency,
									render: (r) => r.frequency.toFixed(2)
								},
								{
									key: "decay",
									header: "CTR change",
									align: "right",
									sortable: true,
									value: (r) => r.ctr_decay_pct,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: r.ctr_decay_pct < -20 ? "font-medium text-bad" : r.ctr_decay_pct > 0 ? "text-good" : "",
										children: [
											r.ctr_decay_pct > 0 ? "+" : "",
											r.ctr_decay_pct.toFixed(1),
											"%"
										]
									})
								}
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketing.buyer_persona.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Buyer persona",
						subtitle: "Age × gender: site visitors vs paid buyers",
						widgetKey: "marketing.buyer_persona",
						loading: persona.loading,
						error: persona.error,
						onRetry: persona.reload,
						caveat: persona.data?.caveat,
						empty: (persona.data?.rows.length ?? 0) === 0,
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: persona.data?.rows ?? [],
							rowKey: (row) => `${row.age}-${row.gender}`,
							columns: [
								{
									key: "seg",
									header: "Segment",
									value: (r) => `${r.age} ${r.gender}`,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: "font-medium capitalize",
										children: [
											r.age,
											" · ",
											r.gender
										]
									})
								},
								{
									key: "sessions",
									header: "Sessions",
									align: "right",
									sortable: true,
									value: (r) => r.sessions,
									render: (r) => formatNumber(r.sessions)
								},
								{
									key: "spend",
									header: "Ad spend",
									align: "right",
									sortable: true,
									value: (r) => r.ad_spend,
									render: (r) => formatCompactCurrency(r.ad_spend)
								},
								{
									key: "roas",
									header: "ROAS",
									align: "right",
									sortable: true,
									value: (r) => r.roas,
									render: (r) => r.ad_spend > 0 ? formatRatio(r.roas) : "—"
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketing.utm_analysis.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "UTM analysis",
					subtitle: "Orders attributed by the tags on your own links",
					widgetKey: "marketing.utm_analysis",
					loading: utm.loading,
					error: utm.error,
					onRetry: utm.reload,
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						rows: utm.data?.rows ?? [],
						rowKey: (row, index) => `${row.source}-${row.medium}-${row.campaign}-${index}`,
						initialSort: {
							key: "net_sales",
							direction: "desc"
						},
						columns: [
							{
								key: "source",
								header: "Source",
								value: (r) => r.source,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium",
									children: r.source
								})
							},
							{
								key: "medium",
								header: "Medium",
								value: (r) => r.medium,
								render: (r) => r.medium
							},
							{
								key: "campaign",
								header: "Campaign",
								value: (r) => r.campaign,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "truncate text-muted-foreground",
									children: r.campaign
								})
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
								key: "new",
								header: "New",
								align: "right",
								sortable: true,
								value: (r) => r.new_customers,
								render: (r) => formatNumber(r.new_customers)
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
								key: "net_sales",
								header: "Net sales",
								align: "right",
								sortable: true,
								value: (r) => r.net_sales,
								render: (r) => formatCompactCurrency(r.net_sales)
							},
							{
								key: "margin",
								header: "Margin %",
								align: "right",
								sortable: true,
								value: (r) => r.margin_pct,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: r.margin_pct < 0 ? "text-bad" : "",
									children: formatPercent(r.margin_pct)
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
export { Marketing as default };

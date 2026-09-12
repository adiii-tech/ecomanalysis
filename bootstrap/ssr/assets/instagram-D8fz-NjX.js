import { t as cn } from "./utils-BVTyW6jK.js";
import { t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { a as formatCompactNumber, f as formatNumber, p as formatPercent, s as formatDate, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { a as GRID_PROPS, c as axisNumber, i as ChartTooltip, n as CHART_COLORS, o as axisCurrency, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { n as KpiStrip } from "./kpi-card-ByDkhHxD.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useState } from "react";
import { Bookmark, ExternalLink, Heart, MessageCircle, Send } from "lucide-react";
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ComposedChart, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/pages/instagram/index.tsx
var DAYS = [
	"Mon",
	"Tue",
	"Wed",
	"Thu",
	"Fri",
	"Sat",
	"Sun"
];
var DAY_INDEX = {
	Mon: 2,
	Tue: 3,
	Wed: 4,
	Thu: 5,
	Fri: 6,
	Sat: 7,
	Sun: 1
};
function Instagram() {
	const [sort, setSort] = useState("reach");
	const kpis = useWidget("instagram/kpis");
	const trend = useWidget("instagram/account-trend");
	const engagement = useWidget("instagram/engagement-trend");
	const content = useWidget("instagram/content", { sort });
	const formats = useWidget("instagram/reels-vs-feed");
	const stories = useWidget("instagram/stories");
	const audience = useWidget("instagram/audience");
	const bestTime = useWidget("instagram/best-time");
	const hashtags = useWidget("instagram/hashtags");
	const correlation = useWidget("instagram/sales-correlation");
	const fbPage = useWidget("instagram/fb-page");
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Instagram & Facebook",
		description: "What organic is actually earning you",
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Instagram" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "instagram.kpi_strip.view",
				children: /* @__PURE__ */ jsx(KpiStrip, {
					metrics: kpis.data,
					loading: kpis.loading,
					columns: 6
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "instagram.growth_reach.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Growth & reach",
						subtitle: "Followers against the reach earning them",
						widgetKey: "instagram.growth_reach",
						loading: trend.loading,
						error: trend.error,
						onRetry: trend.reload,
						insightPayload: trend.data,
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 260,
							children: /* @__PURE__ */ jsxs(ComposedChart, {
								data: trend.data?.series ?? [],
								margin: {
									top: 4,
									right: 8,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
										id: "ig-reach",
										x1: "0",
										y1: "0",
										x2: "0",
										y2: "1",
										children: [/* @__PURE__ */ jsx("stop", {
											offset: "0%",
											stopColor: "var(--chart-6)",
											stopOpacity: .28
										}), /* @__PURE__ */ jsx("stop", {
											offset: "100%",
											stopColor: "var(--chart-6)",
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
										yAxisId: "left",
										...AXIS_PROPS,
										tickFormatter: axisNumber,
										width: 48
									}),
									/* @__PURE__ */ jsx(YAxis, {
										yAxisId: "right",
										orientation: "right",
										...AXIS_PROPS,
										tickFormatter: axisNumber,
										width: 48
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, { format: "number" }) }),
									/* @__PURE__ */ jsx(Area, {
										yAxisId: "left",
										type: "monotone",
										dataKey: "reach",
										name: "Reach",
										stroke: "var(--chart-6)",
										strokeWidth: 2,
										fill: "url(#ig-reach)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Line, {
										yAxisId: "right",
										type: "monotone",
										dataKey: "followers",
										name: "Followers",
										stroke: "var(--chart-1)",
										strokeWidth: 2,
										dot: false,
										isAnimationActive: false
									})
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [{
							label: "Reach",
							color: "var(--chart-6)"
						}, {
							label: "Followers",
							color: "var(--chart-1)"
						}] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "instagram.engagement.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Engagement",
						subtitle: "Saves are the buy-intent signal",
						widgetKey: "instagram.engagement",
						loading: engagement.loading,
						error: engagement.error,
						onRetry: engagement.reload,
						caveat: engagement.data?.caveat,
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 260,
							children: /* @__PURE__ */ jsxs(BarChart, {
								data: engagement.data?.series ?? [],
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
										tickFormatter: axisNumber,
										width: 44
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, { format: "number" }),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "likes",
										name: "Likes",
										stackId: "e",
										fill: "var(--chart-7)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "comments",
										name: "Comments",
										stackId: "e",
										fill: "var(--chart-2)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "shares",
										name: "Shares",
										stackId: "e",
										fill: "var(--chart-4)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "saves",
										name: "Saves",
										stackId: "e",
										fill: "var(--chart-3)",
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
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [
							{
								label: "Likes",
								color: "var(--chart-7)"
							},
							{
								label: "Comments",
								color: "var(--chart-2)"
							},
							{
								label: "Shares",
								color: "var(--chart-4)"
							},
							{
								label: "Saves — buy intent",
								color: "var(--chart-3)"
							}
						] })]
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "instagram.content_performance.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Content performance",
					subtitle: "Everything published in this window",
					widgetKey: "instagram.content_performance",
					loading: content.loading,
					error: content.error,
					onRetry: content.reload,
					tabs: /* @__PURE__ */ jsx(Tabs, {
						value: sort,
						onValueChange: (value) => setSort(value),
						children: /* @__PURE__ */ jsxs(TabsList, { children: [
							/* @__PURE__ */ jsx(TabsTrigger, {
								value: "reach",
								children: "Reach"
							}),
							/* @__PURE__ */ jsx(TabsTrigger, {
								value: "engagement",
								children: "Engagement"
							}),
							/* @__PURE__ */ jsx(TabsTrigger, {
								value: "saves",
								children: "Saves"
							}),
							/* @__PURE__ */ jsx(TabsTrigger, {
								value: "recent",
								children: "Recent"
							})
						] })
					}),
					children: /* @__PURE__ */ jsx("div", {
						className: "grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3",
						children: (content.data?.rows ?? []).slice(0, 12).map((post) => /* @__PURE__ */ jsxs(Card, {
							className: "p-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "flex items-start justify-between gap-2",
									children: [/* @__PURE__ */ jsx(Badge, {
										variant: post.type === "reel" ? "default" : "muted",
										children: post.type
									}), /* @__PURE__ */ jsx("span", {
										className: "text-[10px] text-muted-foreground",
										children: formatDate(post.published_at)
									})]
								}),
								/* @__PURE__ */ jsx("p", {
									className: "mt-1.5 line-clamp-2 text-xs leading-snug",
									children: post.caption ?? "—"
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "mt-2 grid grid-cols-2 gap-1.5 text-[11px]",
									children: [/* @__PURE__ */ jsxs("span", {
										className: "text-muted-foreground",
										children: ["Reach ", /* @__PURE__ */ jsx("span", {
											className: "font-medium tnum text-foreground",
											children: formatCompactNumber(post.reach)
										})]
									}), /* @__PURE__ */ jsxs("span", {
										className: "text-muted-foreground",
										children: ["ENG ", /* @__PURE__ */ jsx("span", {
											className: cn("font-medium tnum", post.engagement_rate > 5 ? "text-good" : "text-foreground"),
											children: formatPercent(post.engagement_rate, 1)
										})]
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "mt-2 flex items-center gap-3 border-t border-border/60 pt-2 text-[11px] text-muted-foreground",
									children: [
										/* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-1",
											children: [/* @__PURE__ */ jsx(Heart, { className: "size-3" }), formatCompactNumber(post.likes)]
										}),
										/* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-1",
											children: [/* @__PURE__ */ jsx(MessageCircle, { className: "size-3" }), formatCompactNumber(post.comments)]
										}),
										/* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-1",
											children: [/* @__PURE__ */ jsx(Send, { className: "size-3" }), formatCompactNumber(post.shares)]
										}),
										/* @__PURE__ */ jsxs("span", {
											className: cn("flex items-center gap-1", post.saves > 0 && "font-medium text-good"),
											children: [/* @__PURE__ */ jsx(Bookmark, { className: "size-3" }), formatCompactNumber(post.saves)]
										}),
										post.permalink && /* @__PURE__ */ jsx("a", {
											href: post.permalink,
											target: "_blank",
											rel: "noreferrer",
											className: "ml-auto hover:text-foreground",
											children: /* @__PURE__ */ jsx(ExternalLink, { className: "size-3" })
										})
									]
								})
							]
						}, post.media_id))
					})
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "instagram.reels_vs_feed.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Reels vs feed",
							subtitle: "Which format earns reach",
							widgetKey: "instagram.reels_vs_feed",
							loading: formats.loading,
							error: formats.error,
							onRetry: formats.reload,
							verdict: formats.data?.verdict,
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: formats.data?.rows ?? [],
								rowKey: (row) => row.type,
								columns: [
									{
										key: "type",
										header: "Format",
										value: (r) => r.type,
										render: (r) => /* @__PURE__ */ jsx("span", {
											className: "font-medium capitalize",
											children: r.type
										})
									},
									{
										key: "posts",
										header: "Posts",
										align: "right",
										value: (r) => r.posts,
										render: (r) => formatNumber(r.posts)
									},
									{
										key: "reach",
										header: "Avg reach",
										align: "right",
										sortable: true,
										value: (r) => r.avg_reach,
										render: (r) => formatCompactNumber(r.avg_reach)
									},
									{
										key: "eng",
										header: "Avg ENG",
										align: "right",
										sortable: true,
										value: (r) => r.avg_engagement,
										render: (r) => formatPercent(r.avg_engagement, 1)
									},
									{
										key: "saves",
										header: "Saves",
										align: "right",
										sortable: true,
										value: (r) => r.saves,
										render: (r) => formatCompactNumber(r.saves)
									}
								]
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "instagram.best_time.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Best time to post",
							subtitle: "Average reach by day and hour",
							widgetKey: "instagram.best_time",
							loading: bestTime.loading,
							error: bestTime.error,
							onRetry: bestTime.reload,
							verdict: bestTime.data?.verdict,
							caveat: bestTime.data?.caveat,
							children: /* @__PURE__ */ jsx("div", {
								className: "overflow-x-auto scrollbar-thin",
								children: /* @__PURE__ */ jsxs("table", {
									className: "w-full text-[10px]",
									children: [/* @__PURE__ */ jsx("thead", { children: /* @__PURE__ */ jsxs("tr", { children: [/* @__PURE__ */ jsx("th", { className: "w-8" }), [
										6,
										9,
										12,
										15,
										18,
										21
									].map((hour) => /* @__PURE__ */ jsxs("th", {
										className: "pb-1 text-center font-medium text-muted-foreground",
										children: [hour, ":00"]
									}, hour))] }) }), /* @__PURE__ */ jsx("tbody", { children: DAYS.map((day) => /* @__PURE__ */ jsxs("tr", { children: [/* @__PURE__ */ jsx("td", {
										className: "pr-1.5 text-right font-medium text-muted-foreground",
										children: day
									}), [
										6,
										9,
										12,
										15,
										18,
										21
									].map((hour) => {
										const cell = (bestTime.data?.cells ?? []).find((c) => c.day_index === DAY_INDEX[day] && c.hour >= hour && c.hour < hour + 3);
										const intensity = cell ? cell.avg_reach / Math.max(bestTime.data?.max_reach ?? 1, 1) : 0;
										return /* @__PURE__ */ jsx("td", {
											className: "p-0.5",
											children: /* @__PURE__ */ jsx("div", {
												className: "rounded py-1.5 text-center tnum",
												style: { background: `color-mix(in oklch, var(--chart-6) ${Math.round(intensity * 85)}%, transparent)` },
												title: cell ? `${formatCompactNumber(cell.avg_reach)} avg reach · ${cell.posts} posts` : "Never posted",
												children: cell ? formatCompactNumber(cell.avg_reach) : "·"
											})
										}, hour);
									})] }, day)) })]
								})
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "instagram.audience.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Audience",
							subtitle: "Where your followers are",
							widgetKey: "instagram.audience",
							loading: audience.loading,
							error: audience.error,
							onRetry: audience.reload,
							caveat: audience.data?.caveat,
							children: /* @__PURE__ */ jsx("div", {
								className: "space-y-3",
								children: [["Top cities", audience.data?.cities], ["Age & gender", audience.data?.gender_age]].map(([label, rows]) => /* @__PURE__ */ jsxs("div", {
									className: "space-y-1.5",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
										children: label
									}), /* @__PURE__ */ jsx(BarList, {
										format: "number",
										rows: (rows ?? []).slice(0, 5).map((row, index) => ({
											label: row.label,
											value: row.value,
											color: CHART_COLORS[index % CHART_COLORS.length]
										}))
									})]
								}, label))
							})
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "instagram.sales_correlation.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Organic reach vs sales",
						subtitle: "Do they move together?",
						widgetKey: "instagram.sales_correlation",
						loading: correlation.loading,
						error: correlation.error,
						onRetry: correlation.reload,
						caveat: correlation.data?.caveat,
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 250,
							children: /* @__PURE__ */ jsxs(ComposedChart, {
								data: correlation.data?.series ?? [],
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
										yAxisId: "left",
										...AXIS_PROPS,
										tickFormatter: axisNumber,
										width: 48
									}),
									/* @__PURE__ */ jsx(YAxis, {
										yAxisId: "right",
										orientation: "right",
										...AXIS_PROPS,
										tickFormatter: axisCurrency,
										width: 54
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, { formats: {
										reach: "number",
										net_sales: "currency"
									} }) }),
									/* @__PURE__ */ jsx(Bar, {
										yAxisId: "left",
										dataKey: "reach",
										name: "Organic reach",
										fill: "var(--chart-6)",
										fillOpacity: .55,
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
										dataKey: "net_sales",
										name: "Net sales",
										stroke: "var(--chart-1)",
										strokeWidth: 2,
										dot: false,
										isAnimationActive: false
									})
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [{
							label: "Organic reach",
							color: "var(--chart-6)"
						}, {
							label: "Net sales",
							color: "var(--chart-1)"
						}] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "instagram.hashtags.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Hashtag performance",
						widgetKey: "instagram.hashtags",
						loading: hashtags.loading,
						error: hashtags.error,
						onRetry: hashtags.reload,
						caveat: hashtags.data?.caveat,
						empty: (hashtags.data?.rows.length ?? 0) === 0,
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: hashtags.data?.rows ?? [],
							rowKey: (row) => row.tag,
							columns: [
								{
									key: "tag",
									header: "Hashtag",
									value: (r) => r.tag,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: "font-medium",
										children: ["#", r.tag]
									})
								},
								{
									key: "posts",
									header: "Posts",
									align: "right",
									value: (r) => r.posts,
									render: (r) => formatNumber(r.posts)
								},
								{
									key: "reach",
									header: "Avg reach",
									align: "right",
									sortable: true,
									value: (r) => r.avg_reach,
									render: (r) => formatCompactNumber(r.avg_reach)
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "instagram.stories.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Stories",
						subtitle: "Reach, views and replies",
						widgetKey: "instagram.stories",
						loading: stories.loading,
						error: stories.error,
						onRetry: stories.reload,
						caveat: stories.data?.caveat,
						empty: (stories.data?.rows.length ?? 0) === 0,
						emptyState: /* @__PURE__ */ jsx("p", {
							className: "py-8 text-center text-xs text-muted-foreground",
							children: "No stories captured in this window."
						}),
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: stories.data?.rows ?? [],
							rowKey: (row) => row.media_id,
							columns: [
								{
									key: "when",
									header: "Published",
									value: (r) => r.published_at,
									render: (r) => formatDate(r.published_at)
								},
								{
									key: "reach",
									header: "Reach",
									align: "right",
									sortable: true,
									value: (r) => r.reach,
									render: (r) => formatCompactNumber(r.reach)
								},
								{
									key: "views",
									header: "Views",
									align: "right",
									sortable: true,
									value: (r) => r.views,
									render: (r) => formatCompactNumber(r.views)
								},
								{
									key: "replies",
									header: "Replies",
									align: "right",
									sortable: true,
									value: (r) => r.replies,
									render: (r) => formatNumber(r.replies)
								}
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "instagram.fb_page.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Facebook Page",
						subtitle: "Reach and page views",
						widgetKey: "instagram.fb_page",
						loading: fbPage.loading,
						error: fbPage.error,
						onRetry: fbPage.reload,
						caveat: fbPage.data?.caveat,
						empty: (fbPage.data?.series.length ?? 0) === 0,
						children: /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 220,
							children: /* @__PURE__ */ jsxs(AreaChart, {
								data: fbPage.data?.series ?? [],
								margin: {
									top: 4,
									right: 8,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx("defs", { children: /* @__PURE__ */ jsxs("linearGradient", {
										id: "fb-reach",
										x1: "0",
										y1: "0",
										x2: "0",
										y2: "1",
										children: [/* @__PURE__ */ jsx("stop", {
											offset: "0%",
											stopColor: "var(--chart-1)",
											stopOpacity: .28
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
										tickFormatter: axisNumber,
										width: 46
									}),
									/* @__PURE__ */ jsx(Tooltip, { content: /* @__PURE__ */ jsx(ChartTooltip, { format: "number" }) }),
									/* @__PURE__ */ jsx(Area, {
										type: "monotone",
										dataKey: "reach",
										name: "Reach",
										stroke: "var(--chart-1)",
										strokeWidth: 2,
										fill: "url(#fb-reach)",
										isAnimationActive: false
									})
								]
							})
						})
					})
				})]
			})
		]
	});
}
//#endregion
export { Instagram as default };

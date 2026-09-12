import { t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { f as formatNumber, i as formatCompactCurrency, o as formatCurrency, p as formatPercent, t as Card } from "./card-DJDNvUnK.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { a as GRID_PROPS, i as ChartTooltip, n as CHART_COLORS, o as axisCurrency, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { t as StatStrip } from "./stat-strip-Bf4c-qAu.js";
import { n as KpiStrip } from "./kpi-card-ByDkhHxD.js";
import { t as SalesSummaryTable } from "./sales-summary-table-CJxS4gRN.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useState } from "react";
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/pages/marketplace/index.tsx
function Marketplace() {
	const [metric, setMetric] = useState("invoiced_sales");
	const snapshot = useWidget("marketplace/today-snapshot");
	const kpis = useWidget("marketplace/kpis");
	const trend = useWidget("marketplace/revenue-trend", { metric });
	const comparison = useWidget("marketplace/channel-comparison");
	const summary = useWidget("marketplace/sales-summary");
	const categories = useWidget("marketplace/top-categories");
	const status = useWidget("marketplace/order-status");
	const states = useWidget("marketplace/top-states");
	const products = useWidget("marketplace/top-products");
	const matrix = useWidget("marketplace/top-products-by-channel");
	const channelReturns = useWidget("marketplace/channel-returns");
	useWidget("marketplace/top-return-reasons");
	const zeroOrders = useWidget("marketplace/inventory/zero-orders");
	const fastMoving = useWidget("marketplace/inventory/fast-moving");
	const valuation = useWidget("marketplace/inventory/valuation");
	const settlements = useWidget("marketplace/settlements");
	const buybox = useWidget("marketplace/buybox");
	const pricing = useWidget("marketplace/price-competitiveness");
	const orders = useWidget("marketplace/recent-orders");
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Marketplace",
		description: "Amazon, Flipkart, Myntra and the rest — after commission",
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Marketplace" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketplace.today_snapshot.view",
				children: /* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [/* @__PURE__ */ jsx("p", {
						className: "text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
						children: "Today, against yesterday — independent of the date filter above"
					}), /* @__PURE__ */ jsx(StatStrip, {
						stats: snapshot.data ?? null,
						loading: snapshot.loading,
						columns: 5
					})]
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketplace.kpi_strip.view",
				children: /* @__PURE__ */ jsx(KpiStrip, {
					metrics: kpis.data,
					loading: kpis.loading,
					columns: 6
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.sales_trend.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Sales by marketplace",
						subtitle: "Daily, stacked by channel",
						widgetKey: "marketplace.sales_trend",
						loading: trend.loading,
						error: trend.error,
						onRetry: trend.reload,
						insightPayload: trend.data,
						tabs: /* @__PURE__ */ jsx(Tabs, {
							value: metric,
							onValueChange: (value) => setMetric(value),
							children: /* @__PURE__ */ jsxs(TabsList, { children: [
								/* @__PURE__ */ jsx(TabsTrigger, {
									value: "invoiced_sales",
									children: "Sales"
								}),
								/* @__PURE__ */ jsx(TabsTrigger, {
									value: "net_sales",
									children: "Net"
								}),
								/* @__PURE__ */ jsx(TabsTrigger, {
									value: "items_count",
									children: "Items"
								})
							] })
						}),
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 260,
							children: /* @__PURE__ */ jsxs(BarChart, {
								data: trend.data?.series ?? [],
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
										tickFormatter: metric === "items_count" ? void 0 : axisCurrency,
										width: 54
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, { format: metric === "items_count" ? "number" : "currency" }),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									(trend.data?.channels ?? []).map((channel, index) => /* @__PURE__ */ jsx(Bar, {
										dataKey: channel.code,
										name: channel.name,
										stackId: "c",
										fill: channel.color ?? CHART_COLORS[index % CHART_COLORS.length],
										radius: index === (trend.data?.channels.length ?? 1) - 1 ? [
											3,
											3,
											0,
											0
										] : 0,
										isAnimationActive: false
									}, channel.code))
								]
							})
						}), /* @__PURE__ */ jsx(ChartLegend, { items: (trend.data?.channels ?? []).map((c, i) => ({
							label: c.name,
							color: c.color ?? CHART_COLORS[i % CHART_COLORS.length]
						})) })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.channel_comparison.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Channel comparison",
						subtitle: "Share of net sales and margin",
						widgetKey: "marketplace.channel_comparison",
						loading: comparison.loading,
						error: comparison.error,
						onRetry: comparison.reload,
						verdict: comparison.data?.verdict,
						exportDataset: "channel_scorecard",
						children: /* @__PURE__ */ jsx(BarList, { rows: (comparison.data?.rows ?? []).map((row) => ({
							label: row.name,
							value: row.net_sales,
							share: row.share_pct,
							color: row.color,
							secondary: formatPercent(row.margin_pct),
							tone: row.margin_pct < 0 ? "bad" : row.margin_pct > 30 ? "good" : "neutral"
						})) })
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.sales_summary.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "Marketplace sales summary",
						subtitle: "Gross → net, including COD charges",
						widgetKey: "marketplace.sales_summary",
						loading: summary.loading,
						error: summary.error,
						onRetry: summary.reload,
						verdict: summary.data?.verdict,
						exportDataset: "sales_summary",
						children: /* @__PURE__ */ jsx(SalesSummaryTable, { rows: summary.data?.rows ?? [] })
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.order_status.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Order status",
						widgetKey: "marketplace.order_status",
						loading: status.loading,
						error: status.error,
						onRetry: status.reload,
						exportDataset: "orders",
						children: /* @__PURE__ */ jsx(BarList, {
							format: "number",
							rows: (status.data?.rows ?? []).map((row, index) => ({
								label: row.label,
								value: row.count,
								share: row.share_pct,
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: formatPercent(row.share_pct, 0)
							}))
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketplace.top_categories.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Top categories",
							widgetKey: "marketplace.top_categories",
							loading: categories.loading,
							error: categories.error,
							onRetry: categories.reload,
							exportDataset: "top_skus",
							children: /* @__PURE__ */ jsx(BarList, { rows: (categories.data?.rows ?? []).map((row, index) => ({
								label: row.category,
								value: row.net_sales,
								share: row.share_pct,
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: formatPercent(row.margin_pct)
							})) })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketplace.top_states.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Top states",
							widgetKey: "marketplace.top_states",
							loading: states.loading,
							error: states.error,
							onRetry: states.reload,
							exportDataset: "geo_states",
							children: /* @__PURE__ */ jsx(BarList, { rows: (states.data?.rows ?? []).map((row) => ({
								label: row.state,
								value: row.net_sales,
								share: row.share_pct,
								secondary: `${formatNumber(row.orders)} orders`
							})) })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketplace.channel_returns.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Return % by channel",
							widgetKey: "marketplace.channel_returns",
							loading: channelReturns.loading,
							error: channelReturns.error,
							onRetry: channelReturns.reload,
							exportDataset: "returns_register",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: channelReturns.data?.rows ?? [],
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
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.top_products.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Top products",
						subtitle: "Share of total net sales",
						widgetKey: "marketplace.top_products",
						loading: products.loading,
						error: products.error,
						onRetry: products.reload,
						exportDataset: "top_skus",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: products.data?.rows ?? [],
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
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.products_by_channel.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Products by channel",
						subtitle: "Net sales per SKU, per marketplace",
						widgetKey: "marketplace.products_by_channel",
						loading: matrix.loading,
						error: matrix.error,
						onRetry: matrix.reload,
						exportDataset: "top_skus",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: matrix.data?.rows ?? [],
							rowKey: (row) => String(row.sku_id),
							columns: [
								{
									key: "sku",
									header: "SKU",
									value: (r) => String(r.sku_code),
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "font-medium",
										children: r.sku_code
									})
								},
								...(matrix.data?.channels ?? []).map((channel) => ({
									key: channel.code,
									header: channel.name,
									align: "right",
									sortable: true,
									value: (r) => Number(r[channel.code] ?? 0),
									render: (r) => {
										const value = Number(r[channel.code] ?? 0);
										return value === 0 ? /* @__PURE__ */ jsx("span", {
											className: "text-muted-foreground/50",
											children: "—"
										}) : formatCompactCurrency(value);
									}
								})),
								{
									key: "total",
									header: "Total",
									align: "right",
									sortable: true,
									value: (r) => Number(r.total),
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "font-medium",
										children: formatCompactCurrency(Number(r.total))
									})
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketplace.zero_order_skus.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Products with zero orders",
							subtitle: "Dead stock, and the capital tied up in it",
							widgetKey: "marketplace.zero_order_skus",
							loading: zeroOrders.loading,
							error: zeroOrders.error,
							onRetry: zeroOrders.reload,
							verdict: zeroOrders.data?.verdict,
							exportDataset: "zero_order_skus",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: zeroOrders.data?.rows ?? [],
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
										value: (r) => Number(r.stock),
										render: (r) => formatNumber(Number(r.stock))
									},
									{
										key: "capital",
										header: "Capital",
										align: "right",
										sortable: true,
										value: (r) => Number(r.capital_held),
										render: (r) => formatCompactCurrency(Number(r.capital_held))
									}
								]
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketplace.fast_moving.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Fast-moving SKUs",
							subtitle: "Highest daily sell-through",
							widgetKey: "marketplace.fast_moving",
							loading: fastMoving.loading,
							error: fastMoving.error,
							onRetry: fastMoving.reload,
							exportDataset: "inventory_health",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: fastMoving.data?.rows ?? [],
								rowKey: (row) => row.sku_code,
								columns: [
									{
										key: "sku",
										header: "SKU",
										value: (r) => r.sku_code,
										render: (r) => /* @__PURE__ */ jsx("span", {
											className: "font-medium",
											children: r.sku_code
										})
									},
									{
										key: "rate",
										header: "Units / day",
										align: "right",
										sortable: true,
										value: (r) => r.daily_rate,
										render: (r) => r.daily_rate.toFixed(2)
									},
									{
										key: "cover",
										header: "Days left",
										align: "right",
										sortable: true,
										value: (r) => r.days_of_cover,
										render: (r) => /* @__PURE__ */ jsxs("span", {
											className: r.days_of_cover < 14 ? "font-medium text-warn" : "",
											children: [r.days_of_cover.toFixed(1), "d"]
										})
									}
								]
							})
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "marketplace.inventory_valuation.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Inventory valuation",
							subtitle: `${formatNumber(valuation.data?.total_units ?? 0)} units · ${formatCurrency(valuation.data?.total_at_cost ?? 0)} at cost`,
							widgetKey: "marketplace.inventory_valuation",
							loading: valuation.loading,
							error: valuation.error,
							onRetry: valuation.reload,
							exportDataset: "inventory_health",
							children: /* @__PURE__ */ jsx(BarList, { rows: (valuation.data?.rows ?? []).map((row, index) => ({
								label: row.category,
								value: Number(row.value_at_cost),
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: `${formatNumber(Number(row.units))}u`
							})) })
						})
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.settlements.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Settlement reconciliation",
						subtitle: "Expected vs received per cycle",
						widgetKey: "marketplace.settlements",
						loading: settlements.loading,
						error: settlements.error,
						onRetry: settlements.reload,
						caveat: settlements.data?.caveat,
						empty: (settlements.data?.rows.length ?? 0) === 0,
						children: /* @__PURE__ */ jsx("div", {
							className: "grid grid-cols-3 gap-3",
							children: [
								["Expected", settlements.data?.expected_total ?? 0],
								["Received", settlements.data?.received_total ?? 0],
								["Variance", settlements.data?.variance_total ?? 0]
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
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "marketplace.buybox.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Buy Box & listing health",
						widgetKey: "marketplace.buybox",
						loading: buybox.loading,
						error: buybox.error,
						onRetry: buybox.reload,
						caveat: buybox.data?.caveat,
						empty: true,
						children: /* @__PURE__ */ jsx("div", {})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketplace.price_competitiveness.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Price competitiveness",
					subtitle: "What customers actually paid, per channel",
					widgetKey: "marketplace.price_competitiveness",
					loading: pricing.loading,
					error: pricing.error,
					onRetry: pricing.reload,
					caveat: pricing.data?.caveat,
					empty: (pricing.data?.rows.length ?? 0) === 0,
					children: /* @__PURE__ */ jsx("div", {
						className: "space-y-3",
						children: (pricing.data?.rows ?? []).slice(0, 10).map((row) => /* @__PURE__ */ jsxs(Card, {
							className: "p-3",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "flex flex-wrap items-baseline justify-between gap-2",
								children: [/* @__PURE__ */ jsxs("div", {
									className: "min-w-0",
									children: [/* @__PURE__ */ jsxs("p", {
										className: "truncate text-xs font-medium",
										children: [
											row.sku_code,
											" · ",
											row.name
										]
									}), /* @__PURE__ */ jsxs("p", {
										className: "text-[11px] text-muted-foreground",
										children: ["MRP ", formatCurrency(row.mrp)]
									})]
								}), /* @__PURE__ */ jsxs("p", {
									className: "text-[11px] text-muted-foreground",
									children: ["Spread ", /* @__PURE__ */ jsx("span", {
										className: "font-semibold tnum text-foreground",
										children: formatCurrency(row.price_spread)
									})]
								})]
							}), /* @__PURE__ */ jsx("div", {
								className: "mt-2 flex flex-wrap gap-1.5",
								children: row.channels.map((channel) => /* @__PURE__ */ jsxs("span", {
									className: "rounded-md border border-border px-2 py-1 text-[11px] tnum",
									children: [
										channel.channel_name,
										" · ",
										formatCurrency(channel.realised_price),
										/* @__PURE__ */ jsxs("span", {
											className: "ml-1 text-muted-foreground",
											children: ["−", formatPercent(channel.discount_from_mrp_pct, 0)]
										})
									]
								}, channel.channel_name))
							})]
						}, row.sku_code))
					})
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "marketplace.recent_orders.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Recent orders",
					widgetKey: "marketplace.recent_orders",
					loading: orders.loading,
					error: orders.error,
					onRetry: orders.reload,
					exportDataset: "orders",
					children: /* @__PURE__ */ jsx(DataTable, {
						rows: orders.data?.rows ?? [],
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
								key: "status",
								header: "Status",
								value: (r) => r.status_label,
								render: (r) => r.status_label
							},
							{
								key: "payment",
								header: "Payment",
								value: (r) => r.payment_mode,
								render: (r) => r.payment_mode === "cod" ? "COD" : "Prepaid"
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
				})
			})
		]
	});
}
//#endregion
export { Marketplace as default };

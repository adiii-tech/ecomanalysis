import { t as cn } from "./utils-BVTyW6jK.js";
import { t as AppLayout } from "./app-layout-Drf1OqZl.js";
import { c as formatDateTime, f as formatNumber, i as formatCompactCurrency, o as formatCurrency, p as formatPercent } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { a as GRID_PROPS, c as axisNumber, i as ChartTooltip, l as axisPercent, n as CHART_COLORS, r as ChartLegend, s as axisDate, t as AXIS_PROPS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { t as StatStrip } from "./stat-strip-Bf4c-qAu.js";
import { t as ReturnsBasisToggle } from "./returns-basis-toggle-Hux1TInn.js";
import { t as Funnel } from "./funnel-CayeUjz3.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { Bar, BarChart, CartesianGrid, Cell, ComposedChart, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";
//#region resources/js/pages/operations/index.tsx
function Operations() {
	const kpis = useWidget("operations/kpis");
	const returnKpis = useWidget("operations/returns/kpis");
	const reasons = useWidget("operations/returns/by-reason");
	const byChannel = useWidget("operations/returns/by-channel");
	const trend = useWidget("operations/returns/trend");
	const shipmentStatus = useWidget("operations/shipment-status");
	const couriers = useWidget("operations/courier-scorecard");
	const rtoStates = useWidget("operations/rto-by-state");
	const funnel = useWidget("operations/delivery-funnel");
	const delivery = useWidget("operations/delivery-performance");
	const ndr = useWidget("operations/ndr-queue");
	const aging = useWidget("operations/order-aging");
	const pincodes = useWidget("operations/pincode-risk");
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Operations",
		description: "Shipments, returns, RTO and the queues that need a human today",
		filterExtras: /* @__PURE__ */ jsx(ReturnsBasisToggle, {}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Operations" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "operations.kpi_strip.view",
				children: /* @__PURE__ */ jsx(StatStrip, {
					stats: kpis.data ?? null,
					loading: kpis.loading,
					columns: 4
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "operations.returns_kpis.view",
				children: /* @__PURE__ */ jsx(StatStrip, {
					stats: returnKpis.data ?? null,
					loading: returnKpis.loading,
					columns: 4
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "operations.order_aging.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Order aging",
						subtitle: "Everything still unshipped, right now",
						widgetKey: "operations.order_aging",
						loading: aging.loading,
						error: aging.error,
						onRetry: aging.reload,
						verdict: aging.data?.verdict,
						caveat: aging.data?.caveat,
						exportDataset: "order_aging",
						children: [/* @__PURE__ */ jsx("div", {
							className: "grid grid-cols-4 gap-2",
							children: (aging.data?.buckets ?? []).map((bucket) => /* @__PURE__ */ jsxs("div", {
								className: cn("rounded-lg border p-3", bucket.bucket === ">3d" && bucket.count > 0 ? "border-bad/30 bg-bad-soft/40" : "border-border"),
								children: [
									/* @__PURE__ */ jsx("p", {
										className: "text-[11px] uppercase tracking-wide text-muted-foreground",
										children: bucket.bucket
									}),
									/* @__PURE__ */ jsx("p", {
										className: cn("mt-0.5 text-lg font-semibold tnum", bucket.bucket === ">3d" && bucket.count > 0 && "text-bad"),
										children: formatNumber(bucket.count)
									}),
									/* @__PURE__ */ jsx("p", {
										className: "text-[11px] text-muted-foreground tnum",
										children: formatCompactCurrency(bucket.value)
									})
								]
							}, bucket.bucket))
						}), /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							searchable: true,
							searchPlaceholder: "Search unshipped orders…",
							rows: aging.data?.rows ?? [],
							rowKey: (row) => row.id,
							columns: [
								{
									key: "order",
									header: "Order",
									value: (r) => r.order_number,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: "flex items-center gap-1.5",
										children: [/* @__PURE__ */ jsx("span", {
											className: "font-medium",
											children: r.order_number
										}), r.sla_breached && /* @__PURE__ */ jsx(Badge, {
											variant: "bad",
											children: "SLA"
										})]
									})
								},
								{
									key: "channel",
									header: "Channel",
									value: (r) => r.channel_name,
									render: (r) => r.channel_name ?? "—"
								},
								{
									key: "state",
									header: "State",
									value: (r) => r.shipping_state,
									render: (r) => r.shipping_state ?? "—"
								},
								{
									key: "placed",
									header: "Placed",
									value: (r) => r.placed_at,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "text-muted-foreground",
										children: formatDateTime(r.placed_at)
									})
								},
								{
									key: "age",
									header: "Age",
									align: "right",
									sortable: true,
									value: (r) => r.age_days,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: r.sla_breached ? "font-medium text-bad" : "",
										children: [r.age_days.toFixed(1), "d"]
									})
								},
								{
									key: "value",
									header: "Value",
									align: "right",
									sortable: true,
									value: (r) => r.net_amount,
									render: (r) => formatCurrency(r.net_amount)
								}
							]
						})]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "operations.delivery_performance.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						title: "Delivery performance",
						subtitle: "Against your delivery SLA",
						widgetKey: "operations.delivery_performance",
						loading: delivery.loading,
						error: delivery.error,
						onRetry: delivery.reload,
						verdict: delivery.data?.verdict,
						children: [/* @__PURE__ */ jsx("div", {
							className: "grid grid-cols-3 gap-2",
							children: [
								["On time", formatPercent(delivery.data?.on_time_pct ?? 0)],
								["Median", `${delivery.data?.median_days ?? 0}d`],
								["Late", formatNumber(delivery.data?.late_count ?? 0)]
							].map(([label, value]) => /* @__PURE__ */ jsxs("div", {
								className: "rounded-lg border border-border p-2.5 text-center",
								children: [/* @__PURE__ */ jsx("p", {
									className: "text-[10px] uppercase tracking-wide text-muted-foreground",
									children: label
								}), /* @__PURE__ */ jsx("p", {
									className: "mt-0.5 text-sm font-semibold tnum",
									children: value
								})]
							}, label))
						}), /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 170,
							children: /* @__PURE__ */ jsxs(BarChart, {
								data: delivery.data?.distribution ?? [],
								margin: {
									top: 8,
									right: 4,
									bottom: 0,
									left: 4
								},
								children: [
									/* @__PURE__ */ jsx(CartesianGrid, { ...GRID_PROPS }),
									/* @__PURE__ */ jsx(XAxis, {
										dataKey: "day",
										...AXIS_PROPS,
										tickFormatter: (v) => `${v}d`
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisNumber,
										width: 36
									}),
									/* @__PURE__ */ jsx(ReferenceLine, {
										x: delivery.data?.sla_days,
										stroke: "var(--bad)",
										strokeDasharray: "4 4"
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, {
											format: "number",
											labelFormatter: (l) => `${l} days`
										}),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "count",
										name: "Shipments",
										radius: [
											3,
											3,
											0,
											0
										],
										isAnimationActive: false,
										children: (delivery.data?.distribution ?? []).map((row) => /* @__PURE__ */ jsx(Cell, { fill: row.day <= (delivery.data?.sla_days ?? 7) ? "var(--good)" : "var(--bad)" }, row.day))
									})
								]
							})
						})]
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "operations.ndr_queue.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "NDR queue",
					subtitle: "Undelivered attempts waiting on a decision",
					widgetKey: "operations.ndr_queue",
					tooltip: "After the third failed attempt most couriers return the shipment automatically. Acting before then is what stops an RTO.",
					loading: ndr.loading,
					error: ndr.error,
					onRetry: ndr.reload,
					verdict: ndr.data?.verdict,
					empty: (ndr.data?.count ?? 0) === 0,
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						searchPlaceholder: "Search AWB, order or city…",
						rows: ndr.data?.rows ?? [],
						rowKey: (row) => row.id,
						columns: [
							{
								key: "awb",
								header: "AWB",
								value: (r) => r.awb,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium tnum",
									children: r.awb
								})
							},
							{
								key: "order",
								header: "Order",
								value: (r) => r.order_number,
								render: (r) => r.order_number
							},
							{
								key: "courier",
								header: "Courier",
								value: (r) => r.courier,
								render: (r) => r.courier
							},
							{
								key: "dest",
								header: "Destination",
								value: (r) => `${r.destination_city} ${r.destination_state}`,
								render: (r) => /* @__PURE__ */ jsxs("span", {
									className: "text-muted-foreground",
									children: [
										r.destination_city,
										", ",
										r.destination_state
									]
								})
							},
							{
								key: "reason",
								header: "Reason",
								value: (r) => r.ndr_reason,
								render: (r) => r.ndr_reason ?? "—"
							},
							{
								key: "attempts",
								header: "Attempts",
								align: "center",
								sortable: true,
								value: (r) => r.attempts,
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: r.attempts >= 3 ? "bad" : "warn",
									children: r.attempts
								})
							},
							{
								key: "value",
								header: "At risk",
								align: "right",
								sortable: true,
								value: (r) => r.net_amount,
								render: (r) => formatCurrency(r.net_amount)
							}
						]
					})
				})
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "operations.courier_scorecard.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Courier scorecard",
						subtitle: "Who deserves the next shipment",
						widgetKey: "operations.courier_scorecard",
						tooltip: "Score weights delivery rate 40%, on-time 35% and low RTO 25%.",
						loading: couriers.loading,
						error: couriers.error,
						onRetry: couriers.reload,
						verdict: couriers.data?.verdict,
						caveat: couriers.data?.caveat,
						exportDataset: "courier_scorecard",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: couriers.data?.rows ?? [],
							rowKey: (row) => row.courier,
							columns: [
								{
									key: "courier",
									header: "Courier",
									value: (r) => r.courier,
									render: (r) => /* @__PURE__ */ jsxs("span", {
										className: "flex items-center gap-1.5 font-medium",
										children: [r.courier, !r.meets_sla && /* @__PURE__ */ jsx(Badge, {
											variant: "warn",
											children: "below SLA"
										})]
									})
								},
								{
									key: "ship",
									header: "Shipments",
									align: "right",
									sortable: true,
									value: (r) => r.shipments,
									render: (r) => formatNumber(r.shipments)
								},
								{
									key: "ontime",
									header: "On time",
									align: "right",
									sortable: true,
									value: (r) => r.on_time_pct,
									render: (r) => formatPercent(r.on_time_pct)
								},
								{
									key: "rto",
									header: "RTO",
									align: "right",
									sortable: true,
									value: (r) => r.rto_pct,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: r.rto_pct > 15 ? "text-bad" : "",
										children: formatPercent(r.rto_pct)
									})
								},
								{
									key: "days",
									header: "Avg days",
									align: "right",
									sortable: true,
									value: (r) => r.avg_days,
									render: (r) => `${r.avg_days}d`
								},
								{
									key: "cost",
									header: "Cost/ship",
									align: "right",
									sortable: true,
									value: (r) => r.cost_per_shipment,
									render: (r) => formatCurrency(r.cost_per_shipment)
								},
								{
									key: "score",
									header: "Score",
									align: "right",
									sortable: true,
									value: (r) => r.score,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: cn("font-semibold", r.score >= 85 ? "text-good" : r.score < 70 ? "text-bad" : ""),
										children: r.score
									})
								}
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "operations.rto_by_state.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "RTO by state",
						subtitle: "Flagged against your threshold",
						widgetKey: "operations.rto_by_state",
						loading: rtoStates.loading,
						error: rtoStates.error,
						onRetry: rtoStates.reload,
						verdict: rtoStates.data?.verdict,
						caveat: rtoStates.data?.caveat,
						exportDataset: "rto_by_state",
						children: /* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 280,
							children: /* @__PURE__ */ jsxs(BarChart, {
								data: (rtoStates.data?.rows ?? []).slice(0, 12),
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
										width: 110,
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
										children: (rtoStates.data?.rows ?? []).slice(0, 12).map((row) => /* @__PURE__ */ jsx(Cell, { fill: row.rto_pct >= (rtoStates.data?.threshold ?? 15) ? "var(--bad)" : "var(--chart-4)" }, row.state))
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
					permission: "operations.returns_trend.view",
					children: /* @__PURE__ */ jsxs(ChartCard, {
						className: "xl:col-span-2",
						title: "Returns trend",
						subtitle: `On the ${trend.data?.basis === "return_date" ? "return" : "order"} date basis`,
						widgetKey: "operations.returns_trend",
						loading: trend.loading,
						error: trend.error,
						onRetry: trend.reload,
						exportDataset: "returns_register",
						children: [/* @__PURE__ */ jsx(ResponsiveContainer, {
							width: "100%",
							height: 240,
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
										minTickGap: 26
									}),
									/* @__PURE__ */ jsx(YAxis, {
										...AXIS_PROPS,
										tickFormatter: axisNumber,
										width: 40
									}),
									/* @__PURE__ */ jsx(Tooltip, {
										content: /* @__PURE__ */ jsx(ChartTooltip, { format: "number" }),
										cursor: {
											fill: "var(--accent)",
											opacity: .4
										}
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "customer_returns",
										name: "Customer returns",
										stackId: "r",
										fill: "var(--chart-5)",
										isAnimationActive: false
									}),
									/* @__PURE__ */ jsx(Bar, {
										dataKey: "rto_events",
										name: "RTO",
										stackId: "r",
										fill: "var(--chart-4)",
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
						}), /* @__PURE__ */ jsx(ChartLegend, { items: [{
							label: "Customer returns",
							color: "var(--chart-5)"
						}, {
							label: "RTO",
							color: "var(--chart-4)"
						}] })]
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "operations.returns_by_reason.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Returns by reason",
						widgetKey: "operations.returns_by_reason",
						loading: reasons.loading,
						error: reasons.error,
						onRetry: reasons.reload,
						exportDataset: "returns_register",
						children: /* @__PURE__ */ jsx(BarList, {
							format: "number",
							rows: (reasons.data?.rows ?? []).map((row, index) => ({
								label: row.label,
								value: row.count,
								color: CHART_COLORS[index % CHART_COLORS.length],
								secondary: formatCompactCurrency(row.refund_amount)
							}))
						})
					})
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "operations.returns_by_channel.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Returns by channel",
							widgetKey: "operations.returns_by_channel",
							loading: byChannel.loading,
							error: byChannel.error,
							onRetry: byChannel.reload,
							exportDataset: "returns_register",
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: byChannel.data?.rows ?? [],
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
										key: "returns",
										header: "Returns",
										align: "right",
										value: (r) => r.customer_returns,
										render: (r) => formatNumber(r.customer_returns)
									},
									{
										key: "rto",
										header: "RTO",
										align: "right",
										value: (r) => r.rto_events,
										render: (r) => formatNumber(r.rto_events)
									},
									{
										key: "pct",
										header: "%",
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
						permission: "operations.delivery_funnel.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Delivery funnel",
							subtitle: "Shipped → delivered",
							widgetKey: "operations.delivery_funnel",
							loading: funnel.loading,
							error: funnel.error,
							onRetry: funnel.reload,
							children: /* @__PURE__ */ jsx(Funnel, { steps: funnel.data?.steps ?? [] })
						})
					}),
					/* @__PURE__ */ jsx(PermissionGuard, {
						permission: "operations.pincode_risk.view",
						children: /* @__PURE__ */ jsx(ChartCard, {
							title: "Risky pincodes",
							subtitle: "High RTO — expose to checkout via the risk API",
							widgetKey: "operations.pincode_risk",
							loading: pincodes.loading,
							error: pincodes.error,
							onRetry: pincodes.reload,
							caveat: pincodes.data?.caveat,
							empty: (pincodes.data?.count ?? 0) === 0,
							children: /* @__PURE__ */ jsx(DataTable, {
								dense: true,
								rows: pincodes.data?.rows ?? [],
								rowKey: (row) => row.pincode,
								columns: [
									{
										key: "pin",
										header: "Pincode",
										value: (r) => r.pincode,
										render: (r) => /* @__PURE__ */ jsxs("div", {
											className: "min-w-0",
											children: [/* @__PURE__ */ jsx("p", {
												className: "font-medium tnum",
												children: r.pincode
											}), /* @__PURE__ */ jsx("p", {
												className: "truncate text-[11px] text-muted-foreground",
												children: r.city
											})]
										})
									},
									{
										key: "ships",
										header: "Ships",
										align: "right",
										value: (r) => r.shipments_count,
										render: (r) => formatNumber(r.shipments_count)
									},
									{
										key: "rto",
										header: "RTO %",
										align: "right",
										sortable: true,
										value: (r) => r.rto_rate,
										render: (r) => /* @__PURE__ */ jsx(Badge, {
											variant: r.risk_band === "critical" ? "bad" : "warn",
											children: formatPercent(r.rto_rate)
										})
									}
								]
							})
						})
					})
				]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "operations.shipment_status.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Shipment status by courier",
					widgetKey: "operations.shipment_status",
					loading: shipmentStatus.loading,
					error: shipmentStatus.error,
					onRetry: shipmentStatus.reload,
					caveat: shipmentStatus.data?.caveat,
					exportDataset: "courier_scorecard",
					children: /* @__PURE__ */ jsx(DataTable, {
						rows: shipmentStatus.data?.rows ?? [],
						rowKey: (row) => String(row.courier),
						columns: [
							{
								key: "courier",
								header: "Courier",
								value: (r) => String(r.courier),
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium",
									children: r.courier
								})
							},
							...(shipmentStatus.data?.statuses ?? []).filter((status) => (shipmentStatus.data?.rows ?? []).some((row) => Number(row[status.key] ?? 0) > 0)).map((status) => ({
								key: status.key,
								header: status.label,
								align: "right",
								sortable: true,
								value: (r) => Number(r[status.key] ?? 0),
								render: (r) => {
									const value = Number(r[status.key] ?? 0);
									return value === 0 ? /* @__PURE__ */ jsx("span", {
										className: "text-muted-foreground/50",
										children: "—"
									}) : formatNumber(value);
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
									children: formatNumber(Number(r.total))
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
export { Operations as default };

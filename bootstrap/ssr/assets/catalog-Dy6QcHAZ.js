import { t as cn } from "./utils-BVTyW6jK.js";
import { t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { f as formatNumber, i as formatCompactCurrency, o as formatCurrency, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { t as BarList } from "./bar-list-CSlL2QKm.js";
import { n as CHART_COLORS } from "./chart-primitives-ChBp4vNI.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/pages/catalog/index.tsx
function Catalog() {
	const inventory = useWidget("operations/inventory");
	const reorder = useWidget("operations/reorder");
	const stockouts = useWidget("operations/stockouts");
	const valuation = useWidget("operations/inventory-valuation");
	const activeSkus = inventory.data?.rows.length ?? 0;
	const outOfStock = (inventory.data?.rows ?? []).filter((row) => row.stock <= 0).length;
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Catalog & inventory",
		description: "What you hold, what it is worth, and what to reorder",
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Catalog" }),
			/* @__PURE__ */ jsx("div", {
				className: "grid gap-3 grid-cols-2 lg:grid-cols-4",
				children: [
					["Active SKUs", formatNumber(activeSkus)],
					["Out of stock", formatNumber(outOfStock)],
					["Stock at cost", formatCompactCurrency(valuation.data?.total_at_cost ?? 0)],
					["Stock at retail", formatCompactCurrency(valuation.data?.total_at_retail ?? 0)]
				].map(([label, value]) => /* @__PURE__ */ jsxs(Card, {
					className: "p-4",
					children: [/* @__PURE__ */ jsx("p", {
						className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
						children: label
					}), /* @__PURE__ */ jsx("p", {
						className: "mt-2 text-xl font-semibold tnum",
						children: value
					})]
				}, label))
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-3",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.reorder.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						className: "xl:col-span-2",
						title: "Reorder plan",
						subtitle: "Days of cover, suggested quantity and ABC class",
						widgetKey: "catalog.reorder",
						tooltip: "A/B/C class ranks SKUs by their share of revenue — A is the top 80%.",
						loading: reorder.loading,
						error: reorder.error,
						onRetry: reorder.reload,
						caveat: reorder.data?.caveat,
						exportDataset: "reorder",
						children: /* @__PURE__ */ jsx(DataTable, {
							searchable: true,
							searchPlaceholder: "Search SKU…",
							rows: reorder.data?.rows ?? [],
							rowKey: (row) => row.sku_id,
							columns: [
								{
									key: "sku",
									header: "SKU",
									value: (r) => `${r.sku_code} ${r.name}`,
									render: (r) => /* @__PURE__ */ jsxs("div", {
										className: "min-w-0",
										children: [/* @__PURE__ */ jsxs("p", {
											className: "flex items-center gap-1.5 truncate font-medium",
											children: [r.sku_code, r.abc_class && /* @__PURE__ */ jsx(Badge, {
												variant: r.abc_class === "A" ? "good" : r.abc_class === "B" ? "muted" : "outline",
												children: r.abc_class
											})]
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
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: r.stock <= 0 ? "font-medium text-bad" : "",
										children: formatNumber(r.stock)
									})
								},
								{
									key: "rate",
									header: "Units/day",
									align: "right",
									sortable: true,
									value: (r) => r.daily_rate,
									render: (r) => r.daily_rate.toFixed(2)
								},
								{
									key: "cover",
									header: "Days cover",
									align: "right",
									sortable: true,
									value: (r) => r.days_of_cover,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: cn(r.days_of_cover < 7 ? "font-medium text-bad" : r.days_of_cover < 14 ? "text-warn" : ""),
										children: r.days_of_cover >= 999 ? "∞" : `${r.days_of_cover.toFixed(1)}d`
									})
								},
								{
									key: "reorder",
									header: "Reorder",
									align: "right",
									sortable: true,
									value: (r) => r.suggested_reorder_qty,
									render: (r) => r.suggested_reorder_qty > 0 ? /* @__PURE__ */ jsx("span", {
										className: "font-semibold",
										children: formatNumber(r.suggested_reorder_qty)
									}) : /* @__PURE__ */ jsx("span", {
										className: "text-muted-foreground",
										children: "—"
									})
								},
								{
									key: "rev",
									header: "Rev / 30d",
									align: "right",
									sortable: true,
									value: (r) => r.monthly_revenue,
									render: (r) => formatCompactCurrency(r.monthly_revenue)
								}
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.inventory.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Inventory value by category",
						widgetKey: "catalog.inventory",
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
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.stockouts.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Stockout impact",
						subtitle: `About ${formatCurrency(stockouts.data?.total_lost_revenue ?? 0)} of revenue not captured`,
						widgetKey: "catalog.stockouts",
						loading: stockouts.loading,
						error: stockouts.error,
						onRetry: stockouts.reload,
						caveat: stockouts.data?.caveat,
						empty: (stockouts.data?.rows.length ?? 0) === 0,
						emptyState: /* @__PURE__ */ jsx("p", {
							className: "py-8 text-center text-xs text-muted-foreground",
							children: "Nothing that was selling has run out. 🎉"
						}),
						exportDataset: "stockout",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: stockouts.data?.rows ?? [],
							rowKey: (row) => row.sku_id,
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
									key: "rate",
									header: "Units/day",
									align: "right",
									sortable: true,
									value: (r) => r.daily_rate,
									render: (r) => r.daily_rate.toFixed(2)
								},
								{
									key: "lost",
									header: "Est. lost",
									align: "right",
									sortable: true,
									value: (r) => r.estimated_lost_revenue,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "font-medium text-bad",
										children: formatCompactCurrency(r.estimated_lost_revenue)
									})
								}
							]
						})
					})
				}), /* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.slow_movers.view",
					children: /* @__PURE__ */ jsx(ChartCard, {
						title: "Dead stock",
						subtitle: "Holding stock, selling nothing",
						widgetKey: "catalog.slow_movers",
						loading: reorder.loading,
						error: reorder.error,
						onRetry: reorder.reload,
						empty: (reorder.data?.dead_stock.length ?? 0) === 0,
						emptyState: /* @__PURE__ */ jsx("p", {
							className: "py-8 text-center text-xs text-muted-foreground",
							children: "Every SKU holding stock is selling."
						}),
						exportDataset: "zero_order_skus",
						children: /* @__PURE__ */ jsx(DataTable, {
							dense: true,
							rows: reorder.data?.dead_stock ?? [],
							rowKey: (row) => row.sku_id,
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
									key: "value",
									header: "Capital",
									align: "right",
									sortable: true,
									value: (r) => r.stock_value,
									render: (r) => /* @__PURE__ */ jsx("span", {
										className: "font-medium text-warn",
										children: formatCompactCurrency(r.stock_value)
									})
								}
							]
						})
					})
				})]
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "catalog.products.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "All SKUs",
					subtitle: "Stock, sell-through and margin per SKU",
					widgetKey: "catalog.products",
					loading: inventory.loading,
					error: inventory.error,
					onRetry: inventory.reload,
					exportDataset: "inventory_health",
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						searchPlaceholder: "Search SKU or product…",
						rows: inventory.data?.rows ?? [],
						rowKey: (row) => row.sku_id,
						initialSort: {
							key: "rev",
							direction: "desc"
						},
						columns: [
							{
								key: "sku",
								header: "SKU",
								value: (r) => `${r.sku_code} ${r.name}`,
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
								key: "category",
								header: "Category",
								value: (r) => r.category,
								render: (r) => r.category ?? "—"
							},
							{
								key: "cost",
								header: "Cost",
								align: "right",
								sortable: true,
								value: (r) => r.cost_price,
								render: (r) => formatCurrency(r.cost_price)
							},
							{
								key: "price",
								header: "Price",
								align: "right",
								sortable: true,
								value: (r) => r.selling_price,
								render: (r) => formatCurrency(r.selling_price)
							},
							{
								key: "stock",
								header: "Stock",
								align: "right",
								sortable: true,
								value: (r) => r.stock,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: r.stock <= 0 ? "font-medium text-bad" : "",
									children: formatNumber(r.stock)
								})
							},
							{
								key: "units",
								header: "Sold / 30d",
								align: "right",
								sortable: true,
								value: (r) => r.units_30d,
								render: (r) => formatNumber(r.units_30d)
							},
							{
								key: "rev",
								header: "Rev / 30d",
								align: "right",
								sortable: true,
								value: (r) => r.monthly_revenue,
								render: (r) => formatCompactCurrency(r.monthly_revenue)
							}
						]
					})
				})
			})
		]
	});
}
//#endregion
export { Catalog as default };

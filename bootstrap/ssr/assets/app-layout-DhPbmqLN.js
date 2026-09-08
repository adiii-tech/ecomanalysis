import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as apiSend, c as DropdownMenu, d as DropdownMenuLabel, f as DropdownMenuSeparator, i as apiGet, l as DropdownMenuContent, o as useFilters, p as DropdownMenuTrigger, s as usePermissions, u as DropdownMenuItem } from "./empty-state-DjIQjBC7.js";
import { u as formatLongDate } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { Link, router, usePage } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import * as React from "react";
import { useCallback, useEffect, useState } from "react";
import { Bell, Bookmark, Boxes, CalendarDays, Camera, Check, ChevronDown, ChevronLeft, Command, FileBarChart, LayoutDashboard, Loader2, LogOut, Megaphone, Moon, PanelLeftClose, PanelLeftOpen, PlugZap, RefreshCw, Settings, Share2, Sparkles, Store, Sun, Trash2, Truck, User, Users, Wallet, Warehouse } from "lucide-react";
import { toast } from "sonner";
import * as SeparatorPrimitive from "@radix-ui/react-separator";
import { Command as Command$1 } from "cmdk";
import * as Dialog from "@radix-ui/react-dialog";
import * as PopoverPrimitive from "@radix-ui/react-popover";
import * as SelectPrimitive from "@radix-ui/react-select";
//#region resources/js/components/ui/separator.tsx
var Separator = React.forwardRef(({ className, orientation = "horizontal", decorative = true, ...props }, ref) => /* @__PURE__ */ jsx(SeparatorPrimitive.Root, {
	ref,
	decorative,
	orientation,
	className: cn("shrink-0 bg-border", orientation === "horizontal" ? "h-px w-full" : "h-full w-px", className),
	...props
}));
Separator.displayName = SeparatorPrimitive.Root.displayName;
//#endregion
//#region resources/js/lib/navigation.ts
var NAVIGATION = [
	{
		label: "Overview",
		items: [{
			label: "Command Centre",
			href: "/dashboard",
			icon: LayoutDashboard,
			permission: "dashboard.kpi_strip.view"
		}, {
			label: "Finance",
			href: "/finance",
			icon: Wallet,
			permission: "finance.kpi_strip.view"
		}]
	},
	{
		label: "Growth",
		items: [
			{
				label: "Marketing",
				href: "/marketing",
				icon: Megaphone,
				permission: "marketing.kpi_strip.view"
			},
			{
				label: "Instagram",
				href: "/instagram",
				icon: Camera,
				permission: "instagram.kpi_strip.view"
			},
			{
				label: "Customers & Reviews",
				href: "/customers",
				icon: Users,
				permission: "customer_intelligence.kpi_strip.view"
			}
		]
	},
	{
		label: "Operations",
		items: [
			{
				label: "Marketplace",
				href: "/marketplace",
				icon: Store,
				permission: "marketplace.kpi_strip.view"
			},
			{
				label: "Operations",
				href: "/operations",
				icon: Truck,
				permission: "operations.kpi_strip.view"
			},
			{
				label: "Catalog",
				href: "/catalog",
				icon: Boxes,
				permission: "catalog.kpi_strip.view"
			},
			{
				label: "Inventory",
				href: "/inventory",
				icon: Warehouse,
				permission: "catalog.stock.view"
			}
		]
	},
	{
		label: "Intelligence",
		items: [
			{
				label: "Reports",
				href: "/reports",
				icon: FileBarChart,
				permission: "reports.library.view"
			},
			{
				label: "Ask AI",
				href: "/ask-ai",
				icon: Sparkles,
				permission: "ai.chat.view"
			},
			{
				label: "Alerts",
				href: "/alerts",
				icon: Bell,
				permission: "alerts.events.view"
			}
		]
	},
	{
		label: "Setup",
		items: [{
			label: "Connectors",
			href: "/connectors",
			icon: PlugZap,
			permission: "connectors.index.view"
		}, {
			label: "Admin",
			href: "/admin/users",
			icon: Settings,
			permission: "admin.users.view"
		}]
	}
];
var REPORT_LINKS = [
	{
		key: "owner_business_review",
		slug: "owner-business-review",
		label: "Owner Business Review",
		category: "Executive",
		description: "One-screen scorecard against the previous period."
	},
	{
		key: "channel_scorecard",
		slug: "channel-scorecard",
		label: "Channel Scorecard",
		category: "Profit & Margin",
		description: "Revenue, orders, AOV, margin % and RTO ranked by channel."
	},
	{
		key: "discount_impact",
		slug: "discount-impact",
		label: "Discount Impact",
		category: "Profit & Margin",
		description: "Did the promo buy volume or burn margin?"
	},
	{
		key: "fee_leakage",
		slug: "fee-leakage",
		label: "Fee Leakage",
		category: "Profit & Margin",
		description: "Commission, fixed, shipping and settlement fees per channel."
	},
	{
		key: "net_realisation",
		slug: "net-realisation",
		label: "Net Realisation",
		category: "Profit & Margin",
		description: "Gross to net waterfall per marketplace after fees, discounts and GST."
	},
	{
		key: "order_profitability",
		slug: "order-profitability",
		label: "Order Profitability",
		category: "Profit & Margin",
		description: "Order-level P&L, filterable and exportable."
	},
	{
		key: "cohort_retention",
		slug: "cohort-retention",
		label: "Cohort Retention",
		category: "Marketing & Customers",
		description: "Acquisition month by month repeat rate."
	},
	{
		key: "geo_cities",
		slug: "geo-cities",
		label: "Geography",
		category: "Marketing & Customers",
		description: "Revenue and orders by state and city."
	},
	{
		key: "channel_cac",
		slug: "channel-cac",
		label: "Channel CAC",
		category: "Marketing & Customers",
		description: "CAC and ROAS by acquisition source."
	},
	{
		key: "new_vs_repeat",
		slug: "new-vs-repeat",
		label: "New vs Repeat",
		category: "Marketing & Customers",
		description: "Conversion and ROAS split by customer type."
	},
	{
		key: "state_roi",
		slug: "state-roi",
		label: "State ROI",
		category: "Marketing & Customers",
		description: "Revenue, spend, ROAS and CAC by state."
	},
	{
		key: "top_customers",
		slug: "top-customers",
		label: "Top Customers",
		category: "Marketing & Customers",
		description: "Highest lifetime value customers."
	},
	{
		key: "inventory_health",
		slug: "inventory-health",
		label: "Inventory Health",
		category: "Operations & Inventory",
		description: "Stock levels, turnover and reorder signals by SKU."
	},
	{
		key: "logistics_performance",
		slug: "logistics-performance",
		label: "Logistics Performance",
		category: "Operations & Inventory",
		description: "Shipment status, courier performance and RTO by state."
	},
	{
		key: "order_aging",
		slug: "order-aging",
		label: "Order Aging",
		category: "Operations & Inventory",
		description: "Unshipped orders by age with SLA breach flags."
	},
	{
		key: "reorder_replenishment",
		slug: "reorder-replenishment",
		label: "Reorder & Replenishment",
		category: "Operations & Inventory",
		description: "Days of cover, suggested quantities, ABC class and dead stock."
	},
	{
		key: "stockout",
		slug: "stockout",
		label: "Stockout Impact",
		category: "Operations & Inventory",
		description: "Revenue lost to out-of-stock per SKU."
	},
	{
		key: "cod_cash_flow",
		slug: "cod-cash-flow",
		label: "COD Cash Flow",
		category: "Returns & Cash",
		description: "Collected vs remitted, settlement delays and reconciliation."
	},
	{
		key: "returns_rto_register",
		slug: "returns-rto-register",
		label: "Returns & RTO Register",
		category: "Returns & Cash",
		description: "Line-item register in Tally-matching column format."
	},
	{
		key: "transaction_ledger",
		slug: "transaction-ledger",
		label: "Transaction Ledger",
		category: "Returns & Cash",
		description: "Every payment, refund and failure with gateway and status."
	},
	{
		key: "pnl_statement",
		slug: "pnl-statement",
		label: "P&L Statement",
		category: "Finance",
		description: "Full monthly P&L down to EBITDA."
	},
	{
		key: "gst_summary",
		slug: "gst-summary",
		label: "GST Summary",
		category: "Finance",
		description: "Output tax, HSN-wise, B2C/B2B split."
	},
	{
		key: "sku_margin_waterfall",
		slug: "sku-margin-waterfall",
		label: "SKU Margin Waterfall",
		category: "Profit & Margin",
		description: "MRP to margin, step by step, per SKU."
	},
	{
		key: "contribution_by_cohort",
		slug: "contribution-by-cohort",
		label: "Contribution by Cohort",
		category: "Marketing & Customers",
		description: "Profit per acquisition cohort over time."
	},
	{
		key: "forecast",
		slug: "forecast",
		label: "Forecast",
		category: "Executive",
		description: "30/60/90-day sales and inventory projection."
	},
	{
		key: "stock_ledger",
		slug: "stock-ledger",
		label: "Stock Ledger",
		category: "Operations & Inventory",
		description: "Every stock movement, with the shrinkage it adds up to."
	},
	{
		key: "inventory_valuation",
		slug: "inventory-valuation",
		label: "Inventory Valuation",
		category: "Finance",
		description: "Stock value at cost, at retail, and what is aging out."
	}
];
[...new Set(REPORT_LINKS.map((report) => report.category))];
//#endregion
//#region resources/js/components/app/command-palette.tsx
/** ⌘K — jump to any page or report the user is allowed to see. */
function CommandPalette() {
	const [open, setOpen] = useState(false);
	const { can } = usePermissions();
	useEffect(() => {
		function onKeyDown(event) {
			if (event.key === "k" && (event.metaKey || event.ctrlKey)) {
				event.preventDefault();
				setOpen((value) => !value);
			}
		}
		document.addEventListener("keydown", onKeyDown);
		return () => document.removeEventListener("keydown", onKeyDown);
	}, []);
	function go(href) {
		setOpen(false);
		router.visit(href);
	}
	const pages = NAVIGATION.flatMap((section) => section.items).filter((item) => !item.permission || can(item.permission));
	const reports = REPORT_LINKS.filter((report) => can(`reports.${report.key}.view`));
	return /* @__PURE__ */ jsx(Dialog.Root, {
		open,
		onOpenChange: setOpen,
		children: /* @__PURE__ */ jsxs(Dialog.Portal, { children: [/* @__PURE__ */ jsx(Dialog.Overlay, { className: "fixed inset-0 z-50 bg-black/45 backdrop-blur-[1px] data-[state=open]:animate-in data-[state=open]:fade-in-0" }), /* @__PURE__ */ jsxs(Dialog.Content, {
			className: "fixed left-1/2 top-[18%] z-50 w-[min(94vw,560px)] -translate-x-1/2 overflow-hidden rounded-xl border border-border bg-popover shadow-2xl data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95",
			children: [/* @__PURE__ */ jsx(Dialog.Title, {
				className: "sr-only",
				children: "Command palette"
			}), /* @__PURE__ */ jsxs(Command$1, {
				loop: true,
				children: [/* @__PURE__ */ jsx(Command$1.Input, {
					autoFocus: true,
					placeholder: "Jump to a page, report, SKU or order…",
					className: "w-full border-b border-border bg-transparent px-4 py-3 text-sm outline-none placeholder:text-muted-foreground"
				}), /* @__PURE__ */ jsxs(Command$1.List, {
					className: "max-h-80 overflow-auto p-1.5 scrollbar-thin",
					children: [
						/* @__PURE__ */ jsx(Command$1.Empty, {
							className: "py-8 text-center text-xs text-muted-foreground",
							children: "Nothing matches that."
						}),
						/* @__PURE__ */ jsx(Command$1.Group, {
							heading: "Pages",
							className: "[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-[11px] [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:tracking-wide [&_[cmdk-group-heading]]:text-muted-foreground",
							children: pages.map((item) => /* @__PURE__ */ jsxs(Command$1.Item, {
								value: `${item.label} ${item.href}`,
								onSelect: () => go(item.href),
								className: cn("flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm", "data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground"),
								children: [/* @__PURE__ */ jsx(item.icon, { className: "size-4 text-muted-foreground" }), item.label]
							}, item.href))
						}),
						reports.length > 0 && /* @__PURE__ */ jsx(Command$1.Group, {
							heading: "Reports",
							className: "[&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-[11px] [&_[cmdk-group-heading]]:font-semibold [&_[cmdk-group-heading]]:uppercase [&_[cmdk-group-heading]]:tracking-wide [&_[cmdk-group-heading]]:text-muted-foreground",
							children: reports.map((report) => /* @__PURE__ */ jsxs(Command$1.Item, {
								value: `report ${report.label}`,
								onSelect: () => go(`/reports/${report.slug}`),
								className: "flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground",
								children: [/* @__PURE__ */ jsx("span", {
									className: "text-[11px] uppercase tracking-wide text-muted-foreground",
									children: report.category
								}), report.label]
							}, report.key))
						})
					]
				})]
			})]
		})] })
	});
}
//#endregion
//#region resources/js/components/ui/popover.tsx
var Popover = PopoverPrimitive.Root;
var PopoverTrigger = PopoverPrimitive.Trigger;
var PopoverContent = React.forwardRef(({ className, align = "start", sideOffset = 6, ...props }, ref) => /* @__PURE__ */ jsx(PopoverPrimitive.Portal, { children: /* @__PURE__ */ jsx(PopoverPrimitive.Content, {
	ref,
	align,
	sideOffset,
	className: cn("z-50 rounded-xl border border-border bg-popover p-3 text-popover-foreground shadow-lg outline-none", "data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95", "data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95", className),
	...props
}) }));
PopoverContent.displayName = PopoverPrimitive.Content.displayName;
//#endregion
//#region resources/js/components/app/sync-indicator.tsx
var DOT_CLASS = {
	green: "bg-good",
	amber: "bg-warn",
	red: "bg-bad",
	grey: "bg-muted-foreground",
	blue: "bg-primary animate-pulse"
};
function SyncIndicator() {
	const { syncHealth } = usePage().props;
	if (!syncHealth) return null;
	return /* @__PURE__ */ jsxs(Popover, { children: [/* @__PURE__ */ jsx(PopoverTrigger, {
		asChild: true,
		children: /* @__PURE__ */ jsxs(Button, {
			variant: "ghost",
			size: "sm",
			className: "gap-1.5 px-2 text-xs font-normal text-muted-foreground",
			children: [/* @__PURE__ */ jsx("span", { className: cn("size-2 rounded-full", DOT_CLASS[syncHealth.status]) }), /* @__PURE__ */ jsx("span", {
				className: "hidden sm:inline",
				children: syncHealth.label
			})]
		})
	}), /* @__PURE__ */ jsxs(PopoverContent, {
		align: "end",
		className: "w-80 p-0",
		children: [
			/* @__PURE__ */ jsxs("div", {
				className: "border-b border-border px-3 py-2.5",
				children: [/* @__PURE__ */ jsx("p", {
					className: "text-xs font-semibold",
					children: "Connector health"
				}), /* @__PURE__ */ jsx("p", {
					className: "text-[11px] text-muted-foreground",
					children: syncHealth.label
				})]
			}),
			/* @__PURE__ */ jsx("div", {
				className: "max-h-80 overflow-auto scrollbar-thin",
				children: syncHealth.connectors.length === 0 ? /* @__PURE__ */ jsxs("div", {
					className: "px-3 py-6 text-center",
					children: [/* @__PURE__ */ jsx("p", {
						className: "text-xs text-muted-foreground",
						children: "No connectors yet."
					}), /* @__PURE__ */ jsx(Button, {
						size: "sm",
						variant: "outline",
						asChild: true,
						className: "mt-2",
						children: /* @__PURE__ */ jsx("a", {
							href: "/connectors",
							children: "Connect a source"
						})
					})]
				}) : syncHealth.connectors.map((connector) => /* @__PURE__ */ jsxs("div", {
					className: "flex items-start gap-2.5 border-b border-border/60 px-3 py-2.5 last:border-0",
					children: [/* @__PURE__ */ jsx("span", { className: cn("mt-1.5 size-2 shrink-0 rounded-full", DOT_CLASS[connector.dot]) }), /* @__PURE__ */ jsxs("div", {
						className: "min-w-0 flex-1",
						children: [
							/* @__PURE__ */ jsxs("div", {
								className: "flex items-baseline justify-between gap-2",
								children: [/* @__PURE__ */ jsx("p", {
									className: "truncate text-xs font-medium",
									children: connector.label
								}), /* @__PURE__ */ jsx("span", {
									className: "shrink-0 text-[10px] text-muted-foreground",
									children: connector.last_synced_human ?? "never synced"
								})]
							}),
							connector.account_label && /* @__PURE__ */ jsx("p", {
								className: "truncate text-[11px] text-muted-foreground",
								children: connector.account_label
							}),
							connector.last_error && /* @__PURE__ */ jsx("p", {
								className: "mt-0.5 line-clamp-2 text-[11px] text-bad",
								children: connector.last_error
							})
						]
					})]
				}, connector.id))
			}),
			/* @__PURE__ */ jsx("div", {
				className: "border-t border-border px-3 py-2",
				children: /* @__PURE__ */ jsx(Button, {
					variant: "ghost",
					size: "sm",
					asChild: true,
					className: "h-7 w-full justify-start gap-1.5 text-xs",
					children: /* @__PURE__ */ jsxs("a", {
						href: "/connectors",
						children: [/* @__PURE__ */ jsx(RefreshCw, { className: "size-3" }), "Manage connectors"]
					})
				})
			})
		]
	})] });
}
//#endregion
//#region resources/js/components/app/date-range-picker.tsx
var PRESETS = [
	{
		value: "today",
		label: "Today"
	},
	{
		value: "yesterday",
		label: "Yesterday"
	},
	{
		value: "last_7_days",
		label: "Last 7 days"
	},
	{
		value: "last_30_days",
		label: "Last 30 days"
	},
	{
		value: "last_90_days",
		label: "Last 90 days"
	},
	{
		value: "mtd",
		label: "Month to date"
	},
	{
		value: "last_month",
		label: "Last month"
	},
	{
		value: "qtd",
		label: "Quarter to date"
	},
	{
		value: "ytd",
		label: "Financial YTD"
	}
];
/**
* The global window. Persisted in the URL query and localStorage, and attached
* to every analytics request.
*/
function DateRangePicker({ period }) {
	const { filters, setFilters } = useFilters();
	const [open, setOpen] = useState(false);
	const [customFrom, setCustomFrom] = useState(filters.from ?? "");
	const [customTo, setCustomTo] = useState(filters.to ?? "");
	const label = filters.preset ? PRESETS.find((p) => p.value === filters.preset)?.label ?? "Custom" : period ? `${formatLongDate(period.from)} — ${formatLongDate(period.to)}` : "Custom range";
	return /* @__PURE__ */ jsxs(Popover, {
		open,
		onOpenChange: setOpen,
		children: [/* @__PURE__ */ jsx(PopoverTrigger, {
			asChild: true,
			children: /* @__PURE__ */ jsxs(Button, {
				variant: "outline",
				size: "sm",
				className: "gap-2 font-normal",
				children: [
					/* @__PURE__ */ jsx(CalendarDays, { className: "size-3.5 text-muted-foreground" }),
					/* @__PURE__ */ jsx("span", {
						className: "max-w-[190px] truncate",
						children: label
					}),
					/* @__PURE__ */ jsx(ChevronDown, { className: "size-3.5 text-muted-foreground" })
				]
			})
		}), /* @__PURE__ */ jsxs(PopoverContent, {
			align: "end",
			className: "w-72 p-2",
			children: [
				/* @__PURE__ */ jsx("div", {
					className: "grid grid-cols-2 gap-1",
					children: PRESETS.map((preset) => /* @__PURE__ */ jsx("button", {
						type: "button",
						onClick: () => {
							setFilters({ preset: preset.value });
							setOpen(false);
						},
						className: cn("rounded-lg px-2.5 py-1.5 text-left text-xs transition-colors hover:bg-accent", filters.preset === preset.value && "bg-accent font-medium text-accent-foreground"),
						children: preset.label
					}, preset.value))
				}),
				/* @__PURE__ */ jsx(Separator, { className: "my-2" }),
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-2 px-1 pb-1",
					children: [
						/* @__PURE__ */ jsx("p", {
							className: "text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
							children: "Custom range"
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "grid grid-cols-2 gap-2",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "space-y-1",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "range-from",
									children: "From"
								}), /* @__PURE__ */ jsx(Input, {
									id: "range-from",
									type: "date",
									value: customFrom,
									onChange: (e) => setCustomFrom(e.target.value),
									className: "h-8 text-xs"
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "space-y-1",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "range-to",
									children: "To"
								}), /* @__PURE__ */ jsx(Input, {
									id: "range-to",
									type: "date",
									value: customTo,
									onChange: (e) => setCustomTo(e.target.value),
									className: "h-8 text-xs"
								})]
							})]
						}),
						/* @__PURE__ */ jsx(Button, {
							size: "sm",
							className: "w-full",
							disabled: !customFrom || !customTo,
							onClick: () => {
								setFilters({
									from: customFrom,
									to: customTo,
									preset: null
								});
								setOpen(false);
							},
							children: "Apply range"
						})
					]
				})
			]
		})]
	});
}
//#endregion
//#region resources/js/components/ui/select.tsx
var Select = SelectPrimitive.Root;
var SelectGroup = SelectPrimitive.Group;
var SelectValue = SelectPrimitive.Value;
var SelectTrigger = React.forwardRef(({ className, children, ...props }, ref) => /* @__PURE__ */ jsxs(SelectPrimitive.Trigger, {
	ref,
	className: cn("flex h-9 w-full items-center justify-between gap-2 rounded-lg border border-input bg-card px-3 py-1 text-sm shadow-xs", "focus:outline-none focus:ring-2 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50 [&>span]:truncate", className),
	...props,
	children: [children, /* @__PURE__ */ jsx(SelectPrimitive.Icon, {
		asChild: true,
		children: /* @__PURE__ */ jsx(ChevronDown, { className: "size-4 shrink-0 opacity-50" })
	})]
}));
SelectTrigger.displayName = SelectPrimitive.Trigger.displayName;
var SelectContent = React.forwardRef(({ className, children, position = "popper", ...props }, ref) => /* @__PURE__ */ jsx(SelectPrimitive.Portal, { children: /* @__PURE__ */ jsx(SelectPrimitive.Content, {
	ref,
	position,
	className: cn("relative z-50 max-h-72 min-w-[8rem] overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-lg", "data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0", position === "popper" && "data-[side=bottom]:translate-y-1", className),
	...props,
	children: /* @__PURE__ */ jsx(SelectPrimitive.Viewport, {
		className: cn("p-1", position === "popper" && "w-full min-w-[var(--radix-select-trigger-width)]"),
		children
	})
}) }));
SelectContent.displayName = SelectPrimitive.Content.displayName;
var SelectItem = React.forwardRef(({ className, children, ...props }, ref) => /* @__PURE__ */ jsxs(SelectPrimitive.Item, {
	ref,
	className: cn("relative flex w-full cursor-pointer select-none items-center rounded-lg py-1.5 pl-8 pr-2 text-sm outline-none", "focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50", className),
	...props,
	children: [/* @__PURE__ */ jsx("span", {
		className: "absolute left-2.5 flex size-3.5 items-center justify-center",
		children: /* @__PURE__ */ jsx(SelectPrimitive.ItemIndicator, { children: /* @__PURE__ */ jsx(Check, { className: "size-3.5" }) })
	}), /* @__PURE__ */ jsx(SelectPrimitive.ItemText, { children })]
}));
SelectItem.displayName = SelectPrimitive.Item.displayName;
var SelectLabel = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(SelectPrimitive.Label, {
	ref,
	className: cn("px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground", className),
	...props
}));
SelectLabel.displayName = SelectPrimitive.Label.displayName;
//#endregion
//#region resources/js/components/app/channel-filter.tsx
function ChannelFilter() {
	const { channels } = usePage().props;
	const { filters, setFilters } = useFilters();
	const marketplaces = channels.filter((channel) => channel.type === "marketplace");
	const d2c = channels.filter((channel) => channel.type === "d2c");
	return /* @__PURE__ */ jsxs(Select, {
		value: filters.channel,
		onValueChange: (value) => setFilters({ channel: value }),
		children: [/* @__PURE__ */ jsx(SelectTrigger, {
			className: "h-8 w-[170px] text-xs",
			children: /* @__PURE__ */ jsxs("span", {
				className: "flex items-center gap-1.5 truncate",
				children: [/* @__PURE__ */ jsx(Store, { className: "size-3.5 shrink-0 text-muted-foreground" }), /* @__PURE__ */ jsx(SelectValue, {})]
			})
		}), /* @__PURE__ */ jsxs(SelectContent, { children: [
			/* @__PURE__ */ jsx(SelectItem, {
				value: "all",
				children: "All channels"
			}),
			/* @__PURE__ */ jsx(SelectItem, {
				value: "d2c",
				children: "D2C only"
			}),
			/* @__PURE__ */ jsx(SelectItem, {
				value: "marketplace",
				children: "Marketplaces only"
			}),
			d2c.length > 0 && /* @__PURE__ */ jsxs(SelectGroup, { children: [/* @__PURE__ */ jsx(SelectLabel, { children: "D2C" }), d2c.map((channel) => /* @__PURE__ */ jsx(SelectItem, {
				value: channel.code,
				children: channel.name
			}, channel.code))] }),
			marketplaces.length > 0 && /* @__PURE__ */ jsxs(SelectGroup, { children: [/* @__PURE__ */ jsx(SelectLabel, { children: "Marketplaces" }), marketplaces.map((channel) => /* @__PURE__ */ jsx(SelectItem, {
				value: channel.code,
				children: channel.name
			}, channel.code))] })
		] })]
	});
}
//#endregion
//#region resources/js/components/app/saved-views.tsx
/**
* Named filter sets for the current screen. "Last quarter, marketplace only,
* return-date basis" is a question people ask weekly — this stops them
* rebuilding it every time.
*/
function SavedViews({ surface }) {
	const { filters, setFilters } = useFilters();
	const [views, setViews] = useState(null);
	const [open, setOpen] = useState(false);
	const [name, setName] = useState("");
	const [shared, setShared] = useState(false);
	const [saving, setSaving] = useState(false);
	const load = useCallback(() => {
		apiGet("/saved-views", { surface }).then((response) => setViews(response.data.rows)).catch(() => setViews([]));
	}, [surface]);
	useEffect(() => {
		if (open && views === null) load();
	}, [
		open,
		views,
		load
	]);
	const save = async () => {
		if (name.trim() === "") {
			toast.error("Give the view a name.");
			return;
		}
		setSaving(true);
		try {
			await apiSend("POST", "/saved-views", {
				surface,
				name: name.trim(),
				state: filters,
				is_shared: shared
			});
			toast.success("View saved.");
			setName("");
			load();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save the view.");
		} finally {
			setSaving(false);
		}
	};
	const apply = (view) => {
		setFilters(view.state);
		setOpen(false);
		toast.success(`Applied "${view.name}".`);
	};
	const remove = async (view) => {
		try {
			await apiSend("DELETE", `/saved-views/${view.id}`);
			toast.success("View deleted.");
			load();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not delete that view.");
		}
	};
	return /* @__PURE__ */ jsxs(Popover, {
		open,
		onOpenChange: setOpen,
		children: [/* @__PURE__ */ jsx(PopoverTrigger, {
			asChild: true,
			children: /* @__PURE__ */ jsxs(Button, {
				variant: "outline",
				size: "sm",
				className: "gap-1.5 text-xs",
				children: [
					/* @__PURE__ */ jsx(Bookmark, { className: "size-3.5" }),
					"Views",
					views && views.length > 0 && /* @__PURE__ */ jsx(Badge, {
						variant: "muted",
						children: views.length
					})
				]
			})
		}), /* @__PURE__ */ jsx(PopoverContent, {
			align: "start",
			className: "w-80 p-3",
			children: /* @__PURE__ */ jsxs("div", {
				className: "space-y-3",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						views === null && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-muted-foreground",
							children: "Loading…"
						}),
						views?.length === 0 && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-muted-foreground",
							children: "No saved views yet. Set the filters you want, then name them below."
						}),
						views?.map((view) => /* @__PURE__ */ jsxs("div", {
							className: "flex items-center gap-1",
							children: [/* @__PURE__ */ jsxs("button", {
								type: "button",
								onClick: () => apply(view),
								className: "flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition-colors hover:bg-accent/60",
								children: [
									/* @__PURE__ */ jsx(Check, { className: "size-3 shrink-0 text-muted-foreground" }),
									/* @__PURE__ */ jsx("span", {
										className: "truncate font-medium",
										children: view.name
									}),
									view.is_shared && /* @__PURE__ */ jsx(Share2, { className: "size-3 shrink-0 text-muted-foreground" }),
									!view.is_mine && /* @__PURE__ */ jsxs("span", {
										className: "shrink-0 text-[10px] text-muted-foreground",
										children: ["by ", view.owner]
									})
								]
							}), view.is_mine && /* @__PURE__ */ jsx(Button, {
								variant: "ghost",
								size: "icon",
								onClick: () => remove(view),
								"aria-label": `Delete ${view.name}`,
								children: /* @__PURE__ */ jsx(Trash2, { className: "size-3.5" })
							})]
						}, view.id))
					]
				}), /* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5 border-t border-border pt-3",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "view-name",
							children: "Save the current filters"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "view-name",
							placeholder: "Last quarter, marketplace only",
							value: name,
							onChange: (event) => setName(event.target.value),
							onKeyDown: (event) => event.key === "Enter" && save()
						}),
						/* @__PURE__ */ jsxs("label", {
							className: "flex cursor-pointer items-center gap-2 text-[11px] text-muted-foreground",
							children: [/* @__PURE__ */ jsx("input", {
								type: "checkbox",
								checked: shared,
								onChange: (event) => setShared(event.target.checked),
								className: "size-3.5 rounded border-border"
							}), "Share with everyone on this account"]
						}),
						/* @__PURE__ */ jsxs(Button, {
							size: "sm",
							className: cn("w-full"),
							onClick: save,
							disabled: saving,
							children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-3.5 animate-spin" }), " Save view"]
						})
					]
				})]
			})
		})]
	});
}
//#endregion
//#region resources/js/hooks/use-appearance.ts
var ACCENTS = [
	"indigo",
	"emerald",
	"rose",
	"amber",
	"cyan",
	"violet"
];
function applyTheme(theme) {
	const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
	document.documentElement.classList.toggle("dark", theme === "dark" || theme === "system" && prefersDark);
}
function applyAccent(accent) {
	if (accent === "indigo") delete document.documentElement.dataset.accent;
	else document.documentElement.dataset.accent = accent;
}
function useAppearance(initialTheme = "system", initialAccent = "indigo") {
	const [theme, setThemeState] = useState(() => localStorage.getItem("theme") ?? initialTheme);
	const [accent, setAccentState] = useState(() => localStorage.getItem("accent") ?? initialAccent);
	useEffect(() => {
		applyTheme(theme);
		const media = window.matchMedia("(prefers-color-scheme: dark)");
		const listener = () => theme === "system" && applyTheme("system");
		media.addEventListener("change", listener);
		return () => media.removeEventListener("change", listener);
	}, [theme]);
	useEffect(() => applyAccent(accent), [accent]);
	return {
		theme,
		setTheme: useCallback((next) => {
			localStorage.setItem("theme", next);
			setThemeState(next);
		}, []),
		accent,
		setAccent: useCallback((next) => {
			localStorage.setItem("accent", next);
			setAccentState(next);
		}, [])
	};
}
//#endregion
//#region resources/js/layouts/app-layout.tsx
function DemoBanner() {
	const { tenant } = usePage().props;
	if (!tenant?.is_demo) return null;
	return /* @__PURE__ */ jsxs("div", {
		className: "flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-primary/20 bg-primary/8 px-4 py-2 text-center text-xs",
		children: [/* @__PURE__ */ jsx("span", {
			className: "text-foreground/85",
			children: "You’re exploring sample data. Tell us about your business and we’ll set this up with your numbers."
		}), /* @__PURE__ */ jsx(Link, {
			href: "/onboarding",
			className: "font-semibold text-primary underline-offset-2 hover:underline",
			children: "Build mine →"
		})]
	});
}
function Sidebar({ collapsed, onToggle }) {
	const { url, props } = usePage();
	const { can } = usePermissions();
	return /* @__PURE__ */ jsxs("aside", {
		className: cn("hidden shrink-0 flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-200 lg:flex", collapsed ? "w-[62px]" : "w-[228px]"),
		children: [
			/* @__PURE__ */ jsxs("div", {
				className: cn("flex h-14 items-center gap-2 px-3", collapsed && "justify-center px-0"),
				children: [/* @__PURE__ */ jsx("div", {
					className: "flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground",
					children: /* @__PURE__ */ jsx("span", {
						className: "text-sm font-bold",
						children: props.tenant?.name?.[0] ?? "L"
					})
				}), !collapsed && /* @__PURE__ */ jsxs("div", {
					className: "min-w-0",
					children: [/* @__PURE__ */ jsx("p", {
						className: "truncate text-sm font-semibold leading-tight",
						children: props.tenant?.name ?? "Ledgerloop"
					}), /* @__PURE__ */ jsxs("p", {
						className: "truncate text-[10px] uppercase tracking-wide text-muted-foreground",
						children: [props.tenant?.plan, " plan"]
					})]
				})]
			}),
			/* @__PURE__ */ jsx("nav", {
				className: "flex-1 space-y-4 overflow-y-auto px-2 py-2 scrollbar-thin",
				children: NAVIGATION.map((section) => {
					const items = section.items.filter((item) => !item.permission || can(item.permission));
					if (items.length === 0) return null;
					return /* @__PURE__ */ jsxs("div", {
						className: "space-y-0.5",
						children: [!collapsed && /* @__PURE__ */ jsx("p", {
							className: "px-2.5 pb-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground/70",
							children: section.label
						}), items.map((item) => {
							const active = url.startsWith(item.href);
							return /* @__PURE__ */ jsxs(Link, {
								href: item.href,
								title: collapsed ? item.label : void 0,
								className: cn("flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors", collapsed && "justify-center px-0", active ? "bg-sidebar-accent font-medium text-accent-foreground" : "text-sidebar-foreground hover:bg-sidebar-accent/60"),
								children: [/* @__PURE__ */ jsx(item.icon, { className: "size-4 shrink-0" }), !collapsed && /* @__PURE__ */ jsx("span", {
									className: "truncate",
									children: item.label
								})]
							}, item.href);
						})]
					}, section.label);
				})
			}),
			/* @__PURE__ */ jsx("div", {
				className: "border-t border-sidebar-border p-2",
				children: /* @__PURE__ */ jsxs(Button, {
					variant: "ghost",
					size: "sm",
					onClick: onToggle,
					className: cn("w-full gap-2 text-xs text-muted-foreground", collapsed && "justify-center px-0"),
					children: [collapsed ? /* @__PURE__ */ jsx(PanelLeftOpen, { className: "size-4" }) : /* @__PURE__ */ jsx(PanelLeftClose, { className: "size-4" }), !collapsed && "Collapse"]
				})
			})
		]
	});
}
function UserMenu() {
	const { auth } = usePage().props;
	const { theme, setTheme, accent, setAccent } = useAppearance(auth.user?.theme ?? "system", auth.user?.accent_color ?? "indigo");
	if (!auth.user) return null;
	return /* @__PURE__ */ jsxs(DropdownMenu, { children: [/* @__PURE__ */ jsx(DropdownMenuTrigger, {
		asChild: true,
		children: /* @__PURE__ */ jsxs(Button, {
			variant: "ghost",
			size: "sm",
			className: "gap-2 px-2",
			children: [/* @__PURE__ */ jsx("span", {
				className: "flex size-6 items-center justify-center rounded-full bg-primary/12 text-[11px] font-semibold text-primary",
				children: auth.user.name.slice(0, 2).toUpperCase()
			}), /* @__PURE__ */ jsx("span", {
				className: "hidden text-xs sm:inline",
				children: auth.user.name
			})]
		})
	}), /* @__PURE__ */ jsxs(DropdownMenuContent, {
		align: "end",
		className: "w-56",
		children: [
			/* @__PURE__ */ jsxs(DropdownMenuLabel, { children: [/* @__PURE__ */ jsx("p", {
				className: "text-xs font-semibold normal-case tracking-normal text-foreground",
				children: auth.user.name
			}), /* @__PURE__ */ jsx("p", {
				className: "text-[11px] font-normal normal-case tracking-normal text-muted-foreground",
				children: auth.user.role
			})] }),
			/* @__PURE__ */ jsx(DropdownMenuSeparator, {}),
			/* @__PURE__ */ jsxs("div", {
				className: "px-2.5 py-1.5",
				children: [/* @__PURE__ */ jsx("p", {
					className: "mb-1.5 text-[11px] font-medium text-muted-foreground",
					children: "Theme"
				}), /* @__PURE__ */ jsx("div", {
					className: "flex gap-1",
					children: [
						"light",
						"dark",
						"system"
					].map((option) => /* @__PURE__ */ jsx("button", {
						type: "button",
						onClick: () => setTheme(option),
						className: cn("flex-1 rounded-md border px-1.5 py-1 text-[11px] capitalize transition-colors", theme === option ? "border-primary bg-primary/10 text-primary" : "border-border hover:bg-accent"),
						children: option === "light" ? /* @__PURE__ */ jsx(Sun, { className: "mx-auto size-3" }) : option === "dark" ? /* @__PURE__ */ jsx(Moon, { className: "mx-auto size-3" }) : option
					}, option))
				})]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "px-2.5 py-1.5",
				children: [/* @__PURE__ */ jsx("p", {
					className: "mb-1.5 text-[11px] font-medium text-muted-foreground",
					children: "Accent"
				}), /* @__PURE__ */ jsx("div", {
					className: "flex gap-1.5",
					children: ACCENTS.map((option) => /* @__PURE__ */ jsx("button", {
						type: "button",
						"aria-label": `Use ${option} accent`,
						onClick: () => setAccent(option),
						"data-accent": option === "indigo" ? void 0 : option,
						className: cn("size-5 rounded-full border-2 bg-primary transition", accent === option ? "border-foreground" : "border-transparent")
					}, option))
				})]
			}),
			/* @__PURE__ */ jsx(DropdownMenuSeparator, {}),
			/* @__PURE__ */ jsx(DropdownMenuItem, {
				asChild: true,
				children: /* @__PURE__ */ jsxs(Link, {
					href: "/settings/profile",
					children: [/* @__PURE__ */ jsx(User, {}), "Profile & security"]
				})
			}),
			/* @__PURE__ */ jsxs(DropdownMenuItem, {
				onSelect: () => router.post("/logout"),
				children: [/* @__PURE__ */ jsx(LogOut, {}), "Sign out"]
			})
		]
	})] });
}
function AppLayout({ title, description, actions, showFilters = true, surface, filterExtras, breadcrumb, children }) {
	const { auth } = usePage().props;
	const currentUrl = usePage().url;
	const [collapsed, setCollapsed] = useState(() => localStorage.getItem("sidebar_collapsed") === "1");
	const { can } = usePermissions();
	useEffect(() => {
		localStorage.setItem("sidebar_collapsed", collapsed ? "1" : "0");
	}, [collapsed]);
	return /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsxs("div", {
		className: "flex min-h-screen bg-background",
		children: [/* @__PURE__ */ jsx(Sidebar, {
			collapsed,
			onToggle: () => setCollapsed((value) => !value)
		}), /* @__PURE__ */ jsxs("div", {
			className: "flex min-w-0 flex-1 flex-col",
			children: [
				/* @__PURE__ */ jsx(DemoBanner, {}),
				/* @__PURE__ */ jsxs("header", {
					className: "sticky top-0 z-30 border-b border-border bg-background/85 backdrop-blur-md",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "flex h-14 items-center gap-3 px-4 sm:px-6",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "min-w-0 flex-1",
							children: [
								breadcrumb && /* @__PURE__ */ jsxs(Link, {
									href: breadcrumb.href,
									className: "mb-0.5 inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground",
									children: [/* @__PURE__ */ jsx(ChevronLeft, { className: "size-3" }), breadcrumb.label]
								}),
								/* @__PURE__ */ jsx("h1", {
									className: "truncate text-base font-semibold tracking-tight",
									children: title
								}),
								description && /* @__PURE__ */ jsx("p", {
									className: "truncate text-xs text-muted-foreground",
									children: description
								})
							]
						}), /* @__PURE__ */ jsxs("div", {
							className: "flex items-center gap-1.5",
							children: [
								can("ai.chat.view") && auth.user && /* @__PURE__ */ jsxs("div", {
									className: "hidden items-center gap-1.5 rounded-full bg-primary/8 px-2.5 py-1 text-[11px] text-primary md:flex",
									children: [
										/* @__PURE__ */ jsx(Sparkles, { className: "size-3" }),
										auth.user.ai_credits_remaining,
										" credits left"
									]
								}),
								/* @__PURE__ */ jsx(SyncIndicator, {}),
								/* @__PURE__ */ jsx(Separator, {
									orientation: "vertical",
									className: "mx-0.5 h-5"
								}),
								/* @__PURE__ */ jsx(UserMenu, {})
							]
						})]
					}), (showFilters || actions) && /* @__PURE__ */ jsxs("div", {
						className: "flex flex-wrap items-center gap-2 border-t border-border/70 px-4 py-2 sm:px-6",
						children: [showFilters && /* @__PURE__ */ jsxs(Fragment, { children: [
							/* @__PURE__ */ jsx(DateRangePicker, {}),
							/* @__PURE__ */ jsx(ChannelFilter, {}),
							/* @__PURE__ */ jsx(SavedViews, { surface: surface ?? surfaceFromUrl(currentUrl) }),
							filterExtras
						] }), /* @__PURE__ */ jsxs("div", {
							className: "ml-auto flex items-center gap-1.5",
							children: [actions, /* @__PURE__ */ jsxs(Button, {
								variant: "outline",
								size: "sm",
								className: "hidden gap-1.5 text-xs text-muted-foreground sm:inline-flex",
								onClick: () => document.dispatchEvent(new KeyboardEvent("keydown", {
									key: "k",
									metaKey: true,
									bubbles: true
								})),
								children: [/* @__PURE__ */ jsx(Command, { className: "size-3" }), "K"]
							})]
						})]
					})]
				}),
				/* @__PURE__ */ jsx("main", {
					className: "flex-1 space-y-4 p-4 sm:p-6",
					children
				})
			]
		})]
	}), /* @__PURE__ */ jsx(CommandPalette, {})] });
}
/**
* Saved views belong to a screen, so the surface key is the path without its
* query string or trailing ids: /reports/channel-scorecard, /operations.
*/
function surfaceFromUrl(url) {
	const path = url.split("?")[0].replace(/\/$/, "");
	return path === "" ? "/dashboard" : path;
}
//#endregion
export { SelectContent as a, SelectValue as c, Select as i, REPORT_LINKS as l, ACCENTS as n, SelectItem as o, useAppearance as r, SelectTrigger as s, AppLayout as t };

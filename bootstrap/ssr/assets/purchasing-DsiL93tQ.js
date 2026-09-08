import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { a as apiSend, i as apiGet, n as WidgetError, s as usePermissions } from "./empty-state-DjIQjBC7.js";
import { f as formatNumber, o as formatCurrency, s as formatDate, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { i as VerdictNote, t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useState } from "react";
import { Check, Loader2, PackageCheck, Plus, Send, Trash2, TriangleAlert, X } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/inventory/purchasing.tsx
var STATUS_TONE = {
	draft: "muted",
	sent: "warn",
	partial: "warn",
	received: "good",
	cancelled: "muted"
};
function Purchasing() {
	const { can } = usePermissions();
	const [tab, setTab] = useState("orders");
	const [orders, setOrders] = useState(null);
	const [suppliers, setSuppliers] = useState(null);
	const [suggestions, setSuggestions] = useState(null);
	const [error, setError] = useState(null);
	const [detail, setDetail] = useState(null);
	const [composing, setComposing] = useState(false);
	const load = useCallback(() => {
		apiGet("/purchasing/orders").then((response) => {
			setOrders(response.data);
			setError(null);
		}).catch((err) => setError(err instanceof Error ? err.message : "Could not load purchase orders."));
		apiGet("/purchasing/suppliers").then((response) => setSuppliers(response.data.rows)).catch(() => setSuppliers([]));
		apiGet("/purchasing/orders/suggestions").then((response) => setSuggestions(response.data)).catch(() => setSuggestions(null));
	}, []);
	useEffect(load, [load]);
	const openOrder = async (id) => {
		try {
			const response = await apiGet(`/purchasing/orders/${id}`);
			setDetail(response.data);
		} catch {
			toast.error("Could not open that order.");
		}
	};
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Purchasing",
		description: "Suppliers, purchase orders and what is on its way in",
		showFilters: false,
		breadcrumb: {
			label: "Inventory",
			href: "/inventory"
		},
		actions: /* @__PURE__ */ jsx(PermissionGuard, {
			permission: "catalog.purchase_orders.manage",
			children: /* @__PURE__ */ jsxs(Button, {
				size: "sm",
				onClick: () => setComposing(true),
				children: [/* @__PURE__ */ jsx(Plus, { className: "size-3.5" }), " New purchase order"]
			})
		}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Purchasing" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: load
			}),
			!orders && !error && /* @__PURE__ */ jsx(SkeletonChart, { className: "h-64" }),
			orders && /* @__PURE__ */ jsxs(Fragment, { children: [
				/* @__PURE__ */ jsx(VerdictNote, { verdict: orders.verdict }),
				/* @__PURE__ */ jsxs("div", {
					className: "grid gap-3 sm:grid-cols-3",
					children: [
						/* @__PURE__ */ jsxs(Card, {
							className: "p-4",
							children: [/* @__PURE__ */ jsx("p", {
								className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
								children: "Open orders"
							}), /* @__PURE__ */ jsx("p", {
								className: "mt-1.5 text-xl font-semibold tnum",
								children: formatNumber(orders.summary.open ?? 0)
							})]
						}),
						/* @__PURE__ */ jsxs(Card, {
							className: "p-4",
							children: [/* @__PURE__ */ jsx("p", {
								className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
								children: "Value inbound"
							}), /* @__PURE__ */ jsx("p", {
								className: "mt-1.5 text-xl font-semibold tnum",
								children: formatCurrency(orders.summary.open_value ?? 0)
							})]
						}),
						/* @__PURE__ */ jsxs(Card, {
							className: "p-4",
							children: [/* @__PURE__ */ jsx("p", {
								className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
								children: "Overdue"
							}), /* @__PURE__ */ jsx("p", {
								className: cn("mt-1.5 text-xl font-semibold tnum", (orders.summary.overdue ?? 0) > 0 && "text-bad"),
								children: formatNumber(orders.summary.overdue ?? 0)
							})]
						})
					]
				}),
				/* @__PURE__ */ jsx(Tabs, {
					value: tab,
					onValueChange: setTab,
					children: /* @__PURE__ */ jsxs(TabsList, { children: [
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "orders",
							children: "Purchase orders"
						}),
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "suggestions",
							children: "What to reorder"
						}),
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "suppliers",
							children: "Suppliers"
						})
					] })
				}),
				tab === "orders" && /* @__PURE__ */ jsx(ChartCard, {
					title: "Purchase orders",
					subtitle: "Newest first",
					bodyClassName: "p-0",
					empty: orders.rows.length === 0,
					children: /* @__PURE__ */ jsx(DataTable, {
						columns: [
							{
								key: "po_number",
								header: "PO",
								sortable: true,
								value: (row) => row.po_number,
								render: (row) => /* @__PURE__ */ jsx("button", {
									type: "button",
									className: "text-xs font-medium text-primary hover:underline",
									onClick: () => openOrder(row.id),
									children: row.po_number
								})
							},
							{
								key: "supplier",
								header: "Supplier",
								render: (row) => row.supplier ?? "—"
							},
							{
								key: "status",
								header: "Status",
								render: (row) => /* @__PURE__ */ jsxs("div", {
									className: "flex items-center gap-1.5",
									children: [/* @__PURE__ */ jsx(Badge, {
										variant: STATUS_TONE[row.status] ?? "muted",
										children: row.status
									}), row.is_overdue && /* @__PURE__ */ jsx(TriangleAlert, { className: "size-3.5 text-bad" })]
								})
							},
							{
								key: "items",
								header: "Lines",
								align: "right",
								sortable: true,
								value: (row) => row.items,
								render: (row) => formatNumber(row.items)
							},
							{
								key: "expected_at",
								header: "Expected",
								sortable: true,
								value: (row) => row.expected_at ?? "",
								render: (row) => row.expected_at ? /* @__PURE__ */ jsx("span", {
									className: cn(row.is_overdue && "text-bad"),
									children: formatDate(row.expected_at)
								}) : "—"
							},
							{
								key: "total",
								header: "Total",
								align: "right",
								sortable: true,
								value: (row) => row.total,
								render: (row) => formatCurrency(row.total)
							}
						],
						rows: orders.rows,
						searchable: true,
						rowKey: (row) => row.id,
						dense: true
					})
				}),
				tab === "suggestions" && suggestions && /* @__PURE__ */ jsxs("div", {
					className: "space-y-3",
					children: [
						/* @__PURE__ */ jsx(CaveatNote, { caveat: suggestions.caveat }),
						suggestions.groups.length === 0 && /* @__PURE__ */ jsx("p", {
							className: "py-10 text-center text-sm text-muted-foreground",
							children: "Nothing is below its reorder point right now."
						}),
						suggestions.groups.map((group) => /* @__PURE__ */ jsx(ChartCard, {
							title: group.supplier,
							subtitle: `${group.skus} SKUs · ${formatNumber(group.units)} units · ${formatCurrency(group.cost)}`,
							bodyClassName: "p-0",
							children: /* @__PURE__ */ jsx(DataTable, {
								columns: [
									{
										key: "sku_code",
										header: "SKU",
										render: (row) => row.sku_code
									},
									{
										key: "name",
										header: "Product",
										render: (row) => row.name
									},
									{
										key: "on_hand",
										header: "On hand",
										align: "right",
										render: (row) => formatNumber(row.on_hand)
									},
									{
										key: "suggested_quantity",
										header: "Suggested",
										align: "right",
										render: (row) => formatNumber(row.suggested_quantity)
									},
									{
										key: "cost",
										header: "Cost",
										align: "right",
										render: (row) => formatCurrency(row.suggested_quantity * row.cost_price)
									}
								],
								rows: group.rows,
								rowKey: (row) => row.sku_id,
								dense: true
							})
						}, group.supplier))
					]
				}),
				tab === "suppliers" && /* @__PURE__ */ jsx(SuppliersPanel, {
					suppliers,
					canManage: can("catalog.suppliers.manage"),
					onSaved: load
				})
			] }),
			/* @__PURE__ */ jsx(OrderSheet, {
				detail,
				onClose: () => setDetail(null),
				onChanged: () => {
					load();
					setDetail(null);
				}
			}),
			/* @__PURE__ */ jsx(ComposeSheet, {
				open: composing,
				suppliers: suppliers ?? [],
				onOpenChange: setComposing,
				onSaved: load
			})
		]
	});
}
function SuppliersPanel({ suppliers, canManage, onSaved }) {
	const [name, setName] = useState("");
	const [contact, setContact] = useState("");
	const [phone, setPhone] = useState("");
	const [leadTime, setLeadTime] = useState("7");
	const [saving, setSaving] = useState(false);
	const save = async () => {
		setSaving(true);
		try {
			const response = await apiSend("POST", "/purchasing/suppliers", {
				name,
				contact_name: contact || null,
				phone: phone || null,
				lead_time_days: Number(leadTime)
			});
			toast.success(response.message);
			setName("");
			setContact("");
			setPhone("");
			onSaved();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save the supplier.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsxs("div", {
		className: "grid gap-4 lg:grid-cols-[1fr_20rem]",
		children: [/* @__PURE__ */ jsx(ChartCard, {
			title: "Suppliers",
			bodyClassName: "p-0",
			empty: (suppliers?.length ?? 0) === 0,
			children: /* @__PURE__ */ jsx(DataTable, {
				columns: [
					{
						key: "name",
						header: "Supplier",
						render: (row) => /* @__PURE__ */ jsx("span", {
							className: "text-xs font-medium",
							children: row.name
						})
					},
					{
						key: "contact_name",
						header: "Contact",
						render: (row) => row.contact_name ?? "—"
					},
					{
						key: "phone",
						header: "Phone",
						render: (row) => row.phone ?? "—"
					},
					{
						key: "city",
						header: "City",
						render: (row) => row.city ?? "—"
					},
					{
						key: "lead_time_days",
						header: "Lead time",
						align: "right",
						render: (row) => `${row.lead_time_days}d`
					},
					{
						key: "skus",
						header: "SKUs",
						align: "right",
						render: (row) => formatNumber(row.skus)
					},
					{
						key: "purchase_orders",
						header: "POs",
						align: "right",
						render: (row) => formatNumber(row.purchase_orders)
					}
				],
				rows: suppliers ?? [],
				searchable: true,
				rowKey: (row) => row.id,
				dense: true
			})
		}), canManage && /* @__PURE__ */ jsxs(Card, {
			className: "space-y-2 p-4",
			children: [
				/* @__PURE__ */ jsx("p", {
					className: "text-sm font-semibold",
					children: "Add a supplier"
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [/* @__PURE__ */ jsx(Label, {
						htmlFor: "sup-name",
						children: "Name"
					}), /* @__PURE__ */ jsx(Input, {
						id: "sup-name",
						value: name,
						onChange: (event) => setName(event.target.value),
						placeholder: "Jaipur Textiles"
					})]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [/* @__PURE__ */ jsx(Label, {
						htmlFor: "sup-contact",
						children: "Contact person"
					}), /* @__PURE__ */ jsx(Input, {
						id: "sup-contact",
						value: contact,
						onChange: (event) => setContact(event.target.value)
					})]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "grid grid-cols-2 gap-2",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "sup-phone",
							children: "Phone"
						}), /* @__PURE__ */ jsx(Input, {
							id: "sup-phone",
							value: phone,
							onChange: (event) => setPhone(event.target.value)
						})]
					}), /* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "sup-lead",
							children: "Lead time (days)"
						}), /* @__PURE__ */ jsx(Input, {
							id: "sup-lead",
							type: "number",
							value: leadTime,
							onChange: (event) => setLeadTime(event.target.value)
						})]
					})]
				}),
				/* @__PURE__ */ jsxs(Button, {
					size: "sm",
					className: "w-full",
					onClick: save,
					disabled: saving || name.trim() === "",
					children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }), " Add supplier"]
				})
			]
		})]
	});
}
function OrderSheet({ detail, onClose, onChanged }) {
	const { can } = usePermissions();
	const [received, setReceived] = useState({});
	const [busy, setBusy] = useState(false);
	useEffect(() => {
		if (detail) setReceived(Object.fromEntries(detail.items.map((item) => [item.id, String(item.outstanding)])));
	}, [detail]);
	if (!detail) return null;
	const act = async (action) => {
		setBusy(true);
		try {
			const payload = action === "receive" ? { received: Object.fromEntries(Object.entries(received).map(([id, value]) => [id, Number(value) || 0])) } : {};
			const response = await apiSend("POST", `/purchasing/orders/${detail.order.id}/${action}`, payload);
			toast.success(response.message);
			onChanged();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "That did not work.");
		} finally {
			setBusy(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open: true,
		onOpenChange: (open) => !open && onClose(),
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-2xl",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, {
				className: "flex items-center gap-2",
				children: [detail.order.po_number, /* @__PURE__ */ jsx(Badge, {
					variant: STATUS_TONE[detail.order.status] ?? "muted",
					children: detail.order.status
				})]
			}), /* @__PURE__ */ jsxs(SheetDescription, { children: [
				detail.order.supplier ?? "No supplier",
				" → ",
				detail.order.location ?? "default location",
				detail.order.expected_at && ` · expected ${formatDate(detail.order.expected_at)}`
			] })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-3 overflow-y-auto px-4",
				children: [
					/* @__PURE__ */ jsx("div", {
						className: "space-y-1.5",
						children: detail.items.map((item) => /* @__PURE__ */ jsx("div", {
							className: "rounded-lg border border-border p-2.5",
							children: /* @__PURE__ */ jsxs("div", {
								className: "flex items-start justify-between gap-3",
								children: [/* @__PURE__ */ jsxs("div", {
									className: "min-w-0",
									children: [
										/* @__PURE__ */ jsx("p", {
											className: "truncate text-xs font-medium",
											children: item.sku_code
										}),
										/* @__PURE__ */ jsx("p", {
											className: "truncate text-[11px] text-muted-foreground",
											children: item.name
										}),
										/* @__PURE__ */ jsxs("p", {
											className: "mt-1 text-[11px] text-muted-foreground",
											children: [
												formatNumber(item.quantity_ordered),
												" ordered · ",
												formatNumber(item.quantity_received),
												" received · landed ",
												formatCurrency(item.landed_unit_cost),
												"/unit",
												item.landed_unit_cost !== item.current_cost && /* @__PURE__ */ jsxs("span", {
													className: "ml-1 text-warn",
													children: [
														"(current cost ",
														formatCurrency(item.current_cost),
														")"
													]
												})
											]
										})
									]
								}), detail.order.is_receivable && can("catalog.purchase_orders.manage") && /* @__PURE__ */ jsxs("div", {
									className: "w-24 shrink-0",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: `recv-${item.id}`,
										className: "text-[10px]",
										children: "Receive now"
									}), /* @__PURE__ */ jsx(Input, {
										id: `recv-${item.id}`,
										type: "number",
										min: 0,
										max: item.outstanding,
										value: received[item.id] ?? "",
										onChange: (event) => setReceived((current) => ({
											...current,
											[item.id]: event.target.value
										}))
									})]
								})]
							})
						}, item.id))
					}),
					/* @__PURE__ */ jsxs(Card, {
						className: "space-y-1 p-3 text-xs",
						children: [
							/* @__PURE__ */ jsxs("div", {
								className: "flex justify-between",
								children: [/* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: "Subtotal"
								}), /* @__PURE__ */ jsx("span", {
									className: "tnum",
									children: formatCurrency(detail.order.subtotal)
								})]
							}),
							/* @__PURE__ */ jsxs("div", {
								className: "flex justify-between",
								children: [/* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: "Freight & other"
								}), /* @__PURE__ */ jsx("span", {
									className: "tnum",
									children: formatCurrency(detail.order.freight_cost)
								})]
							}),
							/* @__PURE__ */ jsxs("div", {
								className: "flex justify-between",
								children: [/* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: "Tax"
								}), /* @__PURE__ */ jsx("span", {
									className: "tnum",
									children: formatCurrency(detail.order.tax_amount)
								})]
							}),
							/* @__PURE__ */ jsxs("div", {
								className: "flex justify-between border-t border-border pt-1 font-semibold",
								children: [/* @__PURE__ */ jsx("span", { children: "Total" }), /* @__PURE__ */ jsx("span", {
									className: "tnum",
									children: formatCurrency(detail.order.total)
								})]
							})
						]
					}),
					can("catalog.purchase_orders.manage") && /* @__PURE__ */ jsxs("div", {
						className: "flex flex-wrap gap-2",
						children: [
							detail.order.is_editable && /* @__PURE__ */ jsxs(Button, {
								size: "sm",
								onClick: () => act("send"),
								disabled: busy,
								children: [/* @__PURE__ */ jsx(Send, { className: "size-3.5" }), " Mark as sent"]
							}),
							detail.order.is_receivable && /* @__PURE__ */ jsxs(Button, {
								size: "sm",
								onClick: () => act("receive"),
								disabled: busy,
								children: [busy ? /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }) : /* @__PURE__ */ jsx(PackageCheck, { className: "size-3.5" }), " Receive stock"]
							}),
							detail.order.status !== "received" && detail.order.status !== "cancelled" && /* @__PURE__ */ jsxs(Button, {
								size: "sm",
								variant: "ghost",
								className: "text-bad",
								onClick: () => act("cancel"),
								disabled: busy,
								children: [/* @__PURE__ */ jsx(X, { className: "size-3.5" }), " Cancel"]
							})
						]
					}),
					/* @__PURE__ */ jsx("p", {
						className: "text-[11px] text-muted-foreground",
						children: "Receiving books the stock in and re-averages each SKU's cost at the landed price — freight included."
					})
				]
			})]
		})
	});
}
function ComposeSheet({ open, suppliers, onOpenChange, onSaved }) {
	const [supplierId, setSupplierId] = useState("");
	const [expected, setExpected] = useState("");
	const [freight, setFreight] = useState("0");
	const [lines, setLines] = useState([{
		sku_code: "",
		quantity: "",
		unit_cost: ""
	}]);
	const [skus, setSkus] = useState([]);
	const [saving, setSaving] = useState(false);
	useEffect(() => {
		if (open) apiGet("/inventory/levels").then((response) => setSkus(response.data.rows)).catch(() => setSkus([]));
	}, [open]);
	const save = async () => {
		const items = lines.filter((line) => line.sku_code && line.quantity).map((line) => {
			return {
				sku_id: skus.find((item) => item.sku_code === line.sku_code)?.sku_id,
				quantity: Number(line.quantity),
				unit_cost: Number(line.unit_cost || 0)
			};
		}).filter((item) => item.sku_id);
		if (items.length === 0) {
			toast.error("Add at least one line.");
			return;
		}
		setSaving(true);
		try {
			const response = await apiSend("POST", "/purchasing/orders", {
				supplier_id: supplierId ? Number(supplierId) : null,
				expected_at: expected || null,
				freight_cost: Number(freight || 0),
				items
			});
			toast.success(response.message);
			setLines([{
				sku_code: "",
				quantity: "",
				unit_cost: ""
			}]);
			onSaved();
			onOpenChange(false);
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save the order.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-xl",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "New purchase order" }), /* @__PURE__ */ jsx(SheetDescription, { children: "Freight is spread across the lines by value, so landed cost per unit is right." })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-3 overflow-y-auto px-4",
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "grid grid-cols-2 gap-2",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, { children: "Supplier" }), /* @__PURE__ */ jsxs(Select, {
								value: supplierId,
								onValueChange: setSupplierId,
								children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Pick one" }) }), /* @__PURE__ */ jsx(SelectContent, { children: suppliers.map((supplier) => /* @__PURE__ */ jsx(SelectItem, {
									value: String(supplier.id),
									children: supplier.name
								}, supplier.id)) })]
							})]
						}), /* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, {
								htmlFor: "po-expected",
								children: "Expected on"
							}), /* @__PURE__ */ jsx(Input, {
								id: "po-expected",
								type: "date",
								value: expected,
								onChange: (event) => setExpected(event.target.value)
							})]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "po-freight",
							children: "Freight & other costs (₹)"
						}), /* @__PURE__ */ jsx(Input, {
							id: "po-freight",
							type: "number",
							value: freight,
							onChange: (event) => setFreight(event.target.value)
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-2",
						children: [
							/* @__PURE__ */ jsx(Label, { children: "Lines" }),
							lines.map((line, index) => /* @__PURE__ */ jsxs("div", {
								className: "flex items-end gap-1.5",
								children: [
									/* @__PURE__ */ jsx("div", {
										className: "flex-1 space-y-1",
										children: /* @__PURE__ */ jsx(Input, {
											list: "sku-options",
											placeholder: "SKU code",
											value: line.sku_code,
											onChange: (event) => setLines((current) => current.map((item, position) => position === index ? {
												...item,
												sku_code: event.target.value
											} : item))
										})
									}),
									/* @__PURE__ */ jsx("div", {
										className: "w-20 space-y-1",
										children: /* @__PURE__ */ jsx(Input, {
											type: "number",
											placeholder: "Qty",
											value: line.quantity,
											onChange: (event) => setLines((current) => current.map((item, position) => position === index ? {
												...item,
												quantity: event.target.value
											} : item))
										})
									}),
									/* @__PURE__ */ jsx("div", {
										className: "w-24 space-y-1",
										children: /* @__PURE__ */ jsx(Input, {
											type: "number",
											placeholder: "₹ / unit",
											value: line.unit_cost,
											onChange: (event) => setLines((current) => current.map((item, position) => position === index ? {
												...item,
												unit_cost: event.target.value
											} : item))
										})
									}),
									/* @__PURE__ */ jsx(Button, {
										variant: "ghost",
										size: "icon",
										"aria-label": "Remove line",
										onClick: () => setLines((current) => current.filter((_, position) => position !== index)),
										children: /* @__PURE__ */ jsx(Trash2, { className: "size-4" })
									})
								]
							}, index)),
							/* @__PURE__ */ jsx("datalist", {
								id: "sku-options",
								children: skus.map((sku) => /* @__PURE__ */ jsx("option", {
									value: sku.sku_code,
									children: sku.name
								}, sku.sku_id))
							}),
							/* @__PURE__ */ jsxs(Button, {
								variant: "outline",
								size: "sm",
								onClick: () => setLines((current) => [...current, {
									sku_code: "",
									quantity: "",
									unit_cost: ""
								}]),
								children: [/* @__PURE__ */ jsx(Plus, { className: "size-3.5" }), " Add line"]
							})
						]
					}),
					/* @__PURE__ */ jsxs(Button, {
						className: "w-full",
						onClick: save,
						disabled: saving,
						children: [saving ? /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }) : /* @__PURE__ */ jsx(Check, { className: "size-3.5" }), " Save as draft"]
					})
				]
			})]
		})
	});
}
//#endregion
export { Purchasing as default };

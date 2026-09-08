import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { a as apiSend, i as apiGet, n as WidgetError, s as usePermissions } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, f as formatNumber, o as formatCurrency, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useState } from "react";
import { CheckCheck, ClipboardList, Loader2, Plus } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/inventory/counts.tsx
function StockCounts() {
	const { can } = usePermissions();
	const [rows, setRows] = useState(null);
	const [error, setError] = useState(null);
	const [detail, setDetail] = useState(null);
	const [creating, setCreating] = useState(false);
	const load = useCallback(() => {
		apiGet("/inventory/counts").then((response) => {
			setRows(response.data.rows);
			setError(null);
		}).catch((err) => setError(err instanceof Error ? err.message : "Could not load counts."));
	}, []);
	useEffect(load, [load]);
	const open = async (id) => {
		try {
			const response = await apiGet(`/inventory/counts/${id}`);
			setDetail(response.data);
		} catch {
			toast.error("Could not open that count.");
		}
	};
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Stock counts",
		description: "Count the shelf, compare it with the system, correct the difference",
		showFilters: false,
		breadcrumb: {
			label: "Inventory",
			href: "/inventory"
		},
		actions: /* @__PURE__ */ jsx(PermissionGuard, {
			permission: "catalog.stock_counts.manage",
			children: /* @__PURE__ */ jsxs(Button, {
				size: "sm",
				onClick: () => setCreating(true),
				children: [/* @__PURE__ */ jsx(Plus, { className: "size-3.5" }), " Start a count"]
			})
		}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Stock counts" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: load
			}),
			!rows && !error && /* @__PURE__ */ jsx(SkeletonChart, { className: "h-64" }),
			rows && /* @__PURE__ */ jsx(ChartCard, {
				title: "Counts",
				subtitle: "A count freezes what the system believed when it was opened, so the variance is measured against that moment",
				bodyClassName: "p-0",
				empty: rows.length === 0,
				emptyState: /* @__PURE__ */ jsxs("div", {
					className: "p-10 text-center",
					children: [/* @__PURE__ */ jsx(ClipboardList, { className: "mx-auto size-8 text-muted-foreground" }), /* @__PURE__ */ jsx("p", {
						className: "mt-2 text-sm text-muted-foreground",
						children: "No counts yet. Start one to reconcile the shelf with the system."
					})]
				}),
				children: /* @__PURE__ */ jsx(DataTable, {
					columns: [
						{
							key: "reference",
							header: "Reference",
							sortable: true,
							value: (row) => row.reference,
							render: (row) => /* @__PURE__ */ jsx("button", {
								type: "button",
								className: "text-xs font-medium text-primary hover:underline",
								onClick: () => open(row.id),
								children: row.reference
							})
						},
						{
							key: "status",
							header: "Status",
							render: (row) => /* @__PURE__ */ jsx(Badge, {
								variant: row.status === "applied" ? "good" : "warn",
								children: row.status
							})
						},
						{
							key: "scope",
							header: "Scope",
							render: (row) => row.scope
						},
						{
							key: "location",
							header: "Location",
							render: (row) => row.location ?? "—"
						},
						{
							key: "items",
							header: "SKUs",
							align: "right",
							sortable: true,
							value: (row) => row.items,
							render: (row) => formatNumber(row.items)
						},
						{
							key: "created_at",
							header: "Opened",
							render: (row) => row.created_at ? formatDateTime(row.created_at) : "—"
						},
						{
							key: "applied_at",
							header: "Applied",
							render: (row) => row.applied_at ? formatDateTime(row.applied_at) : "—"
						}
					],
					rows,
					rowKey: (row) => row.id,
					dense: true
				})
			}),
			/* @__PURE__ */ jsx(CountSheet, {
				detail,
				canManage: can("catalog.stock_counts.manage"),
				onClose: () => setDetail(null),
				onChanged: () => {
					load();
					setDetail(null);
				}
			}),
			/* @__PURE__ */ jsx(CreateSheet, {
				open: creating,
				onOpenChange: setCreating,
				onCreated: (id) => {
					load();
					open(id);
				}
			})
		]
	});
}
function CountSheet({ detail, canManage, onClose, onChanged }) {
	const [counts, setCounts] = useState({});
	const [reasons, setReasons] = useState({});
	const [busy, setBusy] = useState(false);
	useEffect(() => {
		if (detail) {
			setCounts(Object.fromEntries(detail.items.map((item) => [item.id, item.counted_quantity === null ? "" : String(item.counted_quantity)])));
			setReasons(Object.fromEntries(detail.items.map((item) => [item.id, item.reason ?? ""])));
		}
	}, [detail]);
	if (!detail) return null;
	const applied = detail.count.status === "applied";
	const save = async (thenApply) => {
		setBusy(true);
		try {
			await apiSend("PUT", `/inventory/counts/${detail.count.id}`, { items: detail.items.map((item) => ({
				id: item.id,
				counted_quantity: counts[item.id] === "" ? null : Number(counts[item.id]),
				reason: reasons[item.id] || null
			})) });
			if (!thenApply) {
				toast.success("Count saved.");
				setBusy(false);
				return;
			}
			const response = await apiSend("POST", `/inventory/counts/${detail.count.id}/apply`);
			toast.success(response.message);
			onChanged();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save the count.");
		} finally {
			setBusy(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open: true,
		onOpenChange: (open) => !open && onClose(),
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-3xl",
			children: [
				/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, {
					className: "flex items-center gap-2",
					children: [detail.count.reference, /* @__PURE__ */ jsx(Badge, {
						variant: applied ? "good" : "warn",
						children: detail.count.status
					})]
				}), /* @__PURE__ */ jsxs(SheetDescription, { children: [
					detail.count.location ?? "Default location",
					" · ",
					detail.summary.counted,
					" of ",
					detail.summary.total,
					" counted. Leave a line blank if nobody counted it — blank is not zero."
				] })] }),
				/* @__PURE__ */ jsx("div", {
					className: "space-y-2 overflow-y-auto px-4",
					children: detail.items.map((item) => {
						const typed = counts[item.id];
						const variance = typed === "" || typed === void 0 ? null : Number(typed) - item.expected_quantity;
						return /* @__PURE__ */ jsxs("div", {
							className: "flex flex-wrap items-end gap-2 rounded-lg border border-border p-2.5",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "min-w-0 flex-1",
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
											className: "mt-0.5 text-[11px] text-muted-foreground",
											children: ["System says ", formatNumber(item.expected_quantity)]
										})
									]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "w-24",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: `count-${item.id}`,
										className: "text-[10px]",
										children: "Counted"
									}), /* @__PURE__ */ jsx(Input, {
										id: `count-${item.id}`,
										type: "number",
										min: 0,
										disabled: applied || !canManage,
										value: typed ?? "",
										onChange: (event) => setCounts((current) => ({
											...current,
											[item.id]: event.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "w-16 text-right",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-[10px] text-muted-foreground",
										children: "Variance"
									}), /* @__PURE__ */ jsx("p", {
										className: cn("text-sm font-semibold tnum", variance === null && "text-muted-foreground", (variance ?? 0) < 0 && "text-bad", (variance ?? 0) > 0 && "text-good"),
										children: variance === null ? "—" : `${variance > 0 ? "+" : ""}${variance}`
									})]
								}),
								variance !== null && variance !== 0 && !applied && /* @__PURE__ */ jsxs("div", {
									className: "w-40",
									children: [/* @__PURE__ */ jsx(Label, {
										className: "text-[10px]",
										children: "Reason"
									}), /* @__PURE__ */ jsxs(Select, {
										value: reasons[item.id] ?? "",
										onValueChange: (value) => setReasons((current) => ({
											...current,
											[item.id]: value
										})),
										children: [/* @__PURE__ */ jsx(SelectTrigger, {
											className: "h-9",
											children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Why?" })
										}), /* @__PURE__ */ jsx(SelectContent, { children: detail.reasons.map((reason) => /* @__PURE__ */ jsx(SelectItem, {
											value: reason.value,
											children: reason.label
										}, reason.value)) })]
									})]
								})
							]
						}, item.id);
					})
				}),
				!applied && canManage && /* @__PURE__ */ jsxs("div", {
					className: "flex gap-2 border-t border-border px-4 py-3",
					children: [/* @__PURE__ */ jsx(Button, {
						variant: "outline",
						size: "sm",
						onClick: () => save(false),
						disabled: busy,
						children: "Save progress"
					}), /* @__PURE__ */ jsxs(Button, {
						size: "sm",
						onClick: () => save(true),
						disabled: busy,
						children: [busy ? /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }) : /* @__PURE__ */ jsx(CheckCheck, { className: "size-3.5" }), " Apply to stock"]
					})]
				}),
				applied && /* @__PURE__ */ jsx("div", {
					className: "px-4 py-3",
					children: /* @__PURE__ */ jsxs(Card, {
						className: "p-3 text-xs",
						children: [
							"Applied ",
							detail.count.applied_at ? formatDateTime(detail.count.applied_at) : "",
							" · net variance ",
							formatNumber(detail.summary.variance_units),
							" units (",
							formatCurrency(detail.summary.variance_value),
							" at cost)."
						]
					})
				})
			]
		})
	});
}
function CreateSheet({ open, onOpenChange, onCreated }) {
	const [scope, setScope] = useState("full");
	const [category, setCategory] = useState("");
	const [saving, setSaving] = useState(false);
	const create = async () => {
		setSaving(true);
		try {
			const response = await apiSend("POST", "/inventory/counts", {
				scope,
				category: scope === "category" ? category : null
			});
			toast.success(response.message);
			onOpenChange(false);
			onCreated(response.data.id);
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not start the count.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-md",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Start a stock count" }), /* @__PURE__ */ jsx(SheetDescription, { children: "The sheet records what the system believes right now, so a slow count is still measured against the right baseline." })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-3 px-4",
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "Scope" }), /* @__PURE__ */ jsxs(Select, {
							value: scope,
							onValueChange: setScope,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsxs(SelectContent, { children: [
								/* @__PURE__ */ jsx(SelectItem, {
									value: "full",
									children: "Everything"
								}),
								/* @__PURE__ */ jsx(SelectItem, {
									value: "category",
									children: "One category"
								}),
								/* @__PURE__ */ jsx(SelectItem, {
									value: "reorder",
									children: "Only what needs reordering"
								})
							] })]
						})]
					}),
					scope === "category" && /* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "count-category",
							children: "Category"
						}), /* @__PURE__ */ jsx(Input, {
							id: "count-category",
							value: category,
							onChange: (event) => setCategory(event.target.value),
							placeholder: "Ethnic Wear"
						})]
					}),
					/* @__PURE__ */ jsxs(Button, {
						className: "w-full",
						onClick: create,
						disabled: saving,
						children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }), " Open count sheet"]
					})
				]
			})]
		})
	});
}
//#endregion
export { StockCounts as default };

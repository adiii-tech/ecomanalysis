import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { a as apiSend, i as apiGet, n as WidgetError, s as usePermissions } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, f as formatNumber, o as formatCurrency, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { i as VerdictNote, t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { Head, Link } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useMemo, useState } from "react";
import { ArrowRightLeft, ClipboardList, Loader2, Pencil, Plus, ScrollText, Truck, Upload, Warehouse } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/inventory/index.tsx
var FILTERS = [
	{
		key: "all",
		label: "All"
	},
	{
		key: "reorder",
		label: "Needs reorder"
	},
	{
		key: "out_of_stock",
		label: "Out of stock"
	},
	{
		key: "overstock",
		label: "Overstock"
	},
	{
		key: "untracked",
		label: "Not tracked"
	}
];
function InventoryIndex() {
	const { can } = usePermissions();
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [loading, setLoading] = useState(true);
	const [filter, setFilter] = useState("all");
	const [locationId, setLocationId] = useState("all");
	const [adjusting, setAdjusting] = useState(null);
	const [ledgerFor, setLedgerFor] = useState(null);
	const [importing, setImporting] = useState(false);
	const [transferring, setTransferring] = useState(false);
	const [locationsOpen, setLocationsOpen] = useState(false);
	const load = useCallback(() => {
		setLoading(true);
		apiGet("/inventory/levels", {
			filter,
			location_id: locationId === "all" ? "" : locationId
		}).then((response) => {
			setData(response.data);
			setError(null);
		}).catch((err) => setError(err instanceof Error ? err.message : "Could not load stock.")).finally(() => setLoading(false));
	}, [filter, locationId]);
	useEffect(load, [load]);
	const summary = data?.summary;
	const columns = useMemo(() => [
		{
			key: "sku_code",
			header: "SKU",
			sortable: true,
			value: (row) => row.sku_code,
			render: (row) => /* @__PURE__ */ jsxs("div", {
				className: "min-w-0",
				children: [/* @__PURE__ */ jsx("p", {
					className: "truncate text-xs font-medium",
					children: row.sku_code
				}), /* @__PURE__ */ jsx("p", {
					className: "truncate text-[11px] text-muted-foreground",
					children: row.name
				})]
			})
		},
		{
			key: "supplier_name",
			header: "Supplier",
			render: (row) => row.supplier_name ?? "—"
		},
		{
			key: "on_hand",
			header: "On hand",
			align: "right",
			sortable: true,
			value: (row) => row.on_hand,
			render: (row) => row.tracks_inventory ? /* @__PURE__ */ jsx("span", {
				className: cn("tnum font-medium", row.on_hand <= 0 && "text-bad"),
				children: formatNumber(row.on_hand)
			}) : /* @__PURE__ */ jsx(Badge, {
				variant: "muted",
				children: "not tracked"
			})
		},
		{
			key: "reserved",
			header: "Reserved",
			align: "right",
			sortable: true,
			value: (row) => row.reserved,
			render: (row) => formatNumber(row.reserved)
		},
		{
			key: "available",
			header: "Available",
			align: "right",
			sortable: true,
			value: (row) => row.available,
			render: (row) => formatNumber(row.available)
		},
		{
			key: "incoming",
			header: "Incoming",
			align: "right",
			sortable: true,
			value: (row) => row.incoming,
			render: (row) => row.incoming > 0 ? /* @__PURE__ */ jsxs("span", {
				className: "tnum text-good",
				children: ["+", formatNumber(row.incoming)]
			}) : "—"
		},
		{
			key: "days_of_cover",
			header: "Cover",
			align: "right",
			sortable: true,
			value: (row) => row.days_of_cover,
			tooltip: "Days of stock left at the last 30 days of sell-through.",
			render: (row) => row.days_of_cover >= 999 ? /* @__PURE__ */ jsx("span", {
				className: "text-muted-foreground",
				children: "—"
			}) : /* @__PURE__ */ jsxs("span", {
				className: cn("tnum", row.needs_reorder && "text-warn"),
				children: [row.days_of_cover, "d"]
			})
		},
		{
			key: "reorder_point",
			header: "Reorder at",
			align: "right",
			sortable: true,
			value: (row) => row.reorder_point,
			render: (row) => /* @__PURE__ */ jsxs("span", {
				className: cn("tnum", !row.reorder_point_is_manual && "text-muted-foreground"),
				children: [formatNumber(row.reorder_point), !row.reorder_point_is_manual && /* @__PURE__ */ jsx("span", {
					className: "ml-1 text-[10px]",
					children: "auto"
				})]
			})
		},
		{
			key: "stock_value",
			header: "Value at cost",
			align: "right",
			sortable: true,
			value: (row) => row.stock_value,
			render: (row) => formatCurrency(row.stock_value)
		},
		{
			key: "actions",
			header: "",
			render: (row) => /* @__PURE__ */ jsxs("div", {
				className: "flex justify-end gap-0.5",
				children: [/* @__PURE__ */ jsx(Button, {
					variant: "ghost",
					size: "icon",
					"aria-label": `Ledger for ${row.sku_code}`,
					onClick: () => setLedgerFor(row),
					children: /* @__PURE__ */ jsx(ScrollText, { className: "size-3.5" })
				}), can("catalog.stock.manage") && /* @__PURE__ */ jsx(Button, {
					variant: "ghost",
					size: "icon",
					"aria-label": `Adjust ${row.sku_code}`,
					onClick: () => setAdjusting(row),
					children: /* @__PURE__ */ jsx(Pencil, { className: "size-3.5" })
				})]
			})
		}
	], [can]);
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Inventory",
		description: "Stock you hold, what it is worth, and every change behind it",
		showFilters: false,
		actions: /* @__PURE__ */ jsxs("div", {
			className: "flex flex-wrap items-center gap-1.5",
			children: [
				/* @__PURE__ */ jsxs(Button, {
					variant: "outline",
					size: "sm",
					onClick: () => setLocationsOpen(true),
					children: [/* @__PURE__ */ jsx(Warehouse, { className: "size-3.5" }), " Locations"]
				}),
				/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.purchase_orders.view",
					children: /* @__PURE__ */ jsx(Button, {
						variant: "outline",
						size: "sm",
						asChild: true,
						children: /* @__PURE__ */ jsxs(Link, {
							href: "/inventory/purchasing",
							children: [/* @__PURE__ */ jsx(Truck, { className: "size-3.5" }), " Purchasing"]
						})
					})
				}),
				/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.stock_counts.view",
					children: /* @__PURE__ */ jsx(Button, {
						variant: "outline",
						size: "sm",
						asChild: true,
						children: /* @__PURE__ */ jsxs(Link, {
							href: "/inventory/counts",
							children: [/* @__PURE__ */ jsx(ClipboardList, { className: "size-3.5" }), " Counts"]
						})
					})
				}),
				/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.transfers.manage",
					children: /* @__PURE__ */ jsxs(Button, {
						variant: "outline",
						size: "sm",
						onClick: () => setTransferring(true),
						children: [/* @__PURE__ */ jsx(ArrowRightLeft, { className: "size-3.5" }), " Transfer"]
					})
				}),
				/* @__PURE__ */ jsx(PermissionGuard, {
					permission: "catalog.stock.manage",
					children: /* @__PURE__ */ jsxs(Button, {
						size: "sm",
						onClick: () => setImporting(true),
						children: [/* @__PURE__ */ jsx(Upload, { className: "size-3.5" }), " Opening stock"]
					})
				})
			]
		}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Inventory" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: load
			}),
			!data && !error && /* @__PURE__ */ jsx(SkeletonChart, { className: "h-64" }),
			data && /* @__PURE__ */ jsxs(Fragment, { children: [
				/* @__PURE__ */ jsx(VerdictNote, { verdict: data.verdict }),
				/* @__PURE__ */ jsx("div", {
					className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-5",
					children: [
						{
							label: "Stock at cost",
							value: formatCurrency(summary?.stock_value ?? 0)
						},
						{
							label: "Units on hand",
							value: formatNumber(summary?.units ?? 0)
						},
						{
							label: "SKUs tracked",
							value: formatNumber(summary?.skus ?? 0)
						},
						{
							label: "Out of stock",
							value: formatNumber(summary?.out_of_stock ?? 0),
							tone: (summary?.out_of_stock ?? 0) > 0 ? "bad" : void 0
						},
						{
							label: "Need reorder",
							value: formatNumber(summary?.needs_reorder ?? 0),
							tone: (summary?.needs_reorder ?? 0) > 0 ? "warn" : void 0
						}
					].map((card) => /* @__PURE__ */ jsxs(Card, {
						className: "p-4",
						children: [/* @__PURE__ */ jsx("p", {
							className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
							children: card.label
						}), /* @__PURE__ */ jsx("p", {
							className: cn("mt-1.5 text-xl font-semibold tnum", card.tone === "bad" && "text-bad", card.tone === "warn" && "text-warn"),
							children: card.value
						})]
					}, card.label))
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "flex flex-wrap items-center gap-2",
					children: [/* @__PURE__ */ jsx(Tabs, {
						value: filter,
						onValueChange: setFilter,
						children: /* @__PURE__ */ jsx(TabsList, { children: FILTERS.map((item) => /* @__PURE__ */ jsx(TabsTrigger, {
							value: item.key,
							children: item.label
						}, item.key)) })
					}), /* @__PURE__ */ jsxs(Select, {
						value: locationId,
						onValueChange: setLocationId,
						children: [/* @__PURE__ */ jsx(SelectTrigger, {
							className: "h-9 w-52",
							children: /* @__PURE__ */ jsx(SelectValue, {})
						}), /* @__PURE__ */ jsxs(SelectContent, { children: [/* @__PURE__ */ jsx(SelectItem, {
							value: "all",
							children: "All locations"
						}), data.locations.map((location) => /* @__PURE__ */ jsxs(SelectItem, {
							value: String(location.id),
							children: [
								location.name,
								" (",
								formatNumber(location.units),
								")"
							]
						}, location.id))] })]
					})]
				}),
				/* @__PURE__ */ jsx(ChartCard, {
					title: "Stock levels",
					subtitle: `${data.rows.length} SKUs`,
					bodyClassName: "p-0",
					loading: loading && data === null,
					empty: data.rows.length === 0,
					children: /* @__PURE__ */ jsx(DataTable, {
						columns,
						rows: data.rows,
						searchable: true,
						searchPlaceholder: "Search SKU, name or barcode…",
						rowKey: (row) => row.sku_id,
						maxHeight: "34rem",
						dense: true
					})
				})
			] }),
			/* @__PURE__ */ jsx(AdjustSheet, {
				row: adjusting,
				reasons: data?.reasons ?? [],
				locations: data?.locations ?? [],
				onClose: () => setAdjusting(null),
				onSaved: load
			}),
			/* @__PURE__ */ jsx(LedgerSheet, {
				row: ledgerFor,
				onClose: () => setLedgerFor(null)
			}),
			/* @__PURE__ */ jsx(ImportSheet, {
				open: importing,
				locations: data?.locations ?? [],
				onOpenChange: setImporting,
				onSaved: load
			}),
			/* @__PURE__ */ jsx(TransferSheet, {
				open: transferring,
				rows: data?.rows ?? [],
				locations: data?.locations ?? [],
				onOpenChange: setTransferring,
				onSaved: load
			}),
			/* @__PURE__ */ jsx(LocationsSheet, {
				open: locationsOpen,
				locations: data?.locations ?? [],
				onOpenChange: setLocationsOpen,
				onSaved: load
			})
		]
	});
}
function AdjustSheet({ row, reasons, locations, onClose, onSaved }) {
	const [mode, setMode] = useState("delta");
	const [quantity, setQuantity] = useState("");
	const [reason, setReason] = useState("damaged");
	const [note, setNote] = useState("");
	const [locationId, setLocationId] = useState("");
	const [saving, setSaving] = useState(false);
	useEffect(() => {
		if (row) {
			setMode("delta");
			setQuantity("");
			setReason("damaged");
			setNote("");
			setLocationId(String(locations.find((l) => l.is_default)?.id ?? locations[0]?.id ?? ""));
		}
	}, [row, locations]);
	const save = async () => {
		if (!row || quantity === "") {
			toast.error("Enter a quantity.");
			return;
		}
		setSaving(true);
		try {
			const response = await apiSend("POST", "/inventory/adjust", {
				sku_id: row.sku_id,
				location_id: locationId ? Number(locationId) : null,
				mode,
				quantity: Number(quantity),
				reason,
				note: note || null
			});
			toast.success(response.message);
			onSaved();
			onClose();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not adjust stock.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open: row !== null,
		onOpenChange: (open) => !open && onClose(),
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-md",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, { children: ["Adjust ", row?.sku_code] }), /* @__PURE__ */ jsxs(SheetDescription, { children: [
				row?.name,
				" · currently ",
				formatNumber(row?.on_hand ?? 0),
				" on hand. Every adjustment is recorded with its reason."
			] })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-4 px-4",
				children: [
					/* @__PURE__ */ jsx(Tabs, {
						value: mode,
						onValueChange: (value) => setMode(value),
						children: /* @__PURE__ */ jsxs(TabsList, {
							className: "w-full",
							children: [/* @__PURE__ */ jsx(TabsTrigger, {
								value: "delta",
								className: "flex-1",
								children: "Change by"
							}), /* @__PURE__ */ jsx(TabsTrigger, {
								value: "set",
								className: "flex-1",
								children: "Set to"
							})]
						})
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "adjust-qty",
							children: mode === "delta" ? "Quantity (use a minus for stock going out)" : "Counted quantity"
						}), /* @__PURE__ */ jsx(Input, {
							id: "adjust-qty",
							type: "number",
							value: quantity,
							onChange: (event) => setQuantity(event.target.value),
							placeholder: mode === "delta" ? "-12" : "42"
						})]
					}),
					locations.length > 1 && /* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "Location" }), /* @__PURE__ */ jsxs(Select, {
							value: locationId,
							onValueChange: setLocationId,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: locations.map((location) => /* @__PURE__ */ jsx(SelectItem, {
								value: String(location.id),
								children: location.name
							}, location.id)) })]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "Reason" }), /* @__PURE__ */ jsxs(Select, {
							value: reason,
							onValueChange: setReason,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: reasons.map((item) => /* @__PURE__ */ jsx(SelectItem, {
								value: item.value,
								children: item.label
							}, item.value)) })]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "adjust-note",
							children: "Note"
						}), /* @__PURE__ */ jsx(Input, {
							id: "adjust-note",
							value: note,
							onChange: (event) => setNote(event.target.value),
							placeholder: "Water damage in transit"
						})]
					}),
					/* @__PURE__ */ jsxs(Button, {
						className: "w-full",
						onClick: save,
						disabled: saving,
						children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }), " Save adjustment"]
					})
				]
			})]
		})
	});
}
function LedgerSheet({ row, onClose }) {
	const [rows, setRows] = useState(null);
	const [onHand, setOnHand] = useState(0);
	useEffect(() => {
		if (!row) {
			setRows(null);
			return;
		}
		apiGet(`/inventory/movements/${row.sku_id}`).then((response) => {
			setRows(response.data.rows);
			setOnHand(response.data.on_hand);
		}).catch(() => setRows([]));
	}, [row]);
	return /* @__PURE__ */ jsx(Sheet, {
		open: row !== null,
		onOpenChange: (open) => !open && onClose(),
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-2xl",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, { children: [row?.sku_code, " · stock ledger"] }), /* @__PURE__ */ jsxs(SheetDescription, { children: [formatNumber(onHand), " on hand. Every movement that got it there, newest first."] })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-1.5 overflow-y-auto px-4",
				children: [
					rows === null && /* @__PURE__ */ jsx("p", {
						className: "text-sm text-muted-foreground",
						children: "Loading…"
					}),
					rows?.length === 0 && /* @__PURE__ */ jsx("p", {
						className: "text-sm text-muted-foreground",
						children: "No movements recorded yet."
					}),
					rows?.map((movement) => /* @__PURE__ */ jsxs("div", {
						className: "flex items-start justify-between gap-3 rounded-lg border border-border p-2.5",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "min-w-0",
							children: [
								/* @__PURE__ */ jsxs("p", {
									className: "text-xs font-medium",
									children: [movement.type_label, movement.reason && /* @__PURE__ */ jsxs("span", {
										className: "ml-1.5 text-muted-foreground",
										children: ["· ", movement.reason.replace(/_/g, " ")]
									})]
								}),
								/* @__PURE__ */ jsxs("p", {
									className: "mt-0.5 text-[11px] text-muted-foreground",
									children: [
										movement.happened_at ? formatDateTime(movement.happened_at) : "—",
										" · ",
										movement.by,
										movement.location && ` · ${movement.location}`,
										movement.reference && ` · ${movement.reference}`
									]
								}),
								movement.note && /* @__PURE__ */ jsx("p", {
									className: "mt-0.5 text-[11px] italic text-muted-foreground",
									children: movement.note
								})
							]
						}), /* @__PURE__ */ jsxs("div", {
							className: "shrink-0 text-right",
							children: [/* @__PURE__ */ jsxs("p", {
								className: cn("text-sm font-semibold tnum", movement.quantity > 0 ? "text-good" : "text-bad"),
								children: [movement.quantity > 0 ? "+" : "", formatNumber(movement.quantity)]
							}), /* @__PURE__ */ jsxs("p", {
								className: "text-[11px] tnum text-muted-foreground",
								children: ["→ ", formatNumber(movement.balance_after)]
							})]
						})]
					}, movement.id))
				]
			})]
		})
	});
}
function ImportSheet({ open, locations, onOpenChange, onSaved }) {
	const [text, setText] = useState("");
	const [locationId, setLocationId] = useState("");
	const [saving, setSaving] = useState(false);
	const [result, setResult] = useState(null);
	useEffect(() => {
		if (open) {
			setLocationId(String(locations.find((l) => l.is_default)?.id ?? locations[0]?.id ?? ""));
			setResult(null);
		}
	}, [open, locations]);
	const save = async () => {
		const rows = text.split("\n").map((line) => line.trim()).filter(Boolean).map((line) => {
			const [sku_code, quantity] = line.split(/[,\t]/).map((part) => part.trim());
			return {
				sku_code,
				quantity: Number(quantity)
			};
		}).filter((row) => row.sku_code && Number.isFinite(row.quantity));
		if (rows.length === 0) {
			toast.error("Paste at least one row of \"SKU, quantity\".");
			return;
		}
		setSaving(true);
		try {
			const response = await apiSend("POST", "/inventory/bulk-levels", {
				location_id: locationId ? Number(locationId) : null,
				rows
			});
			setResult(response.data);
			toast.success(response.message);
			onSaved();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not import.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-lg",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Set opening stock" }), /* @__PURE__ */ jsxs(SheetDescription, { children: [
				"Paste one SKU per line as ",
				/* @__PURE__ */ jsx("code", {
					className: "text-[11px]",
					children: "SKU-CODE, quantity"
				}),
				". Each line sets the level to that number and records the difference in the ledger."
			] })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-3 px-4",
				children: [
					locations.length > 1 && /* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "Location" }), /* @__PURE__ */ jsxs(Select, {
							value: locationId,
							onValueChange: setLocationId,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: locations.map((location) => /* @__PURE__ */ jsx(SelectItem, {
								value: String(location.id),
								children: location.name
							}, location.id)) })]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "import-rows",
							children: "Rows"
						}), /* @__PURE__ */ jsx("textarea", {
							id: "import-rows",
							value: text,
							onChange: (event) => setText(event.target.value),
							rows: 12,
							placeholder: "KL-102, 120\nKL-210, 45",
							className: "w-full rounded-(--radius-input) border border-border bg-background px-3 py-2 font-mono text-xs"
						})]
					}),
					result && /* @__PURE__ */ jsxs(Card, {
						className: "space-y-1 p-3 text-xs",
						children: [/* @__PURE__ */ jsxs("p", { children: [
							/* @__PURE__ */ jsx("span", {
								className: "font-medium",
								children: result.applied
							}),
							" SKUs updated · ",
							result.unchanged,
							" already matched"
						] }), result.unknown_skus.length > 0 && /* @__PURE__ */ jsxs("p", {
							className: "text-bad",
							children: ["Not recognised: ", result.unknown_skus.join(", ")]
						})]
					}),
					/* @__PURE__ */ jsxs(Button, {
						className: "w-full",
						onClick: save,
						disabled: saving,
						children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }), " Apply"]
					})
				]
			})]
		})
	});
}
function TransferSheet({ open, rows, locations, onOpenChange, onSaved }) {
	const [skuId, setSkuId] = useState("");
	const [from, setFrom] = useState("");
	const [to, setTo] = useState("");
	const [quantity, setQuantity] = useState("");
	const [saving, setSaving] = useState(false);
	const save = async () => {
		setSaving(true);
		try {
			const response = await apiSend("POST", "/inventory/transfer", {
				sku_id: Number(skuId),
				from_location_id: Number(from),
				to_location_id: Number(to),
				quantity: Number(quantity)
			});
			toast.success(response.message);
			setQuantity("");
			onSaved();
			onOpenChange(false);
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not transfer.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-md",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Move stock between locations" }), /* @__PURE__ */ jsx(SheetDescription, { children: "Recorded as a matched pair, so the total you hold never changes." })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-3 px-4",
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "SKU" }), /* @__PURE__ */ jsxs(Select, {
							value: skuId,
							onValueChange: setSkuId,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Pick a SKU" }) }), /* @__PURE__ */ jsx(SelectContent, { children: rows.slice(0, 300).map((row) => /* @__PURE__ */ jsxs(SelectItem, {
								value: String(row.sku_id),
								children: [
									row.sku_code,
									" — ",
									row.name
								]
							}, row.sku_id)) })]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "grid grid-cols-2 gap-2",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, { children: "From" }), /* @__PURE__ */ jsxs(Select, {
								value: from,
								onValueChange: setFrom,
								children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Source" }) }), /* @__PURE__ */ jsx(SelectContent, { children: locations.map((location) => /* @__PURE__ */ jsx(SelectItem, {
									value: String(location.id),
									children: location.name
								}, location.id)) })]
							})]
						}), /* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, { children: "To" }), /* @__PURE__ */ jsxs(Select, {
								value: to,
								onValueChange: setTo,
								children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Destination" }) }), /* @__PURE__ */ jsx(SelectContent, { children: locations.filter((location) => String(location.id) !== from).map((location) => /* @__PURE__ */ jsx(SelectItem, {
									value: String(location.id),
									children: location.name
								}, location.id)) })]
							})]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "transfer-qty",
							children: "Quantity"
						}), /* @__PURE__ */ jsx(Input, {
							id: "transfer-qty",
							type: "number",
							min: 1,
							value: quantity,
							onChange: (event) => setQuantity(event.target.value)
						})]
					}),
					/* @__PURE__ */ jsxs(Button, {
						className: "w-full",
						onClick: save,
						disabled: saving || !skuId || !from || !to || !quantity,
						children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }), " Move stock"]
					})
				]
			})]
		})
	});
}
function LocationsSheet({ open, locations, onOpenChange, onSaved }) {
	const { can } = usePermissions();
	const [name, setName] = useState("");
	const [type, setType] = useState("warehouse");
	const [city, setCity] = useState("");
	const [saving, setSaving] = useState(false);
	const save = async () => {
		setSaving(true);
		try {
			const response = await apiSend("POST", "/inventory/locations", {
				name,
				type,
				city: city || null,
				is_active: true,
				is_default: locations.length === 0
			});
			toast.success(response.message);
			setName("");
			setCity("");
			onSaved();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save the location.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-md",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Warehouses & locations" }), /* @__PURE__ */ jsx(SheetDescription, { children: "Stock is held per location, so cover and transfers are per location too." })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-3 px-4",
				children: [/* @__PURE__ */ jsx("div", {
					className: "space-y-1.5",
					children: locations.map((location) => /* @__PURE__ */ jsx(Card, {
						className: "flex items-center justify-between gap-2 p-3",
						children: /* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsxs("p", {
							className: "text-xs font-medium",
							children: [location.name, location.is_default && /* @__PURE__ */ jsx(Badge, {
								variant: "muted",
								className: "ml-1.5",
								children: "default"
							})]
						}), /* @__PURE__ */ jsxs("p", {
							className: "text-[11px] text-muted-foreground",
							children: [
								location.type,
								location.city ? ` · ${location.city}` : "",
								" · ",
								formatNumber(location.units),
								" units"
							]
						})] })
					}, location.id))
				}), can("catalog.locations.manage") && /* @__PURE__ */ jsxs("div", {
					className: "space-y-2 border-t border-border pt-3",
					children: [
						/* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, {
								htmlFor: "loc-name",
								children: "Name"
							}), /* @__PURE__ */ jsx(Input, {
								id: "loc-name",
								value: name,
								onChange: (event) => setName(event.target.value),
								placeholder: "Bhiwandi warehouse"
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "grid grid-cols-2 gap-2",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsx(Label, { children: "Type" }), /* @__PURE__ */ jsxs(Select, {
									value: type,
									onValueChange: setType,
									children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsxs(SelectContent, { children: [
										/* @__PURE__ */ jsx(SelectItem, {
											value: "warehouse",
											children: "Warehouse"
										}),
										/* @__PURE__ */ jsx(SelectItem, {
											value: "store",
											children: "Retail store"
										}),
										/* @__PURE__ */ jsx(SelectItem, {
											value: "3pl",
											children: "3PL"
										}),
										/* @__PURE__ */ jsx(SelectItem, {
											value: "virtual",
											children: "Virtual"
										})
									] })]
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "loc-city",
									children: "City"
								}), /* @__PURE__ */ jsx(Input, {
									id: "loc-city",
									value: city,
									onChange: (event) => setCity(event.target.value)
								})]
							})]
						}),
						/* @__PURE__ */ jsxs(Button, {
							className: "w-full",
							size: "sm",
							onClick: save,
							disabled: saving || name.trim() === "",
							children: [saving ? /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }) : /* @__PURE__ */ jsx(Plus, { className: "size-3.5" }), " Add location"]
						})
					]
				})]
			})]
		})
	});
}
//#endregion
export { InventoryIndex as default };

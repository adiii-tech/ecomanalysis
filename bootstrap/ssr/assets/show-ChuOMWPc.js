import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, l as REPORT_LINKS, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-Drf1OqZl.js";
import { a as apiSend, c as DropdownMenu, i as apiGet, l as DropdownMenuContent, n as WidgetError, p as DropdownMenuTrigger, t as EmptyState, u as DropdownMenuItem } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, f as formatNumber, o as formatCurrency, p as formatPercent, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { i as VerdictNote, n as useExport } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { t as KpiCard } from "./kpi-card-CF3GcgfJ.js";
import { t as DrilldownDrawer } from "./drilldown-drawer-B3VTT9x2.js";
import { t as ReportSectionView } from "./report-section-CBf1J4XS.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useState } from "react";
import { Calendar, Download, Link2, Loader2, Star, Trash2 } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/components/app/orders-drilldown.tsx
var COLUMNS = [
	{
		key: "order_number",
		header: "Order",
		sortable: true,
		value: (row) => row.order_number,
		render: (row) => /* @__PURE__ */ jsx("span", {
			className: "font-medium",
			children: row.order_number
		})
	},
	{
		key: "placed_at",
		header: "Placed",
		sortable: true,
		value: (row) => row.placed_at,
		render: (row) => formatDateTime(row.placed_at)
	},
	{
		key: "channel",
		header: "Channel",
		render: (row) => row.channel ?? "—"
	},
	{
		key: "status",
		header: "Status",
		render: (row) => /* @__PURE__ */ jsx(Badge, {
			variant: "muted",
			children: row.status
		})
	},
	{
		key: "payment_mode",
		header: "Payment",
		render: (row) => row.payment_mode
	},
	{
		key: "shipping_state",
		header: "State",
		render: (row) => row.shipping_state ?? "—"
	},
	{
		key: "units_count",
		header: "Units",
		align: "right",
		sortable: true,
		value: (row) => row.units_count,
		render: (row) => formatNumber(row.units_count)
	},
	{
		key: "net_amount",
		header: "Net",
		align: "right",
		sortable: true,
		value: (row) => row.net_amount,
		render: (row) => formatCurrency(row.net_amount)
	},
	{
		key: "cogs_amount",
		header: "COGS",
		align: "right",
		sortable: true,
		value: (row) => row.cogs_amount,
		render: (row) => formatCurrency(row.cogs_amount)
	},
	{
		key: "fees_amount",
		header: "Fees",
		align: "right",
		sortable: true,
		value: (row) => row.fees_amount,
		render: (row) => formatCurrency(row.fees_amount)
	},
	{
		key: "logistics_amount",
		header: "Logistics",
		align: "right",
		sortable: true,
		value: (row) => row.logistics_amount,
		render: (row) => formatCurrency(row.logistics_amount)
	},
	{
		key: "contribution_margin",
		header: "Margin",
		align: "right",
		sortable: true,
		value: (row) => row.contribution_margin,
		render: (row) => /* @__PURE__ */ jsx("span", {
			className: cn("tnum", row.contribution_margin < 0 && "text-bad"),
			children: formatCurrency(row.contribution_margin)
		})
	},
	{
		key: "margin_pct",
		header: "Margin %",
		align: "right",
		sortable: true,
		value: (row) => row.margin_pct,
		render: (row) => formatPercent(row.margin_pct)
	}
];
/**
* The orders behind a number. Any widget can open this with a dimension and a
* value; the point is that no figure in the product is a dead end.
*/
function OrdersDrilldown({ target, onClose }) {
	const [rows, setRows] = useState(null);
	const [loading, setLoading] = useState(false);
	const [meta, setMeta] = useState(null);
	const load = useCallback(async (next) => {
		setLoading(true);
		try {
			const response = await apiGet("/drilldown/orders", {
				dimension: next.dimension ?? "",
				value: next.value === null || next.value === void 0 ? "" : String(next.value),
				only: next.only ?? ""
			});
			setRows(response.data.rows);
			setMeta({
				total: response.data.total,
				shown: response.data.shown,
				truncated: response.data.truncated
			});
		} catch {
			setRows([]);
			setMeta(null);
		} finally {
			setLoading(false);
		}
	}, []);
	useEffect(() => {
		if (target) load(target);
		else {
			setRows(null);
			setMeta(null);
		}
	}, [target, load]);
	return /* @__PURE__ */ jsx(DrilldownDrawer, {
		open: target !== null,
		onOpenChange: (open) => !open && onClose(),
		title: target?.title ?? "Orders",
		description: meta ? `${formatNumber(meta.total)} orders${meta.truncated ? `, showing the ${formatNumber(meta.shown)} most recent` : ""}. ${target?.description ?? ""}` : target?.description,
		columns: COLUMNS,
		rows,
		loading,
		rowKey: (row) => row.id,
		exportDataset: "orders",
		emptyTitle: "No orders behind this number"
	});
}
//#endregion
//#region resources/js/pages/reports/show.tsx
function ReportShow({ reportKey }) {
	const fallback = REPORT_LINKS.find((item) => item.slug === reportKey);
	const { data, loading, error, forbidden, reload } = useWidget(`/reports/${reportKey}`);
	const download = useExport();
	const [favourite, setFavourite] = useState(false);
	const [sharesOpen, setSharesOpen] = useState(false);
	const [drilldown, setDrilldown] = useState(null);
	const [scheduleOpen, setScheduleOpen] = useState(false);
	useEffect(() => {
		if (data?.report) setFavourite(Boolean(data.report.is_favourite));
	}, [data]);
	const toggleFavourite = useCallback(async () => {
		try {
			const response = await apiSend("POST", `/reports/${reportKey}/favourite`);
			setFavourite(response.data.is_favourite);
			toast.success(response.message);
		} catch {
			toast.error("Could not update your favourites.");
		}
	}, [reportKey]);
	const report = data?.report ?? null;
	const title = report?.label ?? fallback?.label ?? "Report";
	if (forbidden) return /* @__PURE__ */ jsxs(AppLayout, {
		title,
		showFilters: false,
		breadcrumb: {
			label: "Report library",
			href: "/reports"
		},
		children: [/* @__PURE__ */ jsx(Head, { title }), /* @__PURE__ */ jsx(EmptyState, {
			title: "You do not have access to this report",
			description: "Ask an admin to grant it in Admin → Users → Permissions."
		})]
	});
	return /* @__PURE__ */ jsxs(AppLayout, {
		title,
		description: report?.description ?? fallback?.description,
		showFilters: report?.uses_period ?? true,
		breadcrumb: {
			label: "Report library",
			href: "/reports"
		},
		actions: /* @__PURE__ */ jsxs("div", {
			className: "flex items-center gap-1.5",
			children: [
				/* @__PURE__ */ jsx(Button, {
					variant: "ghost",
					size: "icon",
					onClick: toggleFavourite,
					"aria-label": favourite ? "Remove from favourites" : "Add to favourites",
					children: /* @__PURE__ */ jsx(Star, { className: cn("size-4", favourite && "fill-warn text-warn") })
				}),
				/* @__PURE__ */ jsx(Button, {
					variant: "ghost",
					size: "icon",
					onClick: () => setSharesOpen(true),
					"aria-label": "Share link",
					children: /* @__PURE__ */ jsx(Link2, { className: "size-4" })
				}),
				/* @__PURE__ */ jsx(Button, {
					variant: "ghost",
					size: "icon",
					onClick: () => setScheduleOpen(true),
					"aria-label": "Schedule delivery",
					children: /* @__PURE__ */ jsx(Calendar, { className: "size-4" })
				}),
				(report?.exports.length ?? 0) > 0 && /* @__PURE__ */ jsxs(DropdownMenu, { children: [/* @__PURE__ */ jsx(DropdownMenuTrigger, {
					asChild: true,
					children: /* @__PURE__ */ jsxs(Button, {
						variant: "outline",
						size: "sm",
						children: [/* @__PURE__ */ jsx(Download, { className: "size-3.5" }), " Export"]
					})
				}), /* @__PURE__ */ jsx(DropdownMenuContent, {
					align: "end",
					children: (report?.exports ?? []).flatMap((dataset) => [
						"csv",
						"xlsx",
						"pdf"
					].map((format) => /* @__PURE__ */ jsxs(DropdownMenuItem, {
						onClick: () => download(dataset, format),
						children: [
							dataset.replace(/_/g, " "),
							" · ",
							format.toUpperCase()
						]
					}, `${dataset}-${format}`)))
				})] })
			]
		}),
		children: [
			/* @__PURE__ */ jsx(Head, { title }),
			error && !loading && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: reload
			}),
			loading && !data && /* @__PURE__ */ jsxs("div", {
				className: "space-y-4",
				children: [/* @__PURE__ */ jsx("div", {
					className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-4",
					children: [
						0,
						1,
						2,
						3
					].map((index) => /* @__PURE__ */ jsx(SkeletonChart, { className: "h-24" }, index))
				}), /* @__PURE__ */ jsx(SkeletonChart, { className: "h-72" })]
			}),
			data && /* @__PURE__ */ jsxs(Fragment, { children: [
				/* @__PURE__ */ jsx(VerdictNote, { verdict: data.verdict }),
				data.kpis.length > 0 && /* @__PURE__ */ jsx("div", {
					className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-4",
					children: data.kpis.map((metric) => /* @__PURE__ */ jsx(KpiCard, { metric }, metric.key))
				}),
				data.caveats.map((caveat, index) => /* @__PURE__ */ jsx(CaveatNote, { caveat }, index)),
				/* @__PURE__ */ jsx("div", {
					className: "space-y-4",
					children: data.sections.map((section, index) => /* @__PURE__ */ jsx(ReportSectionView, {
						section,
						onDrilldown: setDrilldown
					}, `${section.type}-${index}`))
				})
			] }),
			/* @__PURE__ */ jsx(OrdersDrilldown, {
				target: drilldown,
				onClose: () => setDrilldown(null)
			}),
			/* @__PURE__ */ jsx(SharesSheet, {
				open: sharesOpen,
				onOpenChange: setSharesOpen,
				reportKey
			}),
			/* @__PURE__ */ jsx(ScheduleSheet, {
				open: scheduleOpen,
				onOpenChange: setScheduleOpen,
				reportKey
			})
		]
	});
}
function SharesSheet({ open, onOpenChange, reportKey }) {
	const [rows, setRows] = useState(null);
	const [creating, setCreating] = useState(false);
	const [days, setDays] = useState("30");
	const load = useCallback(() => {
		apiGet(`/reports/${reportKey}/shares`).then((response) => setRows(response.data.rows)).catch(() => setRows([]));
	}, [reportKey]);
	useEffect(() => {
		if (open) load();
	}, [open, load]);
	const create = async () => {
		setCreating(true);
		try {
			const response = await apiSend("POST", `/reports/${reportKey}/share`, { expires_in_days: Number(days) });
			await navigator.clipboard?.writeText(response.data.url).catch(() => void 0);
			toast.success("Share link created and copied.");
			load();
		} catch {
			toast.error("Could not create the share link.");
		} finally {
			setCreating(false);
		}
	};
	const revoke = async (id) => {
		try {
			await apiSend("DELETE", `/reports/${reportKey}/shares/${id}`);
			toast.success("Link revoked.");
			load();
		} catch {
			toast.error("Could not revoke that link.");
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-lg",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Share this report" }), /* @__PURE__ */ jsx(SheetDescription, { children: "Anyone with the link sees a read-only snapshot on the filters you have applied right now. No sign-in needed, and you can revoke it at any time." })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-4 px-4",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "flex items-end gap-2",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "flex-1 space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "share-days",
							children: "Expires in (days)"
						}), /* @__PURE__ */ jsx(Input, {
							id: "share-days",
							type: "number",
							min: 1,
							max: 90,
							value: days,
							onChange: (event) => setDays(event.target.value)
						})]
					}), /* @__PURE__ */ jsxs(Button, {
						onClick: create,
						disabled: creating,
						children: [creating ? /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }) : /* @__PURE__ */ jsx(Link2, { className: "size-4" }), "Create link"]
					})]
				}), /* @__PURE__ */ jsxs("div", {
					className: "space-y-2",
					children: [
						rows === null && /* @__PURE__ */ jsx("p", {
							className: "text-sm text-muted-foreground",
							children: "Loading…"
						}),
						rows?.length === 0 && /* @__PURE__ */ jsx("p", {
							className: "text-sm text-muted-foreground",
							children: "No active links."
						}),
						rows?.map((row) => /* @__PURE__ */ jsxs(Card, {
							className: "flex items-center justify-between gap-3 p-3",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "min-w-0",
								children: [/* @__PURE__ */ jsx("button", {
									type: "button",
									className: "truncate text-xs text-primary hover:underline",
									onClick: () => {
										navigator.clipboard?.writeText(row.url).catch(() => void 0);
										toast.success("Link copied.");
									},
									children: row.url
								}), /* @__PURE__ */ jsxs("p", {
									className: "mt-0.5 text-[11px] text-muted-foreground",
									children: [
										row.view_count,
										" views ·",
										" ",
										row.expires_at ? `expires ${formatDateTime(row.expires_at)}` : "no expiry",
										row.is_expired && " · expired"
									]
								})]
							}), /* @__PURE__ */ jsx(Button, {
								variant: "ghost",
								size: "icon",
								onClick: () => revoke(row.id),
								"aria-label": "Revoke link",
								children: /* @__PURE__ */ jsx(Trash2, { className: "size-4" })
							})]
						}, row.id))
					]
				})]
			})]
		})
	});
}
function ScheduleSheet({ open, onOpenChange, reportKey }) {
	const [rows, setRows] = useState(null);
	const [saving, setSaving] = useState(false);
	const [cadence, setCadence] = useState("weekly");
	const [hour, setHour] = useState("8");
	const [dayOfWeek, setDayOfWeek] = useState("1");
	const [recipients, setRecipients] = useState("");
	const [format, setFormat] = useState("pdf");
	const load = useCallback(() => {
		apiGet(`/reports/${reportKey}/schedules`).then((response) => setRows(response.data.rows)).catch(() => setRows([]));
	}, [reportKey]);
	useEffect(() => {
		if (open) load();
	}, [open, load]);
	const save = async () => {
		const emails = recipients.split(/[,\s]+/).map((value) => value.trim()).filter(Boolean);
		if (emails.length === 0) {
			toast.error("Add at least one recipient.");
			return;
		}
		setSaving(true);
		try {
			await apiSend("POST", `/reports/${reportKey}/schedules`, {
				cadence,
				hour: Number(hour),
				day_of_week: cadence === "weekly" ? Number(dayOfWeek) : null,
				day_of_month: cadence === "monthly" ? 1 : null,
				recipients: emails,
				format
			});
			toast.success("Schedule saved.");
			setRecipients("");
			load();
		} catch {
			toast.error("Could not save the schedule.");
		} finally {
			setSaving(false);
		}
	};
	const remove = async (id) => {
		try {
			await apiSend("DELETE", `/reports/${reportKey}/schedules/${id}`);
			toast.success("Schedule deleted.");
			load();
		} catch {
			toast.error("Could not delete that schedule.");
		}
	};
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			className: "w-full sm:max-w-lg",
			children: [/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Email this report on a schedule" }), /* @__PURE__ */ jsx(SheetDescription, { children: "The file is generated fresh at send time using the same filters, and goes to the addresses you list." })] }), /* @__PURE__ */ jsxs("div", {
				className: "space-y-4 px-4",
				children: [
					/* @__PURE__ */ jsxs("div", {
						className: "grid grid-cols-2 gap-3",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, { children: "Cadence" }), /* @__PURE__ */ jsxs(Select, {
								value: cadence,
								onValueChange: setCadence,
								children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsxs(SelectContent, { children: [
									/* @__PURE__ */ jsx(SelectItem, {
										value: "daily",
										children: "Daily"
									}),
									/* @__PURE__ */ jsx(SelectItem, {
										value: "weekly",
										children: "Weekly"
									}),
									/* @__PURE__ */ jsx(SelectItem, {
										value: "monthly",
										children: "Monthly"
									})
								] })]
							})]
						}), /* @__PURE__ */ jsxs("div", {
							className: "space-y-1.5",
							children: [/* @__PURE__ */ jsx(Label, {
								htmlFor: "schedule-hour",
								children: "Hour (IST)"
							}), /* @__PURE__ */ jsx(Input, {
								id: "schedule-hour",
								type: "number",
								min: 0,
								max: 23,
								value: hour,
								onChange: (event) => setHour(event.target.value)
							})]
						})]
					}),
					cadence === "weekly" && /* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "Day" }), /* @__PURE__ */ jsxs(Select, {
							value: dayOfWeek,
							onValueChange: setDayOfWeek,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: [
								"Sunday",
								"Monday",
								"Tuesday",
								"Wednesday",
								"Thursday",
								"Friday",
								"Saturday"
							].map((day, index) => /* @__PURE__ */ jsx(SelectItem, {
								value: String(index),
								children: day
							}, day)) })]
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "schedule-recipients",
							children: "Recipients"
						}), /* @__PURE__ */ jsx(Input, {
							id: "schedule-recipients",
							placeholder: "founder@brand.com, ops@brand.com",
							value: recipients,
							onChange: (event) => setRecipients(event.target.value)
						})]
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: "Format" }), /* @__PURE__ */ jsxs(Select, {
							value: format,
							onValueChange: setFormat,
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsxs(SelectContent, { children: [
								/* @__PURE__ */ jsx(SelectItem, {
									value: "pdf",
									children: "PDF"
								}),
								/* @__PURE__ */ jsx(SelectItem, {
									value: "csv",
									children: "CSV"
								}),
								/* @__PURE__ */ jsx(SelectItem, {
									value: "xlsx",
									children: "Excel"
								})
							] })]
						})]
					}),
					/* @__PURE__ */ jsxs(Button, {
						onClick: save,
						disabled: saving,
						className: "w-full",
						children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-4 animate-spin" }), " Save schedule"]
					}),
					/* @__PURE__ */ jsx("div", {
						className: "space-y-2",
						children: rows?.map((row) => /* @__PURE__ */ jsxs(Card, {
							className: "flex items-center justify-between gap-3 p-3",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "min-w-0",
								children: [/* @__PURE__ */ jsxs("p", {
									className: "text-sm font-medium capitalize",
									children: [
										row.cadence,
										" · ",
										row.hour,
										":00 · ",
										row.format.toUpperCase()
									]
								}), /* @__PURE__ */ jsxs("p", {
									className: "mt-0.5 truncate text-[11px] text-muted-foreground",
									children: [row.recipients.join(", "), row.last_sent_at && ` · last sent ${formatDateTime(row.last_sent_at)}`]
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "flex items-center gap-1.5",
								children: [/* @__PURE__ */ jsx(Badge, {
									variant: row.is_active ? "good" : "muted",
									children: row.is_active ? "Active" : "Paused"
								}), /* @__PURE__ */ jsx(Button, {
									variant: "ghost",
									size: "icon",
									onClick: () => remove(row.id),
									"aria-label": "Delete schedule",
									children: /* @__PURE__ */ jsx(Trash2, { className: "size-4" })
								})]
							})]
						}, row.id))
					})
				]
			})]
		})
	});
}
//#endregion
export { ReportShow as default };

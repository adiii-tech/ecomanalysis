import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { a as apiSend, i as apiGet, s as usePermissions, t as EmptyState } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { t as Switch } from "./switch-CfwX-tOM.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useEffect, useState } from "react";
import { AlertTriangle, BellOff, Check, Loader2, Plus, TestTube2, Trash2 } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/alerts/index.tsx
var BLANK = {
	name: "",
	metric: "rto_rate_by_state",
	operator: "gt",
	threshold: 25,
	window_days: 7,
	channels: ["in_app"],
	is_active: true
};
function Alerts() {
	const { can } = usePermissions();
	const [schema, setSchema] = useState(null);
	const [rules, setRules] = useState([]);
	const [events, setEvents] = useState([]);
	const [unread, setUnread] = useState(0);
	const [draft, setDraft] = useState(null);
	const [testing, setTesting] = useState(false);
	const [testResult, setTestResult] = useState(null);
	const [loading, setLoading] = useState(true);
	useEffect(() => {
		refresh();
	}, []);
	async function refresh() {
		setLoading(true);
		try {
			const [s, r, e] = await Promise.all([
				apiGet("alerts/schema"),
				apiGet("alerts/rules"),
				apiGet("alerts/events")
			]);
			setSchema(s.data);
			setRules(r.data.rows);
			setEvents(e.data.rows);
			setUnread(e.data.unread);
		} catch {} finally {
			setLoading(false);
		}
	}
	const metric = schema?.metrics.find((m) => m.key === draft?.metric);
	async function save() {
		if (!draft) return;
		try {
			if (draft.id) await apiSend("PUT", `alerts/rules/${draft.id}`, draft);
			else await apiSend("POST", "alerts/rules", draft);
			toast.success("Rule saved.");
			setDraft(null);
			setTestResult(null);
			refresh();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save.");
		}
	}
	async function test() {
		if (!draft) return;
		setTesting(true);
		try {
			const response = await apiSend("POST", "alerts/test", draft);
			setTestResult(response.data);
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not test.");
		} finally {
			setTesting(false);
		}
	}
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Alerts",
		description: "Tell me before it costs money",
		showFilters: false,
		actions: can("alerts.rules.manage") && /* @__PURE__ */ jsxs(Button, {
			size: "sm",
			onClick: () => {
				setDraft({ ...BLANK });
				setTestResult(null);
			},
			className: "gap-1.5",
			children: [/* @__PURE__ */ jsx(Plus, { className: "size-3.5" }), "New rule"]
		}),
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Alerts" }),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "alerts.events.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Notifications",
					subtitle: unread > 0 ? `${unread} unread` : "Everything read",
					loading,
					actions: unread > 0 && /* @__PURE__ */ jsxs(Button, {
						size: "xs",
						variant: "ghost",
						onClick: async () => {
							await apiSend("POST", "alerts/events/read");
							refresh();
						},
						className: "gap-1",
						children: [/* @__PURE__ */ jsx(Check, { className: "size-3" }), "Mark all read"]
					}),
					empty: events.length === 0,
					emptyState: /* @__PURE__ */ jsx(EmptyState, {
						kind: "celebrate",
						compact: true,
						title: "Nothing has tripped an alert",
						description: "Rules are evaluated hourly against the same rollups the dashboard reads."
					}),
					children: /* @__PURE__ */ jsx("div", {
						className: "space-y-2",
						children: events.map((event) => /* @__PURE__ */ jsx(Card, {
							className: cn("p-3", event.severity === "critical" ? "border-bad/25 bg-bad-soft/30" : "border-warn/25 bg-warn-soft/30", event.read_at && "opacity-60"),
							children: /* @__PURE__ */ jsxs("div", {
								className: "flex items-start gap-2.5",
								children: [
									/* @__PURE__ */ jsx(AlertTriangle, { className: cn("mt-0.5 size-4 shrink-0", event.severity === "critical" ? "text-bad" : "text-warn") }),
									/* @__PURE__ */ jsxs("div", {
										className: "min-w-0 flex-1",
										children: [
											/* @__PURE__ */ jsx("p", {
												className: "text-sm font-semibold leading-snug",
												children: event.title
											}),
											/* @__PURE__ */ jsx("p", {
												className: "mt-0.5 text-xs leading-snug text-muted-foreground",
												children: event.body
											}),
											/* @__PURE__ */ jsxs("p", {
												className: "mt-1 text-[10px] text-muted-foreground",
												children: [
													event.rule?.name,
													" · ",
													formatDateTime(event.created_at)
												]
											})
										]
									}),
									/* @__PURE__ */ jsxs(Button, {
										size: "xs",
										variant: "ghost",
										onClick: async () => {
											await apiSend("POST", `alerts/events/${event.id}/snooze`, { days: 7 });
											refresh();
										},
										className: "shrink-0 gap-1 text-[11px]",
										children: [/* @__PURE__ */ jsx(BellOff, { className: "size-3" }), "Snooze"]
									})
								]
							})
						}, event.id))
					})
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "alerts.rules.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Rules",
					subtitle: "Evaluated hourly",
					loading,
					empty: rules.length === 0,
					emptyState: /* @__PURE__ */ jsx(EmptyState, {
						title: "No rules yet",
						description: "A rule watches one metric over a window and tells you when it crosses a line."
					}),
					children: /* @__PURE__ */ jsx("div", {
						className: "space-y-2",
						children: rules.map((rule) => /* @__PURE__ */ jsxs("div", {
							className: "flex flex-wrap items-center gap-2 rounded-lg border border-border p-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "min-w-0 flex-1",
									children: [/* @__PURE__ */ jsxs("p", {
										className: "flex items-center gap-1.5 text-sm font-medium",
										children: [
											rule.name,
											!rule.is_active && /* @__PURE__ */ jsx(Badge, {
												variant: "muted",
												children: "off"
											}),
											rule.is_muted && /* @__PURE__ */ jsx(Badge, {
												variant: "warn",
												children: "muted"
											})
										]
									}), /* @__PURE__ */ jsxs("p", {
										className: "text-[11px] text-muted-foreground",
										children: [
											rule.metric_label,
											" ",
											rule.operator === "gt" ? ">" : rule.operator === "lt" ? "<" : rule.operator,
											" ",
											rule.threshold,
											" over ",
											rule.window_days,
											"d",
											rule.last_triggered_at ? ` · last fired ${formatDateTime(rule.last_triggered_at)}` : " · never fired"
										]
									})]
								}),
								/* @__PURE__ */ jsxs(Badge, {
									variant: rule.recent_events > 0 ? "warn" : "muted",
									children: [rule.recent_events, " in 30d"]
								}),
								can("alerts.rules.manage") && /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx(Button, {
									size: "xs",
									variant: "ghost",
									onClick: () => {
										setDraft({
											...BLANK,
											...rule,
											channels: rule.channels ?? ["in_app"]
										});
										setTestResult(null);
									},
									children: "Edit"
								}), /* @__PURE__ */ jsx(Button, {
									size: "xs",
									variant: "ghost",
									className: "text-bad",
									onClick: async () => {
										await apiSend("DELETE", `alerts/rules/${rule.id}`);
										toast.success("Rule deleted.");
										refresh();
									},
									children: /* @__PURE__ */ jsx(Trash2, { className: "size-3" })
								})] })
							]
						}, rule.id))
					})
				})
			}),
			/* @__PURE__ */ jsx(Sheet, {
				open: draft !== null,
				onOpenChange: (open) => !open && setDraft(null),
				children: /* @__PURE__ */ jsxs(SheetContent, {
					side: "right",
					className: "sm:max-w-md",
					children: [
						/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: draft?.id ? "Edit rule" : "New alert rule" }), /* @__PURE__ */ jsx(SheetDescription, { children: "Watch one metric over a window and get told when it crosses a line." })] }),
						/* @__PURE__ */ jsxs("div", {
							className: "flex-1 space-y-4 overflow-auto p-5",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1.5",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "rule-name",
										children: "Name"
									}), /* @__PURE__ */ jsx(Input, {
										id: "rule-name",
										value: draft?.name ?? "",
										placeholder: "RTO spike in any state",
										onChange: (event) => setDraft((d) => d ? {
											...d,
											name: event.target.value
										} : d)
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1.5",
									children: [
										/* @__PURE__ */ jsx(Label, { children: "Metric" }),
										/* @__PURE__ */ jsxs(Select, {
											value: draft?.metric,
											onValueChange: (value) => setDraft((d) => d ? {
												...d,
												metric: value
											} : d),
											children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: (schema?.metrics ?? []).map((m) => /* @__PURE__ */ jsx(SelectItem, {
												value: m.key,
												children: m.label
											}, m.key)) })]
										}),
										metric && /* @__PURE__ */ jsx("p", {
											className: "text-[11px] text-muted-foreground",
											children: metric.description
										})
									]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "grid grid-cols-2 gap-2",
									children: [/* @__PURE__ */ jsxs("div", {
										className: "space-y-1.5",
										children: [/* @__PURE__ */ jsx(Label, { children: "Condition" }), /* @__PURE__ */ jsxs(Select, {
											value: draft?.operator,
											onValueChange: (value) => setDraft((d) => d ? {
												...d,
												operator: value
											} : d),
											children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: (schema?.operators ?? []).map((o) => /* @__PURE__ */ jsx(SelectItem, {
												value: o.key,
												children: o.label
											}, o.key)) })]
										})]
									}), /* @__PURE__ */ jsxs("div", {
										className: "space-y-1.5",
										children: [/* @__PURE__ */ jsxs(Label, {
											htmlFor: "threshold",
											children: ["Threshold ", metric ? `(${metric.unit})` : ""]
										}), /* @__PURE__ */ jsx(Input, {
											id: "threshold",
											type: "number",
											step: "0.1",
											value: draft?.threshold ?? 0,
											onChange: (event) => setDraft((d) => d ? {
												...d,
												threshold: Number(event.target.value)
											} : d)
										})]
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1.5",
									children: [/* @__PURE__ */ jsx(Label, { children: "Window" }), /* @__PURE__ */ jsxs(Select, {
										value: String(draft?.window_days ?? 7),
										onValueChange: (value) => setDraft((d) => d ? {
											...d,
											window_days: Number(value)
										} : d),
										children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: (schema?.windows ?? [7]).map((w) => /* @__PURE__ */ jsxs(SelectItem, {
											value: String(w),
											children: [
												w,
												" day",
												w === 1 ? "" : "s"
											]
										}, w)) })]
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1.5",
									children: [/* @__PURE__ */ jsx(Label, { children: "Deliver to" }), /* @__PURE__ */ jsx("div", {
										className: "space-y-1.5",
										children: (schema?.channels ?? []).map((channel) => /* @__PURE__ */ jsxs("label", {
											className: cn("flex items-center gap-2 text-xs", !channel.available && "opacity-50"),
											children: [
												/* @__PURE__ */ jsx("input", {
													type: "checkbox",
													disabled: !channel.available,
													checked: draft?.channels?.includes(channel.key) ?? false,
													onChange: (event) => setDraft((d) => d ? {
														...d,
														channels: event.target.checked ? [...d.channels ?? [], channel.key] : (d.channels ?? []).filter((c) => c !== channel.key)
													} : d),
													className: "size-3.5 rounded border-input"
												}),
												channel.label,
												channel.note && /* @__PURE__ */ jsxs("span", {
													className: "text-[10px] text-muted-foreground",
													children: ["— ", channel.note]
												})
											]
										}, channel.key))
									})]
								}),
								/* @__PURE__ */ jsxs("label", {
									className: "flex items-center justify-between text-xs",
									children: [/* @__PURE__ */ jsx("span", { children: "Active" }), /* @__PURE__ */ jsx(Switch, {
										checked: draft?.is_active ?? true,
										onCheckedChange: (checked) => setDraft((d) => d ? {
											...d,
											is_active: checked
										} : d)
									})]
								}),
								testResult && /* @__PURE__ */ jsx("div", {
									className: cn("rounded-lg px-3 py-2 text-xs", testResult.would_fire ? "bg-warn-soft text-warn" : "bg-good-soft text-good"),
									children: testResult.message
								})
							]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "flex justify-between gap-2 border-t border-border px-5 py-3",
							children: [/* @__PURE__ */ jsxs(Button, {
								variant: "outline",
								size: "sm",
								onClick: test,
								disabled: testing || !draft?.name,
								className: "gap-1.5",
								children: [testing ? /* @__PURE__ */ jsx(Loader2, { className: "animate-spin" }) : /* @__PURE__ */ jsx(TestTube2, { className: "size-3.5" }), "Test against live data"]
							}), /* @__PURE__ */ jsxs("div", {
								className: "flex gap-2",
								children: [/* @__PURE__ */ jsx(Button, {
									variant: "ghost",
									size: "sm",
									onClick: () => setDraft(null),
									children: "Cancel"
								}), /* @__PURE__ */ jsx(Button, {
									size: "sm",
									onClick: save,
									disabled: !draft?.name,
									children: "Save"
								})]
							})]
						})
					]
				})
			})
		]
	});
}
//#endregion
export { Alerts as default };

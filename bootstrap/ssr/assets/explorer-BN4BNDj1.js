import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { a as apiSend, c as DropdownMenu, i as apiGet, l as DropdownMenuContent, n as WidgetError, p as DropdownMenuTrigger, s as usePermissions, u as DropdownMenuItem } from "./empty-state-DjIQjBC7.js";
import { f as formatNumber, o as formatCurrency, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Download, Plus, RefreshCw, Trash2, Users } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/customers/explorer.tsx
var OPERATOR_LABELS = {
	gt: "is more than",
	gte: "is at least",
	lt: "is less than",
	lte: "is at most",
	eq: "is",
	not_eq: "is not",
	between: "is between",
	contains: "contains",
	in: "is any of",
	before: "is before",
	after: "is after",
	within_days: "was within the last (days)",
	not_within_days: "was not within the last (days)",
	is: "is"
};
function CustomerExplorer() {
	const { can } = usePermissions();
	const [fields, setFields] = useState([]);
	const [segments, setSegments] = useState([]);
	const [destinations, setDestinations] = useState([]);
	const [error, setError] = useState(null);
	const [rules, setRules] = useState({
		match: "all",
		conditions: []
	});
	const [preview, setPreview] = useState(null);
	const [previewing, setPreviewing] = useState(false);
	const [name, setName] = useState("");
	const [description, setDescription] = useState("");
	const load = useCallback(() => {
		apiGet("/segments").then((response) => {
			setSegments(response.data.rows);
			setFields(response.data.fields);
			setDestinations(response.data.destinations);
			setError(null);
		}).catch((err) => setError(err instanceof Error ? err.message : "Could not load segments."));
	}, []);
	useEffect(load, [load]);
	const fieldMap = useMemo(() => Object.fromEntries(fields.map((field) => [field.key, field])), [fields]);
	const runPreview = useCallback(async (next) => {
		setPreviewing(true);
		try {
			const response = await apiSend("POST", "/segments/preview", { rules: next });
			setPreview(response.data);
		} catch (err) {
			toast.error(err instanceof Error ? err.message : "Could not preview that segment.");
			setPreview(null);
		} finally {
			setPreviewing(false);
		}
	}, []);
	useEffect(() => {
		runPreview(rules);
	}, [rules, runPreview]);
	const addCondition = () => {
		const first = fields[0];
		if (!first) return;
		setRules((current) => ({
			...current,
			conditions: [...current.conditions, {
				field: first.key,
				operator: first.operators[0],
				value: ""
			}]
		}));
	};
	const updateCondition = (index, patch) => {
		setRules((current) => ({
			...current,
			conditions: current.conditions.map((condition, position) => position === index ? {
				...condition,
				...patch
			} : condition)
		}));
	};
	const removeCondition = (index) => {
		setRules((current) => ({
			...current,
			conditions: current.conditions.filter((_, position) => position !== index)
		}));
	};
	const save = async () => {
		if (name.trim() === "") {
			toast.error("Give the segment a name.");
			return;
		}
		try {
			const response = await apiSend("POST", "/segments", {
				name: name.trim(),
				description: description || null,
				rules
			});
			toast.success(response.message);
			setName("");
			setDescription("");
			load();
		} catch (err) {
			toast.error(err instanceof Error ? err.message : "Could not save the segment.");
		}
	};
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Customer explorer",
		description: "Build a segment, see who is in it, then send it somewhere useful",
		showFilters: false,
		breadcrumb: {
			label: "Customers & Reviews",
			href: "/customers"
		},
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Customer explorer" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: load
			}),
			fields.length === 0 && !error && /* @__PURE__ */ jsx(SkeletonChart, { className: "h-64" }),
			fields.length > 0 && /* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-[1fr_20rem]",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "space-y-4",
					children: [
						/* @__PURE__ */ jsx(ChartCard, {
							title: "Rules",
							subtitle: "Every field here is a real customer attribute — nothing free-text reaches the query",
							children: /* @__PURE__ */ jsxs("div", {
								className: "space-y-3",
								children: [
									/* @__PURE__ */ jsxs("div", {
										className: "flex items-center gap-2 text-xs",
										children: [/* @__PURE__ */ jsx("span", {
											className: "text-muted-foreground",
											children: "Match"
										}), /* @__PURE__ */ jsxs(Select, {
											value: rules.match,
											onValueChange: (value) => setRules((c) => ({
												...c,
												match: value
											})),
											children: [/* @__PURE__ */ jsx(SelectTrigger, {
												className: "h-8 w-28",
												children: /* @__PURE__ */ jsx(SelectValue, {})
											}), /* @__PURE__ */ jsxs(SelectContent, { children: [/* @__PURE__ */ jsx(SelectItem, {
												value: "all",
												children: "all rules"
											}), /* @__PURE__ */ jsx(SelectItem, {
												value: "any",
												children: "any rule"
											})] })]
										})]
									}),
									rules.conditions.map((condition, index) => {
										const meta = fieldMap[condition.field];
										return /* @__PURE__ */ jsxs("div", {
											className: "flex flex-wrap items-center gap-2",
											children: [
												/* @__PURE__ */ jsxs(Select, {
													value: condition.field,
													onValueChange: (value) => {
														const next = fieldMap[value];
														updateCondition(index, {
															field: value,
															operator: next.operators[0],
															value: ""
														});
													},
													children: [/* @__PURE__ */ jsx(SelectTrigger, {
														className: "h-9 w-52",
														children: /* @__PURE__ */ jsx(SelectValue, {})
													}), /* @__PURE__ */ jsx(SelectContent, { children: fields.map((field) => /* @__PURE__ */ jsx(SelectItem, {
														value: field.key,
														children: field.label
													}, field.key)) })]
												}),
												/* @__PURE__ */ jsxs(Select, {
													value: condition.operator,
													onValueChange: (value) => updateCondition(index, { operator: value }),
													children: [/* @__PURE__ */ jsx(SelectTrigger, {
														className: "h-9 w-48",
														children: /* @__PURE__ */ jsx(SelectValue, {})
													}), /* @__PURE__ */ jsx(SelectContent, { children: (meta?.operators ?? []).map((operator) => /* @__PURE__ */ jsx(SelectItem, {
														value: operator,
														children: OPERATOR_LABELS[operator] ?? operator
													}, operator)) })]
												}),
												meta?.type === "enum" ? /* @__PURE__ */ jsxs(Select, {
													value: String(condition.value),
													onValueChange: (value) => updateCondition(index, { value }),
													children: [/* @__PURE__ */ jsx(SelectTrigger, {
														className: "h-9 w-44",
														children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Pick one" })
													}), /* @__PURE__ */ jsx(SelectContent, { children: (meta.options ?? []).map((option) => /* @__PURE__ */ jsx(SelectItem, {
														value: option,
														children: option.replace(/_/g, " ")
													}, option)) })]
												}) : meta?.type === "boolean" ? /* @__PURE__ */ jsxs(Select, {
													value: String(condition.value),
													onValueChange: (value) => updateCondition(index, { value: value === "true" }),
													children: [/* @__PURE__ */ jsx(SelectTrigger, {
														className: "h-9 w-32",
														children: /* @__PURE__ */ jsx(SelectValue, { placeholder: "Yes / no" })
													}), /* @__PURE__ */ jsxs(SelectContent, { children: [/* @__PURE__ */ jsx(SelectItem, {
														value: "true",
														children: "Yes"
													}), /* @__PURE__ */ jsx(SelectItem, {
														value: "false",
														children: "No"
													})] })]
												}) : /* @__PURE__ */ jsx(Input, {
													className: "h-9 w-44",
													type: meta?.type === "date" ? "date" : meta?.type === "text" ? "text" : "number",
													placeholder: meta?.type === "money" ? "₹ amount" : "",
													value: String(condition.value ?? ""),
													onChange: (event) => updateCondition(index, { value: event.target.value })
												}),
												/* @__PURE__ */ jsx(Button, {
													variant: "ghost",
													size: "icon",
													onClick: () => removeCondition(index),
													"aria-label": "Remove rule",
													children: /* @__PURE__ */ jsx(Trash2, { className: "size-4" })
												})
											]
										}, index);
									}),
									/* @__PURE__ */ jsxs(Button, {
										variant: "outline",
										size: "sm",
										onClick: addCondition,
										children: [/* @__PURE__ */ jsx(Plus, { className: "size-3.5" }), " Add a rule"]
									})
								]
							})
						}),
						/* @__PURE__ */ jsx(ChartCard, {
							title: "Who is in it",
							subtitle: previewing ? "Counting…" : `${formatNumber(preview?.member_count ?? 0)} customers match`,
							bodyClassName: "p-0",
							empty: (preview?.sample.length ?? 0) === 0,
							emptyState: /* @__PURE__ */ jsx("p", {
								className: "p-6 text-center text-sm text-muted-foreground",
								children: "No customer matches these rules."
							}),
							children: /* @__PURE__ */ jsx(DataTable, {
								columns: [
									{
										key: "name",
										header: "Customer",
										render: (row) => String(row.name ?? "—"),
										sortable: true,
										value: (row) => String(row.name ?? "")
									},
									{
										key: "email",
										header: "Email",
										render: (row) => String(row.email ?? "—")
									},
									{
										key: "city",
										header: "City",
										render: (row) => String(row.city ?? "—")
									},
									{
										key: "orders_count",
										header: "Orders",
										align: "right",
										sortable: true,
										value: (row) => Number(row.orders_count ?? 0),
										render: (row) => formatNumber(Number(row.orders_count ?? 0))
									},
									{
										key: "total_spent",
										header: "Spent",
										align: "right",
										sortable: true,
										value: (row) => Number(row.total_spent ?? 0),
										render: (row) => formatCurrency(Number(row.total_spent ?? 0))
									},
									{
										key: "days_since_last_order",
										header: "Days quiet",
										align: "right",
										sortable: true,
										value: (row) => Number(row.days_since_last_order ?? 0),
										render: (row) => row.days_since_last_order === null ? "—" : formatNumber(Number(row.days_since_last_order))
									}
								],
								rows: preview?.sample ?? [],
								rowKey: (row) => String(row.id),
								dense: true
							})
						}),
						preview?.caveat && /* @__PURE__ */ jsx(CaveatNote, { caveat: preview.caveat })
					]
				}), /* @__PURE__ */ jsxs("div", {
					className: "space-y-3",
					children: [
						/* @__PURE__ */ jsxs(Card, {
							className: "space-y-2 p-4",
							children: [
								/* @__PURE__ */ jsx("p", {
									className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
									children: "This segment"
								}),
								/* @__PURE__ */ jsx("p", {
									className: "text-2xl font-semibold tnum",
									children: formatNumber(preview?.member_count ?? 0)
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1 text-xs text-muted-foreground",
									children: [
										/* @__PURE__ */ jsxs("p", { children: [formatCurrency(preview?.member_value ?? 0), " of lifetime spend"] }),
										/* @__PURE__ */ jsxs("p", { children: [formatCurrency(preview?.average_aov ?? 0), " average order value"] }),
										/* @__PURE__ */ jsxs("p", { children: [formatNumber(preview?.contactable ?? 0), " opted into marketing"] })
									]
								})
							]
						}),
						can("customer_intelligence.segments.manage") && /* @__PURE__ */ jsxs(Card, {
							className: "space-y-2 p-4",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "segment-name",
										children: "Save as"
									}), /* @__PURE__ */ jsx(Input, {
										id: "segment-name",
										placeholder: "High value, gone quiet",
										value: name,
										onChange: (e) => setName(e.target.value)
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "segment-description",
										children: "Note"
									}), /* @__PURE__ */ jsx(Input, {
										id: "segment-description",
										placeholder: "Win-back list for March",
										value: description,
										onChange: (e) => setDescription(e.target.value)
									})]
								}),
								/* @__PURE__ */ jsx(Button, {
									size: "sm",
									className: "w-full",
									onClick: save,
									children: "Save segment"
								})
							]
						}),
						/* @__PURE__ */ jsxs(Card, {
							className: "space-y-2 p-4",
							children: [
								/* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold",
									children: "Saved segments"
								}),
								segments.length === 0 && /* @__PURE__ */ jsx("p", {
									className: "text-xs text-muted-foreground",
									children: "Nothing saved yet."
								}),
								segments.map((segment) => /* @__PURE__ */ jsxs("div", {
									className: "space-y-1.5 rounded-lg border border-border p-2.5",
									children: [
										/* @__PURE__ */ jsxs("div", {
											className: "flex items-start justify-between gap-2",
											children: [/* @__PURE__ */ jsxs("button", {
												type: "button",
												className: "min-w-0 text-left",
												onClick: () => setRules(segment.rules),
												children: [/* @__PURE__ */ jsx("p", {
													className: "truncate text-xs font-medium",
													children: segment.name
												}), /* @__PURE__ */ jsxs("p", {
													className: "text-[11px] text-muted-foreground",
													children: [
														formatNumber(segment.member_count),
														" people · ",
														formatCurrency(segment.member_value)
													]
												})]
											}), /* @__PURE__ */ jsxs("div", {
												className: "flex shrink-0 items-center gap-0.5",
												children: [
													/* @__PURE__ */ jsx(Button, {
														variant: "ghost",
														size: "icon",
														"aria-label": "Recount",
														onClick: async () => {
															await apiSend("POST", `/segments/${segment.id}/refresh`);
															load();
														},
														children: /* @__PURE__ */ jsx(RefreshCw, { className: "size-3.5" })
													}),
													can("customer_intelligence.segments.export") && /* @__PURE__ */ jsxs(DropdownMenu, { children: [/* @__PURE__ */ jsx(DropdownMenuTrigger, {
														asChild: true,
														children: /* @__PURE__ */ jsx(Button, {
															variant: "ghost",
															size: "icon",
															"aria-label": "Export",
															children: /* @__PURE__ */ jsx(Download, { className: "size-3.5" })
														})
													}), /* @__PURE__ */ jsx(DropdownMenuContent, {
														align: "end",
														className: "w-72",
														children: destinations.map((destination) => /* @__PURE__ */ jsxs(DropdownMenuItem, {
															className: "flex-col items-start gap-0.5",
															onClick: () => {
																window.location.href = `/api/segments/${segment.id}/export/${destination.key}`;
															},
															children: [/* @__PURE__ */ jsx("span", {
																className: "text-xs font-medium",
																children: destination.label
															}), /* @__PURE__ */ jsx("span", {
																className: "text-[11px] leading-snug text-muted-foreground",
																children: destination.note
															})]
														}, destination.key))
													})] }),
													can("customer_intelligence.segments.manage") && /* @__PURE__ */ jsx(Button, {
														variant: "ghost",
														size: "icon",
														"aria-label": "Delete segment",
														onClick: async () => {
															await apiSend("DELETE", `/segments/${segment.id}`);
															toast.success("Segment deleted.");
															load();
														},
														children: /* @__PURE__ */ jsx(Trash2, { className: "size-3.5" })
													})
												]
											})]
										}),
										segment.description && /* @__PURE__ */ jsx("p", {
											className: "text-[11px] text-muted-foreground",
											children: segment.description
										}),
										/* @__PURE__ */ jsxs(Badge, {
											variant: "muted",
											children: [
												/* @__PURE__ */ jsx(Users, { className: "mr-1 size-3" }),
												segment.rules.conditions.length,
												" rule",
												segment.rules.conditions.length === 1 ? "" : "s"
											]
										})
									]
								}, segment.id))
							]
						})
					]
				})]
			})
		]
	});
}
//#endregion
export { CustomerExplorer as default };

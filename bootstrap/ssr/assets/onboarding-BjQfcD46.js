import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { a as apiSend, i as apiGet, n as WidgetError } from "./empty-state-DjIQjBC7.js";
import { t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { Head, Link, router } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useState } from "react";
import { ArrowRight, Check, Circle, Loader2, Lock } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/onboarding/index.tsx
var STEP_ACTIONS = {
	connect: {
		label: "Go to connectors",
		href: "/connectors"
	},
	sync: {
		label: "Watch the sync",
		href: "/connectors"
	},
	costs: {
		label: "Enter costs",
		href: "/admin/users"
	},
	benchmarks: {
		label: "Set targets",
		href: "/admin/users"
	},
	team: {
		label: "Invite people",
		href: "/admin/users"
	}
};
function Onboarding() {
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [saving, setSaving] = useState(false);
	const [form, setForm] = useState({
		brand_name: "",
		categories: "",
		monthly_orders: "",
		average_order_value: "",
		gst_state: ""
	});
	const load = useCallback(() => {
		apiGet("/onboarding").then((response) => {
			setData(response.data);
			const business = response.data.business;
			if (business) setForm({
				brand_name: business.brand_name ?? "",
				categories: (business.categories ?? []).join(", "),
				monthly_orders: business.monthly_orders ? String(business.monthly_orders) : "",
				average_order_value: business.average_order_value ? String(business.average_order_value) : "",
				gst_state: business.gst_state ?? ""
			});
			setError(null);
		}).catch((err) => setError(err instanceof Error ? err.message : "Could not load your setup."));
	}, []);
	useEffect(load, [load]);
	const saveBusiness = async () => {
		setSaving(true);
		try {
			const response = await apiSend("PUT", "/onboarding/business", {
				brand_name: form.brand_name || null,
				categories: form.categories.split(",").map((value) => value.trim()).filter(Boolean),
				monthly_orders: form.monthly_orders ? Number(form.monthly_orders) : null,
				average_order_value: form.average_order_value ? Number(form.average_order_value) : null,
				gst_state: form.gst_state || null
			});
			toast.success(response.message);
			load();
			router.reload({ only: ["tenant"] });
		} catch (err) {
			toast.error(err instanceof Error ? err.message : "Could not save.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Set up your data",
		description: "Replace the sample data with your own numbers",
		showFilters: false,
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Set up your data" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: load
			}),
			!data && !error && /* @__PURE__ */ jsx(SkeletonChart, { className: "h-64" }),
			data && /* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 lg:grid-cols-[1fr_22rem]",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "space-y-3",
					children: [/* @__PURE__ */ jsxs(Card, {
						className: "p-4",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "flex items-center justify-between gap-3",
							children: [/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsxs("p", {
								className: "text-sm font-semibold",
								children: [
									data.progress.done,
									" of ",
									data.progress.total,
									" steps done"
								]
							}), /* @__PURE__ */ jsx("p", {
								className: "text-xs text-muted-foreground",
								children: "Each one is ticked from what is actually in your account, not from clicking through."
							})] }), /* @__PURE__ */ jsxs("span", {
								className: "text-2xl font-semibold tnum",
								children: [data.progress.pct, "%"]
							})]
						}), /* @__PURE__ */ jsx("div", {
							className: "mt-3 h-1.5 overflow-hidden rounded-full bg-muted",
							children: /* @__PURE__ */ jsx("div", {
								className: "h-full rounded-full bg-primary transition-all",
								style: { width: `${data.progress.pct}%` }
							})
						})]
					}), data.steps.map((step, index) => /* @__PURE__ */ jsx(Card, {
						className: cn("p-4", step.complete && "border-good/40 bg-good/5"),
						children: /* @__PURE__ */ jsxs("div", {
							className: "flex items-start gap-3",
							children: [
								/* @__PURE__ */ jsx("span", {
									className: cn("mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold", step.complete ? "bg-good text-white" : "bg-muted text-muted-foreground"),
									children: step.complete ? /* @__PURE__ */ jsx(Check, { className: "size-3.5" }) : index + 1
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "min-w-0 flex-1",
									children: [
										/* @__PURE__ */ jsxs("div", {
											className: "flex flex-wrap items-center gap-2",
											children: [
												/* @__PURE__ */ jsx("p", {
													className: "text-sm font-semibold",
													children: step.title
												}),
												step.optional && /* @__PURE__ */ jsx(Badge, {
													variant: "muted",
													children: "Optional"
												}),
												step.blocked_by && !step.complete && /* @__PURE__ */ jsxs(Badge, {
													variant: "muted",
													children: [
														/* @__PURE__ */ jsx(Lock, { className: "mr-1 size-3" }),
														" needs \"",
														step.blocked_by,
														"\" first"
													]
												})
											]
										}),
										/* @__PURE__ */ jsx("p", {
											className: "mt-1 text-xs leading-snug text-muted-foreground",
											children: step.summary
										}),
										step.evidence && /* @__PURE__ */ jsxs("p", {
											className: "mt-1.5 flex items-center gap-1.5 text-[11px] text-muted-foreground",
											children: [/* @__PURE__ */ jsx(Circle, { className: cn("size-2 fill-current", step.complete ? "text-good" : "text-muted-foreground") }), step.evidence]
										})
									]
								}),
								STEP_ACTIONS[step.key] && !step.complete && /* @__PURE__ */ jsx(Button, {
									variant: "outline",
									size: "sm",
									asChild: true,
									children: /* @__PURE__ */ jsxs(Link, {
										href: STEP_ACTIONS[step.key].href,
										children: [
											STEP_ACTIONS[step.key].label,
											" ",
											/* @__PURE__ */ jsx(ArrowRight, { className: "size-3.5" })
										]
									})
								})
							]
						})
					}, step.key))]
				}), /* @__PURE__ */ jsxs("div", {
					className: "space-y-3",
					children: [
						/* @__PURE__ */ jsxs(Card, {
							className: "space-y-3 p-4",
							children: [
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold",
									children: "Tell us about your business"
								}), /* @__PURE__ */ jsx("p", {
									className: "text-xs text-muted-foreground",
									children: "This sets your first revenue target and your GST state. You can change all of it later."
								})] }),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "brand_name",
										children: "Brand name"
									}), /* @__PURE__ */ jsx(Input, {
										id: "brand_name",
										value: form.brand_name,
										onChange: (e) => setForm((f) => ({
											...f,
											brand_name: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "categories",
										children: "What you sell"
									}), /* @__PURE__ */ jsx(Input, {
										id: "categories",
										placeholder: "Ethnic wear, Accessories",
										value: form.categories,
										onChange: (e) => setForm((f) => ({
											...f,
											categories: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "grid grid-cols-2 gap-2",
									children: [/* @__PURE__ */ jsxs("div", {
										className: "space-y-1",
										children: [/* @__PURE__ */ jsx(Label, {
											htmlFor: "monthly_orders",
											children: "Orders / month"
										}), /* @__PURE__ */ jsx(Input, {
											id: "monthly_orders",
											type: "number",
											value: form.monthly_orders,
											onChange: (e) => setForm((f) => ({
												...f,
												monthly_orders: e.target.value
											}))
										})]
									}), /* @__PURE__ */ jsxs("div", {
										className: "space-y-1",
										children: [/* @__PURE__ */ jsx(Label, {
											htmlFor: "average_order_value",
											children: "Typical order (₹)"
										}), /* @__PURE__ */ jsx(Input, {
											id: "average_order_value",
											type: "number",
											value: form.average_order_value,
											onChange: (e) => setForm((f) => ({
												...f,
												average_order_value: e.target.value
											}))
										})]
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "gst_state",
										children: "Your GST state"
									}), /* @__PURE__ */ jsx(Input, {
										id: "gst_state",
										placeholder: "Maharashtra",
										value: form.gst_state,
										onChange: (e) => setForm((f) => ({
											...f,
											gst_state: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs(Button, {
									size: "sm",
									className: "w-full",
									onClick: saveBusiness,
									disabled: saving,
									children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-3.5 animate-spin" }), " Save"]
								})
							]
						}),
						/* @__PURE__ */ jsxs(Card, {
							className: "space-y-2 p-4",
							children: [
								/* @__PURE__ */ jsx("p", {
									className: "text-sm font-semibold",
									children: "Sources you can connect now"
								}),
								data.connectors.map((connector) => /* @__PURE__ */ jsxs("div", {
									className: "rounded-lg border border-border p-2.5",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-xs font-medium",
										children: connector.label
									}), /* @__PURE__ */ jsx("p", {
										className: "mt-0.5 text-[11px] leading-snug text-muted-foreground",
										children: connector.summary
									})]
								}, connector.id)),
								/* @__PURE__ */ jsx(Button, {
									variant: "outline",
									size: "sm",
									className: "w-full",
									asChild: true,
									children: /* @__PURE__ */ jsx(Link, {
										href: "/connectors",
										children: "Open connectors"
									})
								})
							]
						}),
						data.is_demo && !data.dismissed && /* @__PURE__ */ jsx(Button, {
							variant: "ghost",
							size: "sm",
							className: "w-full",
							onClick: async () => {
								try {
									const response = await apiSend("POST", "/onboarding/dismiss");
									toast.success(response.message);
									load();
								} catch {
									toast.error("Could not save that.");
								}
							},
							children: "Keep exploring the sample data for now"
						})
					]
				})]
			})
		]
	});
}
//#endregion
export { Onboarding as default };

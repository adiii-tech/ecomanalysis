import { t as cn } from "./utils-BVTyW6jK.js";
import { r as Button } from "./input-C0zE_xzz.js";
import { a as apiSend, c as DropdownMenu, l as DropdownMenuContent, n as WidgetError, o as useFilters, p as DropdownMenuTrigger, s as usePermissions, u as DropdownMenuItem } from "./empty-state-DjIQjBC7.js";
import { i as formatCompactCurrency, n as CardContent, r as CardHeader, t as Card } from "./card-DJDNvUnK.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { n as SkeletonChart, t as Skeleton } from "./skeleton-DUakt-29.js";
import { jsx, jsxs } from "react/jsx-runtime";
import * as React from "react";
import { useCallback, useEffect, useState } from "react";
import { AlertTriangle, CheckCircle2, Download, Info, Maximize2, Minus, RefreshCw, Sparkles, TrendingDown, TrendingUp, X } from "lucide-react";
import { toast } from "sonner";
import * as TooltipPrimitive from "@radix-ui/react-tooltip";
//#region resources/js/components/ui/tooltip.tsx
var Tooltip = TooltipPrimitive.Root;
var TooltipTrigger = TooltipPrimitive.Trigger;
var TooltipContent = React.forwardRef(({ className, sideOffset = 6, ...props }, ref) => /* @__PURE__ */ jsx(TooltipPrimitive.Portal, { children: /* @__PURE__ */ jsx(TooltipPrimitive.Content, {
	ref,
	sideOffset,
	className: cn("z-50 max-w-[280px] overflow-hidden rounded-lg border border-border bg-popover px-2.5 py-1.5 text-xs text-popover-foreground shadow-md", "animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95", className),
	...props
}) }));
TooltipContent.displayName = TooltipPrimitive.Content.displayName;
//#endregion
//#region resources/js/components/app/chart-insight.tsx
/**
* The ✨ strip under a chart: two or three sentences on what the data says.
*
* Generated on demand rather than on page load — nobody wants to pay for an
* insight on 19 widgets they scrolled past.
*/
function ChartInsight({ widgetKey, title, payload, onClose }) {
	const { queryParams } = useFilters();
	const [content, setContent] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [cached, setCached] = useState(false);
	const generate = useCallback(async (refresh = false) => {
		setLoading(true);
		setError(null);
		try {
			const response = await apiSend("POST", `ai/chart-insight?${new URLSearchParams(queryParams)}`, {
				widget_key: widgetKey,
				title,
				payload: payload ?? {},
				refresh
			});
			setContent(response.data.content);
			setCached(response.data.cached);
		} catch (err) {
			setError(err instanceof Error ? err.message : "Could not generate an insight.");
		} finally {
			setLoading(false);
		}
	}, [
		widgetKey,
		title,
		payload,
		queryParams
	]);
	useEffect(() => {
		generate();
	}, []);
	return /* @__PURE__ */ jsx("div", {
		className: cn("rounded-lg border border-primary/20 bg-primary/5 px-3 py-2.5"),
		children: /* @__PURE__ */ jsxs("div", {
			className: "flex items-start gap-2",
			children: [
				/* @__PURE__ */ jsx(Sparkles, { className: "mt-0.5 size-3.5 shrink-0 text-primary" }),
				/* @__PURE__ */ jsxs("div", {
					className: "min-w-0 flex-1",
					children: [
						loading && /* @__PURE__ */ jsx(Skeleton, { className: "h-8 w-full" }),
						error && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: error
						}),
						content && /* @__PURE__ */ jsx("p", {
							className: "text-xs leading-relaxed text-foreground/90",
							children: content
						}),
						cached && !loading && /* @__PURE__ */ jsx("p", {
							className: "mt-1 text-[10px] text-muted-foreground",
							children: "Generated earlier today from the same numbers."
						})
					]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "flex shrink-0 items-center gap-0.5",
					children: [/* @__PURE__ */ jsx(Button, {
						variant: "ghost",
						size: "icon-sm",
						onClick: () => generate(true),
						disabled: loading,
						"aria-label": "Regenerate",
						children: /* @__PURE__ */ jsx(RefreshCw, { className: cn("size-3", loading && "animate-spin") })
					}), /* @__PURE__ */ jsx(Button, {
						variant: "ghost",
						size: "icon-sm",
						onClick: onClose,
						"aria-label": "Dismiss",
						children: /* @__PURE__ */ jsx(X, { className: "size-3" })
					})]
				})
			]
		})
	});
}
//#endregion
//#region resources/js/components/app/verdict-note.tsx
var STYLES = {
	scale: {
		wrap: "bg-good-soft text-good",
		icon: TrendingUp,
		label: "Scale"
	},
	good: {
		wrap: "bg-good-soft text-good",
		icon: CheckCircle2,
		label: "Good"
	},
	hold: {
		wrap: "bg-warn-soft text-warn",
		icon: Minus,
		label: "Hold"
	},
	watch: {
		wrap: "bg-warn-soft text-warn",
		icon: AlertTriangle,
		label: "Watch"
	},
	cut: {
		wrap: "bg-bad-soft text-bad",
		icon: TrendingDown,
		label: "Cut"
	},
	bad: {
		wrap: "bg-bad-soft text-bad",
		icon: AlertTriangle,
		label: "Act now"
	},
	neutral: {
		wrap: "bg-muted text-muted-foreground",
		icon: Info,
		label: "Note"
	}
};
/**
* Every widget ends in a judgement, not just a number.
*/
function VerdictNote({ verdict, className }) {
	if (!verdict) return null;
	const style = STYLES[verdict.status] ?? STYLES.neutral;
	const Icon = style.icon;
	return /* @__PURE__ */ jsxs("div", {
		className: cn("flex items-start gap-2.5 rounded-lg px-3 py-2.5", style.wrap, className),
		children: [/* @__PURE__ */ jsx(Icon, { className: "mt-px size-4 shrink-0" }), /* @__PURE__ */ jsxs("div", {
			className: "min-w-0 space-y-0.5",
			children: [
				/* @__PURE__ */ jsx("p", {
					className: "text-xs font-semibold leading-snug",
					children: verdict.headline
				}),
				verdict.detail && /* @__PURE__ */ jsx("p", {
					className: "text-[11px] leading-snug opacity-90",
					children: verdict.detail
				}),
				verdict.action && /* @__PURE__ */ jsxs("p", {
					className: "text-[11px] font-medium leading-snug opacity-95",
					children: ["→ ", verdict.action]
				}),
				verdict.impact_paise ? /* @__PURE__ */ jsxs("p", {
					className: "text-[11px] font-semibold leading-snug tnum opacity-95",
					children: ["Estimated impact: ", formatCompactCurrency(verdict.impact_paise)]
				}) : null
			]
		})]
	});
}
function VerdictBadge({ verdict }) {
	if (!verdict) return null;
	const style = STYLES[verdict.status] ?? STYLES.neutral;
	return /* @__PURE__ */ jsx("span", {
		className: cn("inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold", style.wrap),
		children: style.label
	});
}
//#endregion
//#region resources/js/hooks/use-export.ts
/**
* Downloads an export with the current global filters attached, so the file
* always matches what is on screen.
*
* The browser is navigated to the URL rather than fetched, because the response
* is a file attachment — fetch would buffer a 20,000-row export in memory for
* no reason.
*/
function useExport() {
	const { queryParams } = useFilters();
	return useCallback((dataset, format) => {
		const query = new URLSearchParams(queryParams).toString();
		const url = `/api/export/${dataset}/${format}${query ? `?${query}` : ""}`;
		toast.info(`Preparing ${format.toUpperCase()}…`, { duration: 2e3 });
		const frame = document.createElement("iframe");
		frame.style.display = "none";
		frame.src = url;
		document.body.appendChild(frame);
		window.setTimeout(() => frame.remove(), 6e4);
	}, [queryParams]);
}
//#endregion
//#region resources/js/components/app/chart-card.tsx
function ChartCard({ title, subtitle, tooltip, widgetKey, tabs, actions, onDetail, exportDataset, onExport, insightPayload, onAiInsight, verdict, caveat, loading = false, error = null, onRetry, empty = false, emptyState, className, bodyClassName, children }) {
	const { can } = usePermissions();
	const download = useExport();
	const [showInsight, setShowInsight] = useState(false);
	const canExport = widgetKey ? can(`${widgetKey}.export`) : true;
	const exportHandler = onExport ?? (exportDataset ? (format) => download(exportDataset, format) : void 0);
	const canSeeInsight = can("ai.chart_insight.view");
	return /* @__PURE__ */ jsxs(Card, {
		className: cn("flex flex-col", className),
		children: [/* @__PURE__ */ jsx(CardHeader, {
			className: "gap-2 pb-3",
			children: /* @__PURE__ */ jsxs("div", {
				className: "flex flex-wrap items-start justify-between gap-2",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "min-w-0 space-y-0.5",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "flex items-center gap-1.5",
						children: [/* @__PURE__ */ jsx("h3", {
							className: "truncate text-sm font-semibold tracking-tight",
							children: title
						}), tooltip && /* @__PURE__ */ jsxs(Tooltip, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
							asChild: true,
							children: /* @__PURE__ */ jsx("button", {
								type: "button",
								className: "text-muted-foreground/60 transition hover:text-muted-foreground",
								"aria-label": `About ${title}`,
								children: /* @__PURE__ */ jsx(Info, { className: "size-3.5" })
							})
						}), /* @__PURE__ */ jsx(TooltipContent, { children: tooltip })] })]
					}), subtitle && /* @__PURE__ */ jsx("p", {
						className: "text-xs text-muted-foreground",
						children: subtitle
					})]
				}), /* @__PURE__ */ jsxs("div", {
					className: "flex shrink-0 items-center gap-1",
					children: [
						tabs,
						actions,
						(onAiInsight || insightPayload !== void 0 && widgetKey) && canSeeInsight && /* @__PURE__ */ jsxs(Tooltip, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
							asChild: true,
							children: /* @__PURE__ */ jsx(Button, {
								variant: "ghost",
								size: "icon-sm",
								onClick: onAiInsight ?? (() => setShowInsight(true)),
								"aria-label": "Explain this chart",
								children: /* @__PURE__ */ jsx(Sparkles, { className: "size-3.5 text-primary" })
							})
						}), /* @__PURE__ */ jsx(TooltipContent, { children: "The story behind this chart" })] }),
						exportHandler && canExport && /* @__PURE__ */ jsxs(DropdownMenu, { children: [/* @__PURE__ */ jsx(DropdownMenuTrigger, {
							asChild: true,
							children: /* @__PURE__ */ jsx(Button, {
								variant: "ghost",
								size: "icon-sm",
								"aria-label": "Export",
								children: /* @__PURE__ */ jsx(Download, { className: "size-3.5" })
							})
						}), /* @__PURE__ */ jsxs(DropdownMenuContent, {
							align: "end",
							children: [
								/* @__PURE__ */ jsx(DropdownMenuItem, {
									onSelect: () => exportHandler("csv"),
									children: "Export CSV"
								}),
								/* @__PURE__ */ jsx(DropdownMenuItem, {
									onSelect: () => exportHandler("xlsx"),
									children: "Export Excel"
								}),
								/* @__PURE__ */ jsx(DropdownMenuItem, {
									onSelect: () => exportHandler("pdf"),
									children: "Export PDF"
								})
							]
						})] }),
						onDetail && /* @__PURE__ */ jsxs(Button, {
							variant: "ghost",
							size: "sm",
							onClick: onDetail,
							className: "gap-1 text-xs text-muted-foreground",
							children: [/* @__PURE__ */ jsx(Maximize2, { className: "size-3" }), "View detail"]
						})
					]
				})]
			})
		}), /* @__PURE__ */ jsxs(CardContent, {
			className: cn("flex flex-1 flex-col gap-3", bodyClassName),
			children: [
				loading ? /* @__PURE__ */ jsx(SkeletonChart, {}) : error ? /* @__PURE__ */ jsx(WidgetError, {
					message: error,
					onRetry
				}) : empty ? emptyState ?? /* @__PURE__ */ jsx("div", {
					className: "py-8 text-center text-xs text-muted-foreground",
					children: "No data in this period."
				}) : children,
				showInsight && widgetKey && !loading && !error && /* @__PURE__ */ jsx(ChartInsight, {
					widgetKey,
					title,
					payload: insightPayload,
					onClose: () => setShowInsight(false)
				}),
				!loading && !error && verdict && /* @__PURE__ */ jsx(VerdictNote, { verdict }),
				!loading && caveat && /* @__PURE__ */ jsx(CaveatNote, { caveat })
			]
		})]
	});
}
//#endregion
export { Tooltip as a, VerdictNote as i, useExport as n, TooltipContent as o, VerdictBadge as r, TooltipTrigger as s, ChartCard as t };

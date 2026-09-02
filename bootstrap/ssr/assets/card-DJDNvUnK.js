import { t as cn } from "./utils-BVTyW6jK.js";
import { jsx } from "react/jsx-runtime";
import * as React from "react";
//#region resources/js/lib/format.ts
/**
* All money crosses the wire as integer paise. Formatting — including Indian
* lakh/crore grouping — happens here at the edge and nowhere else.
*/
var PAISE = 100;
function num(value) {
	const parsed = typeof value === "number" ? value : Number(value ?? 0);
	return Number.isFinite(parsed) ? parsed : 0;
}
function groupIndian(whole) {
	if (whole.length <= 3) return whole;
	const last3 = whole.slice(-3);
	return `${whole.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ",")},${last3}`;
}
function formatCurrency(paise, options = {}) {
	const { decimals = 0, symbol = true } = options;
	const value = num(paise);
	const negative = value < 0;
	const rupees = Math.abs(value) / PAISE;
	const whole = groupIndian(Math.floor(rupees).toString());
	const fraction = decimals > 0 ? `.${Math.round(rupees % 1 * 10 ** decimals).toString().padStart(decimals, "0")}` : "";
	return `${negative ? "-" : ""}${symbol ? "₹" : ""}${whole}${fraction}`;
}
function trim(value) {
	const rounded = Math.round(value * 10) / 10;
	return Number.isInteger(rounded) ? rounded.toString() : rounded.toFixed(1);
}
/** ₹9.6L, ₹1.2Cr — the compact form used in tight card headers. */
function formatCompactCurrency(paise, symbol = true) {
	const value = num(paise);
	const negative = value < 0;
	const rupees = Math.abs(value) / PAISE;
	const prefix = `${negative ? "-" : ""}${symbol ? "₹" : ""}`;
	if (rupees >= 1e7) return `${prefix}${trim(rupees / 1e7)}Cr`;
	if (rupees >= 1e5) return `${prefix}${trim(rupees / 1e5)}L`;
	if (rupees >= 1e3) return `${prefix}${trim(rupees / 1e3)}K`;
	return `${prefix}${trim(rupees)}`;
}
function formatNumber(value, decimals = 0) {
	const parsed = num(value);
	const negative = parsed < 0;
	const abs = Math.abs(parsed);
	const whole = groupIndian(Math.floor(abs).toString());
	const fraction = decimals > 0 ? `.${(abs % 1).toFixed(decimals).slice(2)}` : "";
	return `${negative ? "-" : ""}${whole}${fraction}`;
}
function formatCompactNumber(value) {
	const parsed = num(value);
	const abs = Math.abs(parsed);
	const sign = parsed < 0 ? "-" : "";
	if (abs >= 1e7) return `${sign}${trim(abs / 1e7)}Cr`;
	if (abs >= 1e5) return `${sign}${trim(abs / 1e5)}L`;
	if (abs >= 1e3) return `${sign}${trim(abs / 1e3)}K`;
	return `${sign}${Math.round(abs)}`;
}
function formatPercent(value, decimals = 1) {
	return `${num(value).toFixed(decimals)}%`;
}
function formatRatio(value, decimals = 2) {
	return `${num(value).toFixed(decimals)}×`;
}
function formatMetric(value, format, compact = false) {
	switch (format) {
		case "currency": return compact ? formatCompactCurrency(value) : formatCurrency(value);
		case "percent": return formatPercent(value);
		case "ratio": return formatRatio(value);
		case "days": return `${formatNumber(value, 1)}d`;
		case "seconds": return `${num(value).toFixed(1)}s`;
		default: return compact ? formatCompactNumber(value) : formatNumber(value);
	}
}
function formatDelta(deltaPct) {
	if (deltaPct === null || deltaPct === void 0) return "—";
	const parsed = num(deltaPct);
	return `${parsed > 0 ? "+" : ""}${parsed.toFixed(1)}%`;
}
var DATE_FMT = new Intl.DateTimeFormat("en-IN", {
	day: "numeric",
	month: "short"
});
var DATE_TIME_FMT = new Intl.DateTimeFormat("en-IN", {
	day: "numeric",
	month: "short",
	hour: "numeric",
	minute: "2-digit"
});
var LONG_DATE_FMT = new Intl.DateTimeFormat("en-IN", {
	day: "numeric",
	month: "short",
	year: "numeric"
});
function formatDate(value) {
	return DATE_FMT.format(new Date(value));
}
function formatLongDate(value) {
	return LONG_DATE_FMT.format(new Date(value));
}
function formatDateTime(value) {
	return DATE_TIME_FMT.format(new Date(value));
}
//#endregion
//#region resources/js/components/ui/card.tsx
var Card = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx("div", {
	ref,
	className: cn("rounded-(--radius-card) border border-border bg-card text-card-foreground shadow-xs", className),
	...props
}));
Card.displayName = "Card";
var CardHeader = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx("div", {
	ref,
	className: cn("flex flex-col gap-1 p-4 sm:p-5", className),
	...props
}));
CardHeader.displayName = "CardHeader";
var CardTitle = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx("h3", {
	ref,
	className: cn("text-sm font-semibold leading-tight tracking-tight", className),
	...props
}));
CardTitle.displayName = "CardTitle";
var CardDescription = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx("p", {
	ref,
	className: cn("text-xs text-muted-foreground", className),
	...props
}));
CardDescription.displayName = "CardDescription";
var CardContent = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx("div", {
	ref,
	className: cn("p-4 pt-0 sm:p-5 sm:pt-0", className),
	...props
}));
CardContent.displayName = "CardContent";
var CardFooter = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx("div", {
	ref,
	className: cn("flex items-center gap-2 border-t border-border/70 px-4 py-3 sm:px-5", className),
	...props
}));
CardFooter.displayName = "CardFooter";
//#endregion
export { formatCompactNumber as a, formatDateTime as c, formatMetric as d, formatNumber as f, formatCompactCurrency as i, formatDelta as l, formatRatio as m, CardContent as n, formatCurrency as o, formatPercent as p, CardHeader as r, formatDate as s, Card as t, formatLongDate as u };

import { t as cn } from "./utils-BVTyW6jK.js";
import { r as Button } from "./input-C0zE_xzz.js";
import { usePage } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import * as React from "react";
import { createContext, useContext, useMemo } from "react";
import { Check, PartyPopper, PlugZap, SearchX, TriangleAlert } from "lucide-react";
import * as DropdownMenuPrimitive from "@radix-ui/react-dropdown-menu";
//#region resources/js/components/ui/dropdown-menu.tsx
var DropdownMenu = DropdownMenuPrimitive.Root;
var DropdownMenuTrigger = DropdownMenuPrimitive.Trigger;
var DropdownMenuContent = React.forwardRef(({ className, sideOffset = 6, ...props }, ref) => /* @__PURE__ */ jsx(DropdownMenuPrimitive.Portal, { children: /* @__PURE__ */ jsx(DropdownMenuPrimitive.Content, {
	ref,
	sideOffset,
	className: cn("z-50 min-w-[10rem] overflow-hidden rounded-xl border border-border bg-popover p-1 text-popover-foreground shadow-lg", "data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95", "data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95", className),
	...props
}) }));
DropdownMenuContent.displayName = DropdownMenuPrimitive.Content.displayName;
var DropdownMenuItem = React.forwardRef(({ className, inset, ...props }, ref) => /* @__PURE__ */ jsx(DropdownMenuPrimitive.Item, {
	ref,
	className: cn("relative flex cursor-pointer select-none items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm outline-none transition-colors", "focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50", "[&_svg]:size-4 [&_svg]:shrink-0 [&_svg]:text-muted-foreground", inset && "pl-8", className),
	...props
}));
DropdownMenuItem.displayName = DropdownMenuPrimitive.Item.displayName;
var DropdownMenuCheckboxItem = React.forwardRef(({ className, children, checked, ...props }, ref) => /* @__PURE__ */ jsxs(DropdownMenuPrimitive.CheckboxItem, {
	ref,
	checked,
	className: cn("relative flex cursor-pointer select-none items-center rounded-lg py-1.5 pl-8 pr-2.5 text-sm outline-none transition-colors focus:bg-accent", className),
	...props,
	children: [/* @__PURE__ */ jsx("span", {
		className: "absolute left-2.5 flex size-3.5 items-center justify-center",
		children: /* @__PURE__ */ jsx(DropdownMenuPrimitive.ItemIndicator, { children: /* @__PURE__ */ jsx(Check, { className: "size-3.5" }) })
	}), children]
}));
DropdownMenuCheckboxItem.displayName = DropdownMenuPrimitive.CheckboxItem.displayName;
var DropdownMenuLabel = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(DropdownMenuPrimitive.Label, {
	ref,
	className: cn("px-2.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground", className),
	...props
}));
DropdownMenuLabel.displayName = DropdownMenuPrimitive.Label.displayName;
var DropdownMenuSeparator = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(DropdownMenuPrimitive.Separator, {
	ref,
	className: cn("-mx-1 my-1 h-px bg-border", className),
	...props
}));
DropdownMenuSeparator.displayName = DropdownMenuPrimitive.Separator.displayName;
//#endregion
//#region resources/js/hooks/use-permissions.ts
/**
* The flattened permission list is shared on every Inertia response and cached
* here; it busts whenever `permissions_version` changes server-side.
*/
function usePermissions() {
	const { auth } = usePage().props;
	return useMemo(() => {
		const set = new Set(auth.permissions ?? []);
		return {
			can: (permission) => set.has(permission),
			canAny: (...permissions) => permissions.some((p) => set.has(p)),
			canAll: (...permissions) => permissions.every((p) => set.has(p)),
			all: auth.permissions ?? []
		};
	}, [auth.permissions, auth.user?.permissions_version]);
}
//#endregion
//#region resources/js/hooks/use-filters.tsx
var FilterContext = createContext(null);
function useFilters() {
	const context = useContext(FilterContext);
	if (!context) throw new Error("useFilters must be used inside a <FilterProvider>");
	return context;
}
//#endregion
//#region resources/js/lib/api.ts
var ApiError = class extends Error {
	status;
	requiredPermission;
	constructor(message, status, requiredPermission) {
		super(message);
		this.status = status;
		this.requiredPermission = requiredPermission;
		this.name = "ApiError";
	}
};
function csrfToken() {
	return document.querySelector("meta[name=\"csrf-token\"]")?.content ?? "";
}
async function apiGet(path, params = {}, signal) {
	const query = new URLSearchParams();
	Object.entries(params).forEach(([key, value]) => {
		if (value !== null && value !== void 0 && value !== "") query.set(key, String(value));
	});
	const url = `/api/${path.replace(/^\//, "")}${query.toString() ? `?${query}` : ""}`;
	const response = await fetch(url, {
		headers: {
			Accept: "application/json",
			"X-Requested-With": "XMLHttpRequest"
		},
		credentials: "same-origin",
		signal
	});
	const body = await response.json().catch(() => null);
	if (!response.ok || !body?.success) throw new ApiError(body?.message ?? `Request failed (${response.status})`, response.status, body?.meta?.required_permission ?? void 0);
	return body;
}
async function apiSend(method, path, payload = {}) {
	const response = await fetch(`/api/${path.replace(/^\//, "")}`, {
		method,
		headers: {
			Accept: "application/json",
			"Content-Type": "application/json",
			"X-Requested-With": "XMLHttpRequest",
			"X-CSRF-TOKEN": csrfToken()
		},
		credentials: "same-origin",
		body: JSON.stringify(payload)
	});
	const body = await response.json().catch(() => null);
	if (!response.ok || !body?.success) throw new ApiError(body?.message ?? `Request failed (${response.status})`, response.status);
	return body;
}
//#endregion
//#region resources/js/components/app/empty-state.tsx
var ICONS = {
	empty: SearchX,
	connector: PlugZap,
	error: TriangleAlert,
	celebrate: PartyPopper
};
function EmptyState({ kind = "empty", title, description, action, className, compact = false }) {
	const Icon = ICONS[kind];
	return /* @__PURE__ */ jsxs("div", {
		className: cn("flex flex-col items-center justify-center gap-2 text-center", compact ? "py-6" : "py-12", className),
		children: [
			/* @__PURE__ */ jsx("span", {
				className: cn("flex size-9 items-center justify-center rounded-full", kind === "error" ? "bg-bad-soft text-bad" : kind === "celebrate" ? "bg-good-soft text-good" : "bg-muted text-muted-foreground"),
				children: /* @__PURE__ */ jsx(Icon, { className: "size-4" })
			}),
			/* @__PURE__ */ jsx("p", {
				className: "text-sm font-medium",
				children: title
			}),
			description && /* @__PURE__ */ jsx("p", {
				className: "max-w-sm text-xs text-muted-foreground",
				children: description
			}),
			action
		]
	});
}
function WidgetError({ message, onRetry }) {
	return /* @__PURE__ */ jsx(EmptyState, {
		kind: "error",
		compact: true,
		title: "Could not load this widget",
		description: message,
		action: onRetry ? /* @__PURE__ */ jsx(Button, {
			size: "sm",
			variant: "outline",
			onClick: onRetry,
			className: "mt-1",
			children: "Try again"
		}) : void 0
	});
}
//#endregion
export { apiSend as a, DropdownMenu as c, DropdownMenuLabel as d, DropdownMenuSeparator as f, apiGet as i, DropdownMenuContent as l, WidgetError as n, useFilters as o, DropdownMenuTrigger as p, ApiError as r, usePermissions as s, EmptyState as t, DropdownMenuItem as u };

import { t as cn } from "./utils-BVTyW6jK.js";
import { jsx, jsxs } from "react/jsx-runtime";
import * as React from "react";
import { X } from "lucide-react";
import * as SheetPrimitive from "@radix-ui/react-dialog";
//#region resources/js/components/ui/sheet.tsx
var Sheet = SheetPrimitive.Root;
var SheetOverlay = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(SheetPrimitive.Overlay, {
	ref,
	className: cn("fixed inset-0 z-50 bg-black/45 backdrop-blur-[1px]", "data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=closed]:animate-out data-[state=closed]:fade-out-0", className),
	...props
}));
SheetOverlay.displayName = SheetPrimitive.Overlay.displayName;
var SheetContent = React.forwardRef(({ className, children, side = "right", ...props }, ref) => /* @__PURE__ */ jsxs(SheetPrimitive.Portal, { children: [/* @__PURE__ */ jsx(SheetOverlay, {}), /* @__PURE__ */ jsxs(SheetPrimitive.Content, {
	ref,
	className: cn("fixed z-50 flex flex-col gap-0 bg-card shadow-xl transition ease-in-out", "data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:duration-200 data-[state=open]:duration-300", side === "right" && "inset-y-0 right-0 h-full w-full border-l sm:max-w-3xl data-[state=closed]:slide-out-to-right data-[state=open]:slide-in-from-right", side === "left" && "inset-y-0 left-0 h-full w-full border-r sm:max-w-sm data-[state=closed]:slide-out-to-left data-[state=open]:slide-in-from-left", side === "bottom" && "inset-x-0 bottom-0 max-h-[85vh] rounded-t-2xl border-t data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom", className),
	...props,
	children: [children, /* @__PURE__ */ jsxs(SheetPrimitive.Close, {
		className: "absolute right-4 top-4 rounded-md p-1 text-muted-foreground opacity-70 transition hover:bg-accent hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring",
		children: [/* @__PURE__ */ jsx(X, { className: "size-4" }), /* @__PURE__ */ jsx("span", {
			className: "sr-only",
			children: "Close"
		})]
	})]
})] }));
SheetContent.displayName = SheetPrimitive.Content.displayName;
function SheetHeader({ className, ...props }) {
	return /* @__PURE__ */ jsx("div", {
		className: cn("flex flex-col gap-1 border-b border-border px-5 py-4 pr-12", className),
		...props
	});
}
var SheetTitle = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(SheetPrimitive.Title, {
	ref,
	className: cn("text-base font-semibold tracking-tight", className),
	...props
}));
SheetTitle.displayName = SheetPrimitive.Title.displayName;
var SheetDescription = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(SheetPrimitive.Description, {
	ref,
	className: cn("text-xs text-muted-foreground", className),
	...props
}));
SheetDescription.displayName = SheetPrimitive.Description.displayName;
//#endregion
export { SheetTitle as a, SheetHeader as i, SheetContent as n, SheetDescription as r, Sheet as t };

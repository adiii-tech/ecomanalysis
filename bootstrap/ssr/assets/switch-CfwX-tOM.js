import { t as cn } from "./utils-BVTyW6jK.js";
import { jsx } from "react/jsx-runtime";
import * as React from "react";
import * as SwitchPrimitive from "@radix-ui/react-switch";
//#region resources/js/components/ui/switch.tsx
var Switch = React.forwardRef(({ className, ...props }, ref) => /* @__PURE__ */ jsx(SwitchPrimitive.Root, {
	ref,
	className: cn("peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors", "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background", "disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input", className),
	...props,
	children: /* @__PURE__ */ jsx(SwitchPrimitive.Thumb, { className: "pointer-events-none block size-4 rounded-full bg-card shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-4 data-[state=unchecked]:translate-x-0" })
}));
Switch.displayName = SwitchPrimitive.Root.displayName;
//#endregion
export { Switch as t };

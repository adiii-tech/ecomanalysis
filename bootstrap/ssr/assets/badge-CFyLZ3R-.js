import { t as cn } from "./utils-BVTyW6jK.js";
import { jsx } from "react/jsx-runtime";
import "react";
import { cva } from "class-variance-authority";
//#region resources/js/components/ui/badge.tsx
var badgeVariants = cva("inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium leading-none transition-colors", {
	variants: { variant: {
		default: "border-transparent bg-primary/10 text-primary",
		secondary: "border-transparent bg-secondary text-secondary-foreground",
		outline: "border-border text-muted-foreground",
		good: "border-transparent bg-good-soft text-good",
		bad: "border-transparent bg-bad-soft text-bad",
		warn: "border-transparent bg-warn-soft text-warn",
		muted: "border-transparent bg-muted text-muted-foreground"
	} },
	defaultVariants: { variant: "default" }
});
function Badge({ className, variant, ...props }) {
	return /* @__PURE__ */ jsx("span", {
		className: cn(badgeVariants({ variant }), className),
		...props
	});
}
//#endregion
export { Badge as t };

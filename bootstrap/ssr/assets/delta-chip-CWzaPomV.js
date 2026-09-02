import { t as cn } from "./utils-BVTyW6jK.js";
import { l as formatDelta } from "./card-DJDNvUnK.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { ArrowDownRight, ArrowRight, ArrowUpRight } from "lucide-react";
//#region resources/js/components/app/delta-chip.tsx
/**
* Colour encodes *goodness*, never direction — a fall in RTO is green.
*/
function DeltaChip({ deltaPct, isGood, direction, className, size = "default" }) {
	if (deltaPct === null || deltaPct === void 0) return /* @__PURE__ */ jsx("span", {
		className: cn("text-xs text-muted-foreground", className),
		children: "no prior data"
	});
	const Icon = direction === "up" ? ArrowUpRight : direction === "down" ? ArrowDownRight : ArrowRight;
	return /* @__PURE__ */ jsxs("span", {
		className: cn("inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 font-medium tnum", size === "sm" ? "text-[10px]" : "text-[11px]", isGood === null && "bg-muted text-muted-foreground", isGood === true && "bg-good-soft text-good", isGood === false && "bg-bad-soft text-bad", className),
		children: [/* @__PURE__ */ jsx(Icon, { className: size === "sm" ? "size-2.5" : "size-3" }), formatDelta(deltaPct)]
	});
}
//#endregion
export { DeltaChip as t };

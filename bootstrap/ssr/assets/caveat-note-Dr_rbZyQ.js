import { t as cn } from "./utils-BVTyW6jK.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { Info, TriangleAlert } from "lucide-react";
//#region resources/js/components/app/caveat-note.tsx
/**
* Data-honesty disclosure. If a number cannot be computed truthfully we print
* this instead of fabricating one.
*/
function CaveatNote({ caveat, className }) {
	if (!caveat) return null;
	const message = typeof caveat === "string" ? caveat : caveat.message;
	const level = typeof caveat === "string" ? "info" : caveat.level;
	const Icon = level === "warning" ? TriangleAlert : Info;
	return /* @__PURE__ */ jsxs("p", {
		className: cn("flex items-start gap-1.5 text-[11px] leading-snug", level === "warning" ? "text-warn" : "text-muted-foreground", className),
		children: [/* @__PURE__ */ jsx(Icon, { className: "mt-px size-3 shrink-0" }), /* @__PURE__ */ jsx("span", { children: message })]
	});
}
//#endregion
export { CaveatNote as t };

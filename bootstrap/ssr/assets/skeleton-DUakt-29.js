import { t as cn } from "./utils-BVTyW6jK.js";
import { jsx, jsxs } from "react/jsx-runtime";
//#region resources/js/components/ui/skeleton.tsx
/** Skeletons everywhere — never spinners. */
function Skeleton({ className, ...props }) {
	return /* @__PURE__ */ jsx("div", {
		className: cn("skeleton rounded-md", className),
		...props
	});
}
function SkeletonChart({ className }) {
	return /* @__PURE__ */ jsx("div", {
		className: cn("flex h-[220px] items-end gap-1.5", className),
		children: Array.from({ length: 24 }).map((_, index) => /* @__PURE__ */ jsx(Skeleton, {
			className: "flex-1 rounded-sm",
			style: { height: `${28 + index * 37 % 62}%` }
		}, index))
	});
}
function SkeletonTable({ rows = 5, cols = 4 }) {
	return /* @__PURE__ */ jsxs("div", {
		className: "space-y-2",
		children: [/* @__PURE__ */ jsx(Skeleton, { className: "h-8 w-full" }), Array.from({ length: rows }).map((_, rowIndex) => /* @__PURE__ */ jsx("div", {
			className: "flex gap-3",
			children: Array.from({ length: cols }).map((_, colIndex) => /* @__PURE__ */ jsx(Skeleton, { className: cn("h-6", colIndex === 0 ? "flex-[2]" : "flex-1") }, colIndex))
		}, rowIndex))]
	});
}
//#endregion
export { SkeletonChart as n, SkeletonTable as r, Skeleton as t };

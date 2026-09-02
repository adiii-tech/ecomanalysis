import { t as cn } from "./utils-BVTyW6jK.js";
import { t as Card, u as formatLongDate } from "./card-DJDNvUnK.js";
import { Head } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { Sparkles } from "lucide-react";
//#region resources/js/pages/ai/shared.tsx
/**
* A publicly shared answer. Read-only, and deliberately carries no navigation
* into the app — the link grants access to this conversation and nothing else.
*/
function SharedAnswer({ title, brand, sharedAt, messages }) {
	return /* @__PURE__ */ jsxs("div", {
		className: "min-h-screen bg-background px-4 py-10",
		children: [/* @__PURE__ */ jsx(Head, { title }), /* @__PURE__ */ jsxs("div", {
			className: "mx-auto max-w-2xl space-y-4",
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1",
					children: [
						/* @__PURE__ */ jsxs("p", {
							className: "flex items-center gap-1.5 text-xs text-muted-foreground",
							children: [
								/* @__PURE__ */ jsx(Sparkles, { className: "size-3.5 text-primary" }),
								"Shared analysis",
								brand ? ` from ${brand}` : ""
							]
						}),
						/* @__PURE__ */ jsx("h1", {
							className: "text-xl font-semibold tracking-tight",
							children: title
						}),
						sharedAt && /* @__PURE__ */ jsxs("p", {
							className: "text-xs text-muted-foreground",
							children: ["Shared ", formatLongDate(sharedAt)]
						})
					]
				}),
				/* @__PURE__ */ jsx("div", {
					className: "space-y-3",
					children: messages.map((message, index) => /* @__PURE__ */ jsxs(Card, {
						className: cn("p-4", message.role === "user" && "border-primary/25 bg-primary/5"),
						children: [/* @__PURE__ */ jsx("p", {
							className: "mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground",
							children: message.role === "user" ? "Question" : "Answer"
						}), /* @__PURE__ */ jsx("p", {
							className: "whitespace-pre-wrap text-sm leading-relaxed",
							children: message.content
						})]
					}, index))
				}),
				/* @__PURE__ */ jsx("p", {
					className: "pt-2 text-center text-[11px] text-muted-foreground",
					children: "This is a read-only snapshot. The numbers were correct when it was shared."
				})
			]
		})]
	});
}
//#endregion
export { SharedAnswer as default };

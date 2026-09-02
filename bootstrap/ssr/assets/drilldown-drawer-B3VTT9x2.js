import { r as Button } from "./input-C0zE_xzz.js";
import { i as VerdictNote, n as useExport } from "./chart-card-CZPTjzl-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { Download } from "lucide-react";
//#region resources/js/components/app/drilldown-drawer.tsx
/**
* Every widget drills down to row-level truth. The drawer is that truth:
* searchable, sortable, exportable.
*/
function DrilldownDrawer({ open, onOpenChange, title, description, columns, rows, loading, rowKey, verdict, caveat, exportDataset, onExport, header, emptyTitle = "No rows for this selection" }) {
	const download = useExport();
	const exportHandler = onExport ?? (exportDataset ? (format) => download(exportDataset, format) : void 0);
	return /* @__PURE__ */ jsx(Sheet, {
		open,
		onOpenChange,
		children: /* @__PURE__ */ jsxs(SheetContent, {
			side: "right",
			className: "w-full sm:max-w-4xl",
			children: [
				/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: title }), description && /* @__PURE__ */ jsx(SheetDescription, { children: description })] }),
				/* @__PURE__ */ jsxs("div", {
					className: "flex-1 space-y-3 overflow-auto p-5 scrollbar-thin",
					children: [
						header,
						verdict && /* @__PURE__ */ jsx(VerdictNote, { verdict }),
						/* @__PURE__ */ jsx(DataTable, {
							columns,
							rows,
							loading,
							searchable: true,
							rowKey,
							emptyTitle
						}),
						caveat && /* @__PURE__ */ jsx(CaveatNote, { caveat })
					]
				}),
				exportHandler && /* @__PURE__ */ jsx("div", {
					className: "flex items-center justify-end gap-2 border-t border-border px-5 py-3",
					children: [
						"csv",
						"xlsx",
						"pdf"
					].map((format) => /* @__PURE__ */ jsxs(Button, {
						variant: "outline",
						size: "sm",
						onClick: () => exportHandler(format),
						className: "gap-1.5",
						children: [/* @__PURE__ */ jsx(Download, { className: "size-3.5" }), format.toUpperCase()]
					}, format))
				})
			]
		})
	});
}
//#endregion
export { DrilldownDrawer as t };

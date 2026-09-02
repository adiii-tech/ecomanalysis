import { t as cn } from "./utils-BVTyW6jK.js";
import { t as Input } from "./input-C0zE_xzz.js";
import { t as EmptyState } from "./empty-state-DjIQjBC7.js";
import { a as Tooltip, o as TooltipContent, s as TooltipTrigger } from "./chart-card-CZPTjzl-.js";
import { r as SkeletonTable } from "./skeleton-DUakt-29.js";
import { jsx, jsxs } from "react/jsx-runtime";
import { useMemo, useState } from "react";
import { ArrowDown, ArrowUp, ChevronsUpDown, Search } from "lucide-react";
//#region resources/js/components/app/data-table.tsx
function DataTable({ columns, rows, loading = false, searchable = false, searchPlaceholder = "Search…", emptyTitle = "Nothing to show", emptyDescription, stickyHeader = true, rowKey, onRowClick, initialSort, maxHeight, dense = false, footer }) {
	const [sort, setSort] = useState(initialSort ?? null);
	const [query, setQuery] = useState("");
	const processed = useMemo(() => {
		let result = rows ?? [];
		if (query.trim() !== "") {
			const needle = query.trim().toLowerCase();
			result = result.filter((row) => columns.some((column) => {
				const value = column.value?.(row);
				return value !== null && value !== void 0 && String(value).toLowerCase().includes(needle);
			}));
		}
		if (sort) {
			const column = columns.find((c) => c.key === sort.key);
			if (column?.value) result = [...result].sort((a, b) => {
				const left = column.value(a);
				const right = column.value(b);
				if (left === right) return 0;
				if (left === null || left === void 0) return 1;
				if (right === null || right === void 0) return -1;
				const compare = typeof left === "number" && typeof right === "number" ? left - right : String(left).localeCompare(String(right));
				return sort.direction === "asc" ? compare : -compare;
			});
		}
		return result;
	}, [
		rows,
		columns,
		sort,
		query
	]);
	function toggleSort(key) {
		setSort((current) => current?.key === key ? {
			key,
			direction: current.direction === "asc" ? "desc" : "asc"
		} : {
			key,
			direction: "desc"
		});
	}
	if (loading) return /* @__PURE__ */ jsx(SkeletonTable, {
		rows: 6,
		cols: Math.min(columns.length, 5)
	});
	return /* @__PURE__ */ jsxs("div", {
		className: "space-y-2",
		children: [searchable && /* @__PURE__ */ jsxs("div", {
			className: "relative",
			children: [/* @__PURE__ */ jsx(Search, { className: "pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" }), /* @__PURE__ */ jsx(Input, {
				value: query,
				onChange: (event) => setQuery(event.target.value),
				placeholder: searchPlaceholder,
				className: "h-8 pl-8 text-xs"
			})]
		}), processed.length === 0 ? /* @__PURE__ */ jsx(EmptyState, {
			compact: true,
			title: emptyTitle,
			description: emptyDescription
		}) : /* @__PURE__ */ jsx("div", {
			className: cn("overflow-auto scrollbar-thin", maxHeight),
			style: maxHeight ? void 0 : void 0,
			children: /* @__PURE__ */ jsxs("table", {
				className: "w-full border-collapse text-sm",
				children: [
					/* @__PURE__ */ jsx("thead", {
						className: cn(stickyHeader && "sticky top-0 z-10 bg-card"),
						children: /* @__PURE__ */ jsx("tr", {
							className: "border-b border-border",
							children: columns.map((column) => /* @__PURE__ */ jsx("th", {
								style: column.width ? { width: column.width } : void 0,
								className: cn("whitespace-nowrap px-2.5 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground", column.align === "right" && "text-right", column.align === "center" && "text-center", !column.align && "text-left"),
								children: /* @__PURE__ */ jsxs("span", {
									className: cn("inline-flex items-center gap-1", column.align === "right" && "flex-row-reverse"),
									children: [column.tooltip ? /* @__PURE__ */ jsxs(Tooltip, { children: [/* @__PURE__ */ jsx(TooltipTrigger, {
										asChild: true,
										children: /* @__PURE__ */ jsx("span", {
											className: "cursor-help border-b border-dotted border-muted-foreground/40",
											children: column.header
										})
									}), /* @__PURE__ */ jsx(TooltipContent, { children: column.tooltip })] }) : column.header, column.sortable && /* @__PURE__ */ jsx("button", {
										type: "button",
										onClick: () => toggleSort(column.key),
										className: "text-muted-foreground/50 transition hover:text-foreground",
										"aria-label": `Sort by ${column.header}`,
										children: sort?.key === column.key ? sort.direction === "asc" ? /* @__PURE__ */ jsx(ArrowUp, { className: "size-3" }) : /* @__PURE__ */ jsx(ArrowDown, { className: "size-3" }) : /* @__PURE__ */ jsx(ChevronsUpDown, { className: "size-3" })
									})]
								})
							}, column.key))
						})
					}),
					/* @__PURE__ */ jsx("tbody", { children: processed.map((row, index) => /* @__PURE__ */ jsx("tr", {
						onClick: onRowClick ? () => onRowClick(row) : void 0,
						className: cn("border-b border-border/60 transition-colors last:border-0", onRowClick && "cursor-pointer hover:bg-accent/50"),
						children: columns.map((column) => /* @__PURE__ */ jsx("td", {
							className: cn("px-2.5 align-middle", dense ? "py-1.5" : "py-2.5", column.align === "right" && "text-right tnum", column.align === "center" && "text-center", column.className),
							children: column.render(row, index)
						}, column.key))
					}, rowKey(row, index))) }),
					footer && /* @__PURE__ */ jsx("tfoot", {
						className: "border-t-2 border-border bg-muted/40",
						children: footer
					})
				]
			})
		})]
	});
}
//#endregion
export { DataTable as t };

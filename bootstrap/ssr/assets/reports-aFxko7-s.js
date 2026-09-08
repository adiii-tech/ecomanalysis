import { t as cn } from "./utils-BVTyW6jK.js";
import { r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { a as apiSend, n as WidgetError } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { Head, Link } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useMemo, useState } from "react";
import { Clock, FileBarChart, Search, Star } from "lucide-react";
//#region resources/js/pages/reports/index.tsx
function Reports() {
	const { data, loading, error, reload } = useWidget("/reports");
	const [query, setQuery] = useState("");
	const [category, setCategory] = useState(null);
	const [sort, setSort] = useState("a-z");
	const [favourites, setFavourites] = useState({});
	const reports = useMemo(() => (data?.reports ?? []).map((report) => ({
		...report,
		is_favourite: favourites[report.key] ?? report.is_favourite ?? false
	})), [data, favourites]);
	const toggleFavourite = useCallback(async (report, next) => {
		setFavourites((current) => ({
			...current,
			[report.key]: next
		}));
		try {
			await apiSend("POST", `/reports/${report.slug}/favourite`);
		} catch {
			setFavourites((current) => ({
				...current,
				[report.key]: !next
			}));
		}
	}, []);
	const filtered = useMemo(() => {
		const needle = query.trim().toLowerCase();
		const matches = reports.filter((report) => {
			const matchesCategory = category === null || report.category === category;
			const matchesQuery = needle === "" || report.label.toLowerCase().includes(needle) || report.description.toLowerCase().includes(needle) || report.category.toLowerCase().includes(needle);
			return matchesCategory && matchesQuery;
		});
		if (sort === "favourites") return matches.filter((report) => report.is_favourite);
		if (sort === "recent") return [...matches].sort((left, right) => (right.last_used_at ?? "").localeCompare(left.last_used_at ?? ""));
		return [...matches].sort((left, right) => left.label.localeCompare(right.label));
	}, [
		reports,
		query,
		category,
		sort
	]);
	const counts = useMemo(() => {
		const map = {};
		reports.forEach((report) => {
			map[report.category] = (map[report.category] ?? 0) + 1;
		});
		return map;
	}, [reports]);
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Report library",
		description: `${reports.length} reports available to you`,
		showFilters: false,
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Reports" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: reload
			}),
			loading && !data && /* @__PURE__ */ jsx("div", {
				className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-3",
				children: [
					0,
					1,
					2,
					3,
					4,
					5
				].map((index) => /* @__PURE__ */ jsx(SkeletonChart, { className: "h-24" }, index))
			}),
			data && /* @__PURE__ */ jsxs(Fragment, { children: [
				/* @__PURE__ */ jsx("div", {
					className: "grid grid-cols-2 gap-3 lg:grid-cols-5",
					children: (data.categories ?? []).filter((name) => counts[name]).map((name) => /* @__PURE__ */ jsxs("button", {
						type: "button",
						onClick: () => setCategory((current) => current === name ? null : name),
						className: cn("rounded-(--radius-card) border p-4 text-left transition-colors", category === name ? "border-primary bg-primary/5" : "border-border bg-card hover:bg-accent/50"),
						children: [/* @__PURE__ */ jsx("p", {
							className: "text-[11px] font-medium uppercase tracking-wide text-muted-foreground",
							children: name
						}), /* @__PURE__ */ jsx("p", {
							className: "mt-2 text-xl font-semibold tnum",
							children: counts[name]
						})]
					}, name))
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "flex flex-wrap items-center gap-2",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "relative min-w-64 flex-1 sm:max-w-md",
						children: [/* @__PURE__ */ jsx(Search, { className: "pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" }), /* @__PURE__ */ jsx(Input, {
							value: query,
							onChange: (event) => setQuery(event.target.value),
							placeholder: "Search reports…",
							className: "pl-8"
						})]
					}), /* @__PURE__ */ jsx("div", {
						className: "flex items-center gap-1",
						children: [
							"a-z",
							"recent",
							"favourites"
						].map((option) => /* @__PURE__ */ jsx(Button, {
							variant: sort === option ? "secondary" : "ghost",
							size: "sm",
							onClick: () => setSort(option),
							children: option === "a-z" ? "A–Z" : option === "recent" ? "Recently used" : "Favourites"
						}, option))
					})]
				}),
				/* @__PURE__ */ jsx("div", {
					className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-3",
					children: filtered.map((report) => /* @__PURE__ */ jsxs(Card, {
						className: "group relative h-full p-4 transition-shadow hover:shadow-md",
						children: [/* @__PURE__ */ jsx("button", {
							type: "button",
							onClick: () => toggleFavourite(report, !report.is_favourite),
							className: "absolute right-3 top-3 text-muted-foreground transition-colors hover:text-warn",
							"aria-label": report.is_favourite ? "Remove from favourites" : "Add to favourites",
							children: /* @__PURE__ */ jsx(Star, { className: cn("size-4", report.is_favourite && "fill-warn text-warn") })
						}), /* @__PURE__ */ jsxs(Link, {
							href: `/reports/${report.slug}`,
							className: "flex items-start gap-2.5",
							children: [/* @__PURE__ */ jsx("span", {
								className: "flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary",
								children: /* @__PURE__ */ jsx(FileBarChart, { className: "size-4" })
							}), /* @__PURE__ */ jsxs("div", {
								className: "min-w-0 pr-6",
								children: [
									/* @__PURE__ */ jsx("p", {
										className: "truncate text-sm font-semibold",
										children: report.label
									}),
									/* @__PURE__ */ jsx(Badge, {
										variant: "muted",
										className: "mt-1",
										children: report.category
									}),
									/* @__PURE__ */ jsx("p", {
										className: "mt-1.5 text-xs leading-snug text-muted-foreground",
										children: report.description
									}),
									report.last_used_at && /* @__PURE__ */ jsxs("p", {
										className: "mt-1.5 flex items-center gap-1 text-[11px] text-muted-foreground",
										children: [
											/* @__PURE__ */ jsx(Clock, { className: "size-3" }),
											" opened ",
											formatDateTime(report.last_used_at)
										]
									})
								]
							})]
						})]
					}, report.key))
				}),
				filtered.length === 0 && /* @__PURE__ */ jsx("p", {
					className: "py-12 text-center text-sm text-muted-foreground",
					children: sort === "favourites" ? "No favourites yet — star a report to pin it here." : "No report matches that search."
				})
			] })
		]
	});
}
//#endregion
export { Reports as default };

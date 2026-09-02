import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-Drf1OqZl.js";
import { a as apiSend, i as apiGet, s as usePermissions } from "./empty-state-DjIQjBC7.js";
import { f as formatNumber, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { t as useWidget } from "./use-widget-CMYArvtl.js";
import { Head, router, usePage } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useEffect, useState } from "react";
import { CheckCircle2, ExternalLink, Loader2, PlugZap, RefreshCw, Settings2, TriangleAlert } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/connectors/index.tsx
var STATUS_DOT = {
	connected: "bg-good",
	syncing: "bg-primary animate-pulse",
	error: "bg-bad",
	needs_setup: "bg-warn",
	disconnected: "bg-muted-foreground/40"
};
function Connectors() {
	const { flash } = usePage().props;
	const [tab, setTab] = useState("live");
	const [authorizing, setAuthorizing] = useState(null);
	const [configuring, setConfiguring] = useState(null);
	const [tokenForm, setTokenForm] = useState(null);
	const [values, setValues] = useState({});
	const [busy, setBusy] = useState(null);
	const { can } = usePermissions();
	const connectors = useWidget("connectors");
	const runs = useWidget("connectors/sync-runs");
	useEffect(() => {
		if (flash.error) toast.error(flash.error);
		if (flash.success) toast.success(flash.success);
		const connected = new URLSearchParams(window.location.search).get("connected");
		if (!connected || !connectors.data) return;
		const row = [...connectors.data.live, ...connectors.data.phase_two].find((c) => c.id === connected);
		if (row && Object.keys(row.pending_selections).length > 0) setConfiguring(row);
		window.history.replaceState({}, "", window.location.pathname);
	}, [
		flash.error,
		flash.success,
		connectors.data
	]);
	function startOAuth(connector, preAuth = {}) {
		const query = new URLSearchParams(preAuth).toString();
		window.location.href = `/connectors/oauth/${connector.id}/redirect${query ? `?${query}` : ""}`;
	}
	async function act(connector, action) {
		setBusy(`${connector}:${action}`);
		try {
			const response = await apiSend("POST", `connectors/${connector}/${action}`);
			toast.success(response.data?.message ?? response.message);
			connectors.reload();
			runs.reload();
			if (action === "disconnect") router.reload();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Something went wrong.");
		} finally {
			setBusy(null);
		}
	}
	async function submitTokens() {
		if (!tokenForm) return;
		setBusy(`${tokenForm.id}:connect`);
		try {
			await apiSend("POST", `connectors/${tokenForm.id}/connect`, values);
			toast.success(`${tokenForm.label} connected.`);
			setTokenForm(null);
			setValues({});
			connectors.reload();
			router.reload();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not connect.");
		} finally {
			setBusy(null);
		}
	}
	const rows = tab === "live" ? connectors.data?.live ?? [] : connectors.data?.phase_two ?? [];
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Connectors",
		description: "Where your numbers come from",
		showFilters: false,
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Connectors" }),
			/* @__PURE__ */ jsx(Tabs, {
				value: tab,
				onValueChange: (value) => setTab(value),
				children: /* @__PURE__ */ jsxs(TabsList, { children: [/* @__PURE__ */ jsxs(TabsTrigger, {
					value: "live",
					children: [
						"Available now (",
						connectors.data?.live.length ?? 0,
						")"
					]
				}), /* @__PURE__ */ jsxs(TabsTrigger, {
					value: "phase_two",
					children: [
						"Phase 2 (",
						connectors.data?.phase_two.length ?? 0,
						")"
					]
				})] })
			}),
			tab === "phase_two" && /* @__PURE__ */ jsx("p", {
				className: "rounded-lg bg-warn-soft/50 px-3 py-2 text-xs text-warn",
				children: "These implement the same connector interface but their drivers are not written yet. Connecting one would not pull any data, so it is disabled."
			}),
			/* @__PURE__ */ jsx("div", {
				className: "grid gap-3 sm:grid-cols-2 xl:grid-cols-3",
				children: connectors.loading ? Array.from({ length: 6 }).map((_, index) => /* @__PURE__ */ jsx(Card, { className: "h-48 animate-pulse bg-muted/40" }, index)) : rows.map((connector) => {
					const needsSetup = connector.status === "needs_setup";
					const isConnected = connector.status === "connected";
					return /* @__PURE__ */ jsxs(Card, {
						className: "flex flex-col p-4",
						children: [
							/* @__PURE__ */ jsxs("div", {
								className: "flex items-start justify-between gap-2",
								children: [/* @__PURE__ */ jsxs("div", {
									className: "min-w-0",
									children: [/* @__PURE__ */ jsxs("p", {
										className: "flex items-center gap-1.5 text-sm font-semibold",
										children: [/* @__PURE__ */ jsx("span", { className: cn("size-2 shrink-0 rounded-full", STATUS_DOT[connector.status]) }), /* @__PURE__ */ jsx("span", {
											className: "truncate",
											children: connector.label
										})]
									}), /* @__PURE__ */ jsx("p", {
										className: "mt-0.5 text-[11px] uppercase tracking-wide text-muted-foreground",
										children: connector.auth_label
									})]
								}), connector.is_stub ? /* @__PURE__ */ jsx(Badge, {
									variant: "warn",
									children: "phase 2"
								}) : isConnected ? /* @__PURE__ */ jsx(Badge, {
									variant: "good",
									children: "connected"
								}) : needsSetup ? /* @__PURE__ */ jsx(Badge, {
									variant: "warn",
									children: "needs setup"
								}) : connector.status === "error" ? /* @__PURE__ */ jsx(Badge, {
									variant: "bad",
									children: "error"
								}) : null]
							}),
							/* @__PURE__ */ jsx("p", {
								className: "mt-2 flex-1 text-xs leading-snug text-muted-foreground",
								children: connector.summary
							}),
							/* @__PURE__ */ jsxs("div", {
								className: "mt-2 flex flex-wrap gap-1",
								children: [connector.entities.slice(0, 4).map((entity) => /* @__PURE__ */ jsx("span", {
									className: "rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground",
									children: entity.replace(/_/g, " ")
								}, entity)), connector.entities.length > 4 && /* @__PURE__ */ jsxs("span", {
									className: "px-1 py-0.5 text-[10px] text-muted-foreground",
									children: ["+", connector.entities.length - 4]
								})]
							}),
							needsSetup && /* @__PURE__ */ jsxs("p", {
								className: "mt-2 text-[11px] text-warn",
								children: [
									"Authorised, but still needs: ",
									Object.values(connector.pending_selections).join(", "),
									"."
								]
							}),
							connector.last_error && /* @__PURE__ */ jsxs("p", {
								className: "mt-2 flex items-start gap-1 text-[11px] text-bad",
								children: [/* @__PURE__ */ jsx(TriangleAlert, { className: "mt-px size-3 shrink-0" }), /* @__PURE__ */ jsx("span", {
									className: "line-clamp-2",
									children: connector.last_error
								})]
							}),
							isConnected && /* @__PURE__ */ jsxs("p", {
								className: "mt-2 flex items-center gap-1 text-[11px] text-muted-foreground",
								children: [
									/* @__PURE__ */ jsx(CheckCircle2, { className: "size-3 text-good" }),
									connector.account_label,
									" · synced ",
									connector.last_synced_human ?? "never"
								]
							}),
							connector.is_oauth && !connector.oauth_configured && !connector.is_stub && /* @__PURE__ */ jsx("p", {
								className: "mt-2 text-[11px] text-muted-foreground",
								children: "Not configured on this server — an admin needs to add its OAuth client id and secret."
							}),
							/* @__PURE__ */ jsx("div", {
								className: "mt-3 flex flex-wrap gap-1.5",
								children: isConnected || needsSetup ? /* @__PURE__ */ jsxs(Fragment, { children: [
									needsSetup && /* @__PURE__ */ jsxs(Button, {
										size: "xs",
										onClick: () => setConfiguring(connector),
										disabled: !can("connectors.credentials.manage"),
										children: [/* @__PURE__ */ jsx(Settings2, {}), "Finish setup"]
									}),
									isConnected && /* @__PURE__ */ jsxs(Fragment, { children: [
										/* @__PURE__ */ jsxs(Button, {
											size: "xs",
											variant: "outline",
											disabled: !can("connectors.sync_health.manage") || busy !== null,
											onClick: () => act(connector.id, "sync"),
											children: [busy === `${connector.id}:sync` ? /* @__PURE__ */ jsx(Loader2, { className: "animate-spin" }) : /* @__PURE__ */ jsx(RefreshCw, {}), "Sync"]
										}),
										/* @__PURE__ */ jsx(Button, {
											size: "xs",
											variant: "ghost",
											disabled: busy !== null,
											onClick: () => act(connector.id, "test"),
											children: "Test"
										}),
										Object.keys(connector.pending_selections).length === 0 && Object.keys(connector.selected).length > 0 && /* @__PURE__ */ jsxs(Button, {
											size: "xs",
											variant: "ghost",
											onClick: () => setConfiguring(connector),
											children: [/* @__PURE__ */ jsx(Settings2, {}), "Accounts"]
										})
									] }),
									/* @__PURE__ */ jsx(Button, {
										size: "xs",
										variant: "ghost",
										className: "text-bad",
										disabled: !can("connectors.credentials.manage") || busy !== null,
										onClick: () => act(connector.id, "disconnect"),
										children: "Disconnect"
									})
								] }) : connector.is_oauth ? /* @__PURE__ */ jsxs(Button, {
									size: "xs",
									disabled: connector.is_stub || !connector.oauth_configured || !can("connectors.credentials.manage"),
									onClick: () => Object.keys(connector.pre_auth_fields).length > 0 ? (setAuthorizing(connector), setValues({})) : startOAuth(connector),
									children: [
										/* @__PURE__ */ jsx(ExternalLink, {}),
										"Connect with ",
										connector.label.split(" ")[0]
									]
								}) : /* @__PURE__ */ jsxs(Button, {
									size: "xs",
									disabled: connector.is_stub || !can("connectors.credentials.manage"),
									onClick: () => {
										setTokenForm(connector);
										setValues({});
									},
									children: [/* @__PURE__ */ jsx(PlugZap, {}), connector.is_stub ? "Not available yet" : "Connect"]
								})
							})
						]
					}, connector.id);
				})
			}),
			/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "connectors.sync_health.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Sync history",
					subtitle: "Every run, successful or not",
					widgetKey: "connectors.sync_health",
					loading: runs.loading,
					error: runs.error,
					onRetry: runs.reload,
					empty: (runs.data?.rows.length ?? 0) === 0,
					emptyState: /* @__PURE__ */ jsx("p", {
						className: "py-8 text-center text-xs text-muted-foreground",
						children: "Nothing has synced yet — connect a source above."
					}),
					children: /* @__PURE__ */ jsx(DataTable, {
						rows: runs.data?.rows ?? [],
						rowKey: (row) => row.id,
						columns: [
							{
								key: "connector",
								header: "Connector",
								value: (r) => r.connector_id,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium",
									children: r.connector_id
								})
							},
							{
								key: "entity",
								header: "Entity",
								value: (r) => r.entity,
								render: (r) => r.entity.replace(/_/g, " ")
							},
							{
								key: "trigger",
								header: "Trigger",
								value: (r) => r.trigger,
								render: (r) => r.trigger
							},
							{
								key: "status",
								header: "Status",
								value: (r) => r.status,
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: r.status === "success" ? "good" : r.status === "failed" ? "bad" : "muted",
									children: r.status
								})
							},
							{
								key: "records",
								header: "Records",
								align: "right",
								sortable: true,
								value: (r) => r.records_upserted,
								render: (r) => formatNumber(r.records_upserted)
							},
							{
								key: "duration",
								header: "Duration",
								align: "right",
								sortable: true,
								value: (r) => r.duration_ms,
								render: (r) => `${formatNumber(r.duration_ms)}ms`
							},
							{
								key: "error",
								header: "Error",
								value: (r) => r.error,
								render: (r) => r.error ? /* @__PURE__ */ jsx("span", {
									className: "line-clamp-1 text-bad",
									children: r.error
								}) : /* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: "—"
								})
							}
						]
					})
				})
			}),
			/* @__PURE__ */ jsx(Sheet, {
				open: authorizing !== null,
				onOpenChange: (open) => !open && setAuthorizing(null),
				children: /* @__PURE__ */ jsxs(SheetContent, {
					side: "right",
					className: "sm:max-w-md",
					children: [
						/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, { children: ["Connect ", authorizing?.label] }), /* @__PURE__ */ jsxs(SheetDescription, { children: [
							"You’ll be sent to ",
							authorizing?.label,
							" to approve access."
						] })] }),
						/* @__PURE__ */ jsxs("div", {
							className: "flex-1 space-y-4 overflow-auto p-5",
							children: [Object.entries(authorizing?.pre_auth_fields ?? {}).map(([name, field]) => /* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [
									/* @__PURE__ */ jsxs(Label, {
										htmlFor: `pre-${name}`,
										children: [field.label, field.required && /* @__PURE__ */ jsx("span", {
											className: "ml-0.5 text-bad",
											children: "*"
										})]
									}),
									/* @__PURE__ */ jsx(Input, {
										id: `pre-${name}`,
										value: values[name] ?? "",
										onChange: (event) => setValues((current) => ({
											...current,
											[name]: event.target.value
										})),
										placeholder: field.help
									}),
									field.help && /* @__PURE__ */ jsx("p", {
										className: "text-[11px] text-muted-foreground",
										children: field.help
									})
								]
							}, name)), /* @__PURE__ */ jsxs("p", {
								className: "rounded-lg bg-muted px-3 py-2 text-[11px] text-muted-foreground",
								children: [
									"We never see your password. ",
									authorizing?.label,
									" issues a token scoped to the data listed on this card."
								]
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "flex justify-end gap-2 border-t border-border px-5 py-3",
							children: [/* @__PURE__ */ jsx(Button, {
								variant: "outline",
								size: "sm",
								onClick: () => setAuthorizing(null),
								children: "Cancel"
							}), /* @__PURE__ */ jsxs(Button, {
								size: "sm",
								disabled: Object.entries(authorizing?.pre_auth_fields ?? {}).some(([name, field]) => field.required && !values[name]),
								onClick: () => authorizing && startOAuth(authorizing, values),
								children: [/* @__PURE__ */ jsx(ExternalLink, {}), "Continue"]
							})]
						})
					]
				})
			}),
			/* @__PURE__ */ jsx(SelectionSheet, {
				connector: configuring,
				onClose: () => setConfiguring(null),
				onSaved: () => {
					connectors.reload();
					router.reload();
				}
			}),
			/* @__PURE__ */ jsx(Sheet, {
				open: tokenForm !== null,
				onOpenChange: (open) => !open && setTokenForm(null),
				children: /* @__PURE__ */ jsxs(SheetContent, {
					side: "right",
					className: "sm:max-w-md",
					children: [
						/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, { children: ["Connect ", tokenForm?.label] }), /* @__PURE__ */ jsx(SheetDescription, { children: tokenForm?.summary })] }),
						/* @__PURE__ */ jsxs("div", {
							className: "flex-1 space-y-4 overflow-auto p-5",
							children: [Object.entries(tokenForm?.fields ?? {}).map(([name, field]) => /* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [
									/* @__PURE__ */ jsxs(Label, {
										htmlFor: name,
										children: [field.label, field.required && /* @__PURE__ */ jsx("span", {
											className: "ml-0.5 text-bad",
											children: "*"
										})]
									}),
									/* @__PURE__ */ jsx(Input, {
										id: name,
										type: field.type === "password" ? "password" : "text",
										value: values[name] ?? "",
										onChange: (event) => setValues((current) => ({
											...current,
											[name]: event.target.value
										}))
									}),
									field.help && /* @__PURE__ */ jsx("p", {
										className: "text-[11px] text-muted-foreground",
										children: field.help
									})
								]
							}, name)), /* @__PURE__ */ jsx("p", {
								className: "rounded-lg bg-muted px-3 py-2 text-[11px] text-muted-foreground",
								children: "Credentials are encrypted at rest and never written to logs."
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "flex justify-end gap-2 border-t border-border px-5 py-3",
							children: [/* @__PURE__ */ jsx(Button, {
								variant: "outline",
								size: "sm",
								onClick: () => setTokenForm(null),
								children: "Cancel"
							}), /* @__PURE__ */ jsxs(Button, {
								size: "sm",
								onClick: submitTokens,
								disabled: busy !== null,
								children: [busy?.endsWith(":connect") && /* @__PURE__ */ jsx(Loader2, { className: "animate-spin" }), "Connect"]
							})]
						})
					]
				})
			})
		]
	});
}
/**
* The step after authorisation: pick which ad account, property or page this
* tenant actually is. Options are read live from the provider.
*/
function SelectionSheet({ connector, onClose, onSaved }) {
	const [options, setOptions] = useState({});
	const [chosen, setChosen] = useState({});
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [errors, setErrors] = useState({});
	const selections = { ...connector?.pending_selections ?? {} };
	Object.keys(connector?.selected ?? {}).forEach((key) => {
		if (!selections[key]) selections[key] = key.replace(/_/g, " ");
	});
	useEffect(() => {
		if (!connector) return;
		setChosen(connector.selected ?? {});
		setErrors({});
		setLoading(true);
		Promise.all(Object.keys(selections).map(async (key) => {
			try {
				return [key, (await apiGet(`connectors/${connector.id}/resources/${key}`)).data.options];
			} catch (error) {
				setErrors((current) => ({
					...current,
					[key]: error instanceof Error ? error.message : "Could not load options."
				}));
				return [key, []];
			}
		})).then((entries) => setOptions(Object.fromEntries(entries))).finally(() => setLoading(false));
	}, [connector?.id]);
	async function save() {
		if (!connector) return;
		setSaving(true);
		try {
			const response = await apiSend("POST", `connectors/${connector.id}/select`, chosen);
			toast.success(response.message);
			onSaved();
			if (response.data.status === "connected") onClose();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save.");
		} finally {
			setSaving(false);
		}
	}
	return /* @__PURE__ */ jsx(Sheet, {
		open: connector !== null,
		onOpenChange: (open) => !open && onClose(),
		children: /* @__PURE__ */ jsxs(SheetContent, {
			side: "right",
			className: "sm:max-w-md",
			children: [
				/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, { children: ["Set up ", connector?.label] }), /* @__PURE__ */ jsx(SheetDescription, { children: "Your account is authorised. Choose which accounts this brand’s numbers should come from." })] }),
				/* @__PURE__ */ jsxs("div", {
					className: "flex-1 space-y-4 overflow-auto p-5",
					children: [loading && /* @__PURE__ */ jsxs("p", {
						className: "flex items-center gap-2 text-xs text-muted-foreground",
						children: [/* @__PURE__ */ jsx(Loader2, { className: "size-3.5 animate-spin" }), "Reading available accounts…"]
					}), Object.entries(selections).map(([key, label]) => /* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [/* @__PURE__ */ jsx(Label, { children: label }), errors[key] ? /* @__PURE__ */ jsx("p", {
							className: "text-[11px] text-bad",
							children: errors[key]
						}) : (options[key] ?? []).length === 0 && !loading ? /* @__PURE__ */ jsxs("p", {
							className: "text-[11px] text-muted-foreground",
							children: [
								"Nothing available — the authorising account may not have access to any ",
								label.toLowerCase(),
								"."
							]
						}) : /* @__PURE__ */ jsxs(Select, {
							value: chosen[key] ?? "",
							onValueChange: (value) => setChosen((c) => ({
								...c,
								[key]: value
							})),
							children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, { placeholder: `Choose a ${label.toLowerCase()}…` }) }), /* @__PURE__ */ jsx(SelectContent, { children: (options[key] ?? []).map((option) => /* @__PURE__ */ jsxs(SelectItem, {
								value: option.id,
								children: [option.label, option.meta && /* @__PURE__ */ jsx("span", {
									className: "ml-1.5 text-muted-foreground",
									children: option.meta
								})]
							}, option.id)) })]
						})]
					}, key))]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "flex justify-end gap-2 border-t border-border px-5 py-3",
					children: [/* @__PURE__ */ jsx(Button, {
						variant: "outline",
						size: "sm",
						onClick: onClose,
						children: "Close"
					}), /* @__PURE__ */ jsxs(Button, {
						size: "sm",
						onClick: save,
						disabled: saving || Object.keys(chosen).length === 0,
						children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "animate-spin" }), "Save"]
					})]
				})
			]
		})
	});
}
//#endregion
export { Connectors as default };

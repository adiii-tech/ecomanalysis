import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, o as SelectItem, s as SelectTrigger, t as AppLayout } from "./app-layout-Drf1OqZl.js";
import { a as apiSend, i as apiGet, s as usePermissions } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, f as formatNumber, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { t as PermissionGuard } from "./permission-guard-B2YFsnLX.js";
import { t as DataTable } from "./data-table-CQdRzZJF.js";
import { a as SheetTitle, i as SheetHeader, n as SheetContent, r as SheetDescription, t as Sheet } from "./sheet-BNOmNaqW.js";
import { t as Switch } from "./switch-CfwX-tOM.js";
import { n as TabsList, r as TabsTrigger, t as Tabs } from "./tabs-CadgG3dj.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useEffect, useMemo, useState } from "react";
import { Copy, KeyRound, Mail, RotateCcw, Search, ShieldCheck, UserPlus } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/admin/users.tsx
function AdminUsers() {
	const { can } = usePermissions();
	const [tab, setTab] = useState("users");
	const [users, setUsers] = useState([]);
	const [invites, setInvites] = useState([]);
	const [roles, setRoles] = useState([]);
	const [seats, setSeats] = useState({
		used: 0,
		limit: 0
	});
	const [audit, setAudit] = useState([]);
	const [roleRows, setRoleRows] = useState([]);
	const [settings, setSettings] = useState(null);
	const [editing, setEditing] = useState(null);
	const [permissions, setPermissions] = useState(null);
	const [inviting, setInviting] = useState(false);
	const [inviteForm, setInviteForm] = useState({
		email: "",
		role: "ANALYST"
	});
	const [search, setSearch] = useState("");
	useEffect(() => {
		load();
	}, [tab]);
	async function load() {
		try {
			if (tab === "users") {
				const response = await apiGet("admin/users");
				setUsers(response.data.users);
				setInvites(response.data.invitations);
				setRoles(response.data.roles);
				setSeats({
					used: response.data.seats_used,
					limit: response.data.seat_limit
				});
			}
			if (tab === "audit") setAudit((await apiGet("admin/audit")).data.rows);
			if (tab === "roles") setRoleRows((await apiGet("admin/roles")).data.rows);
			if (tab === "settings") setSettings((await apiGet("admin/settings")).data);
		} catch {}
	}
	async function openPermissions(user) {
		setEditing(user);
		setPermissions(null);
		const response = await apiGet(`admin/users/${user.id}/permissions`);
		setPermissions(response.data.modules);
	}
	function toggle(permission, granted) {
		setPermissions((current) => current?.map((module) => ({
			...module,
			permissions: module.permissions.map((row) => row.permission === permission ? {
				...row,
				granted_directly: granted,
				effective: granted || row.from_role
			} : row)
		})) ?? null);
	}
	async function savePermissions() {
		if (!editing || !permissions) return;
		const grant = permissions.flatMap((m) => m.permissions.filter((p) => p.granted_directly).map((p) => p.permission));
		try {
			await apiSend("PUT", `admin/users/${editing.id}/permissions`, { grant });
			toast.success("Permissions saved.");
			setEditing(null);
			load();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save.");
		}
	}
	const filtered = useMemo(() => users.filter((u) => `${u.name} ${u.email} ${u.role}`.toLowerCase().includes(search.toLowerCase())), [users, search]);
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Admin",
		description: "Users, roles, audit and settings",
		showFilters: false,
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Admin" }),
			/* @__PURE__ */ jsxs("div", {
				className: "flex flex-wrap items-center justify-between gap-2",
				children: [/* @__PURE__ */ jsx(Tabs, {
					value: tab,
					onValueChange: (value) => setTab(value),
					children: /* @__PURE__ */ jsxs(TabsList, { children: [
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "users",
							children: "Users"
						}),
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "roles",
							children: "Roles"
						}),
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "audit",
							children: "Audit"
						}),
						/* @__PURE__ */ jsx(TabsTrigger, {
							value: "settings",
							children: "Settings"
						})
					] })
				}), tab === "users" && can("admin.users.manage") && /* @__PURE__ */ jsxs(Button, {
					size: "sm",
					onClick: () => setInviting(true),
					className: "gap-1.5",
					children: [/* @__PURE__ */ jsx(UserPlus, { className: "size-3.5" }), "Invite user"]
				})]
			}),
			tab === "users" && /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx(PermissionGuard, {
				permission: "admin.users.view",
				children: /* @__PURE__ */ jsxs(ChartCard, {
					title: "Users",
					subtitle: `${seats.used} of ${seats.limit} seats used`,
					widgetKey: "admin.users",
					exportDataset: void 0,
					children: [/* @__PURE__ */ jsxs("div", {
						className: "relative mb-2 max-w-xs",
						children: [/* @__PURE__ */ jsx(Search, { className: "pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" }), /* @__PURE__ */ jsx(Input, {
							value: search,
							onChange: (e) => setSearch(e.target.value),
							placeholder: "Search users…",
							className: "h-8 pl-8 text-xs"
						})]
					}), /* @__PURE__ */ jsx(DataTable, {
						rows: filtered,
						rowKey: (row) => row.id,
						columns: [
							{
								key: "name",
								header: "User",
								value: (r) => r.name,
								render: (r) => /* @__PURE__ */ jsxs("div", {
									className: "min-w-0",
									children: [/* @__PURE__ */ jsxs("p", {
										className: "flex items-center gap-1.5 truncate font-medium",
										children: [r.name, r.mfa_enabled && /* @__PURE__ */ jsx(ShieldCheck, { className: "size-3 text-good" })]
									}), /* @__PURE__ */ jsx("p", {
										className: "truncate text-[11px] text-muted-foreground",
										children: r.email
									})]
								})
							},
							{
								key: "role",
								header: "Role",
								value: (r) => r.role,
								render: (r) => /* @__PURE__ */ jsxs("span", {
									className: "flex items-center gap-1.5",
									children: [/* @__PURE__ */ jsx(Badge, {
										variant: "secondary",
										children: r.role ?? "—"
									}), r.has_overrides && /* @__PURE__ */ jsx(Badge, {
										variant: "warn",
										children: "overrides"
									})]
								})
							},
							{
								key: "status",
								header: "Status",
								value: (r) => r.is_active ? "active" : "disabled",
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: r.is_active ? "good" : "muted",
									children: r.is_active ? "Active" : "Disabled"
								})
							},
							{
								key: "last",
								header: "Last sign-in",
								value: (r) => r.last_login_at,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: r.last_login_at ? formatDateTime(r.last_login_at) : "never"
								})
							},
							{
								key: "actions",
								header: "",
								align: "right",
								render: (r) => can("admin.users.manage") && /* @__PURE__ */ jsxs("div", {
									className: "flex justify-end gap-1",
									children: [
										/* @__PURE__ */ jsx(Button, {
											size: "xs",
											variant: "ghost",
											onClick: () => openPermissions(r),
											children: "Permissions"
										}),
										/* @__PURE__ */ jsx(Button, {
											size: "xs",
											variant: "ghost",
											onClick: async () => {
												const response = await apiSend("POST", `admin/users/${r.id}/password`);
												await navigator.clipboard.writeText(response.data.temporary_password).catch(() => void 0);
												toast.success("Temporary password copied. They must change it at next sign-in.");
											},
											children: /* @__PURE__ */ jsx(KeyRound, { className: "size-3" })
										}),
										/* @__PURE__ */ jsx(Switch, {
											checked: r.is_active,
											onCheckedChange: async (checked) => {
												try {
													await apiSend("PUT", `admin/users/${r.id}`, { is_active: checked });
													load();
												} catch (error) {
													toast.error(error instanceof Error ? error.message : "Could not update.");
												}
											}
										})
									]
								})
							}
						]
					})]
				})
			}), invites.length > 0 && /* @__PURE__ */ jsx(ChartCard, {
				title: "Pending invitations",
				subtitle: "Not yet accepted",
				children: /* @__PURE__ */ jsx(DataTable, {
					dense: true,
					rows: invites,
					rowKey: (row) => row.id,
					columns: [
						{
							key: "email",
							header: "Email",
							value: (r) => r.email,
							render: (r) => /* @__PURE__ */ jsx("span", {
								className: "font-medium",
								children: r.email
							})
						},
						{
							key: "role",
							header: "Role",
							value: (r) => r.role,
							render: (r) => /* @__PURE__ */ jsx(Badge, {
								variant: "secondary",
								children: r.role
							})
						},
						{
							key: "expires",
							header: "Expires",
							value: (r) => r.expires_at,
							render: (r) => /* @__PURE__ */ jsx("span", {
								className: r.expired ? "text-bad" : "text-muted-foreground",
								children: r.expired ? "Expired" : formatDateTime(r.expires_at)
							})
						},
						{
							key: "actions",
							header: "",
							align: "right",
							render: (r) => /* @__PURE__ */ jsxs("div", {
								className: "flex justify-end gap-1",
								children: [/* @__PURE__ */ jsx(Button, {
									size: "xs",
									variant: "ghost",
									onClick: async () => {
										const response = await apiSend("POST", `admin/invitations/${r.id}/resend`);
										await navigator.clipboard.writeText(response.data.accept_url).catch(() => void 0);
										toast.success("Invite link refreshed and copied.");
										load();
									},
									children: /* @__PURE__ */ jsx(Copy, { className: "size-3" })
								}), /* @__PURE__ */ jsx(Button, {
									size: "xs",
									variant: "ghost",
									className: "text-bad",
									onClick: async () => {
										await apiSend("DELETE", `admin/invitations/${r.id}`);
										load();
									},
									children: "Revoke"
								})]
							})
						}
					]
				})
			})] }),
			tab === "roles" && /* @__PURE__ */ jsx(PermissionGuard, {
				permission: "admin.roles.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Roles",
					subtitle: "Built-in roles plus anything you have added",
					children: /* @__PURE__ */ jsx("div", {
						className: "grid gap-2 sm:grid-cols-2",
						children: roleRows.map((role) => /* @__PURE__ */ jsxs(Card, {
							className: "p-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "flex items-start justify-between gap-2",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-sm font-semibold",
										children: role.name
									}), role.is_builtin && /* @__PURE__ */ jsx(Badge, {
										variant: "muted",
										children: "built-in"
									})]
								}),
								/* @__PURE__ */ jsx("p", {
									className: "mt-1 text-xs leading-snug text-muted-foreground",
									children: role.description
								}),
								/* @__PURE__ */ jsxs("p", {
									className: "mt-2 text-[11px] text-muted-foreground",
									children: [
										formatNumber(role.permission_count),
										" permissions · ",
										formatNumber(role.users_count),
										" user",
										role.users_count === 1 ? "" : "s"
									]
								})
							]
						}, role.name))
					})
				})
			}),
			tab === "audit" && /* @__PURE__ */ jsx(PermissionGuard, {
				permission: "admin.audit.view",
				children: /* @__PURE__ */ jsx(ChartCard, {
					title: "Audit log",
					subtitle: "Every mutation and export",
					children: /* @__PURE__ */ jsx(DataTable, {
						searchable: true,
						rows: audit,
						rowKey: (row) => row.id,
						columns: [
							{
								key: "when",
								header: "When",
								value: (r) => r.created_at,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "text-muted-foreground",
									children: formatDateTime(r.created_at)
								})
							},
							{
								key: "log",
								header: "Area",
								value: (r) => r.log,
								render: (r) => /* @__PURE__ */ jsx(Badge, {
									variant: "muted",
									children: r.log
								})
							},
							{
								key: "action",
								header: "Action",
								value: (r) => r.description,
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "font-medium",
									children: r.description
								})
							},
							{
								key: "causer",
								header: "By",
								value: (r) => r.causer,
								render: (r) => r.causer
							},
							{
								key: "props",
								header: "Details",
								value: (r) => JSON.stringify(r.properties),
								render: (r) => /* @__PURE__ */ jsx("span", {
									className: "line-clamp-1 text-[11px] text-muted-foreground",
									children: Object.entries(r.properties ?? {}).map(([k, v]) => `${k}=${typeof v === "object" ? JSON.stringify(v) : v}`).join(" · ")
								})
							}
						]
					})
				})
			}),
			tab === "settings" && settings && /* @__PURE__ */ jsx(PermissionGuard, {
				permission: "admin.settings.view",
				children: /* @__PURE__ */ jsx(SettingsEditor, {
					settings,
					onSaved: load,
					canManage: can("admin.settings.manage")
				})
			}),
			/* @__PURE__ */ jsx(Sheet, {
				open: inviting,
				onOpenChange: setInviting,
				children: /* @__PURE__ */ jsxs(SheetContent, {
					side: "right",
					className: "sm:max-w-md",
					children: [
						/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsx(SheetTitle, { children: "Invite a user" }), /* @__PURE__ */ jsx(SheetDescription, { children: "They pick their own password when they accept." })] }),
						/* @__PURE__ */ jsxs("div", {
							className: "flex-1 space-y-4 p-5",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "invite-email",
									children: "Email"
								}), /* @__PURE__ */ jsx(Input, {
									id: "invite-email",
									type: "email",
									value: inviteForm.email,
									onChange: (e) => setInviteForm((f) => ({
										...f,
										email: e.target.value
									}))
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsx(Label, { children: "Role" }), /* @__PURE__ */ jsxs(Select, {
									value: inviteForm.role,
									onValueChange: (value) => setInviteForm((f) => ({
										...f,
										role: value
									})),
									children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: roles.map((role) => /* @__PURE__ */ jsx(SelectItem, {
										value: role,
										children: role
									}, role)) })]
								})]
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "flex justify-end gap-2 border-t border-border px-5 py-3",
							children: [/* @__PURE__ */ jsx(Button, {
								variant: "outline",
								size: "sm",
								onClick: () => setInviting(false),
								children: "Cancel"
							}), /* @__PURE__ */ jsxs(Button, {
								size: "sm",
								onClick: async () => {
									try {
										const response = await apiSend("POST", "admin/users/invite", inviteForm);
										await navigator.clipboard.writeText(response.data.accept_url).catch(() => void 0);
										toast.success("Invite created and link copied.");
										setInviting(false);
										setInviteForm({
											email: "",
											role: "ANALYST"
										});
										load();
									} catch (error) {
										toast.error(error instanceof Error ? error.message : "Could not invite.");
									}
								},
								children: [/* @__PURE__ */ jsx(Mail, { className: "size-3.5" }), "Create invite"]
							})]
						})
					]
				})
			}),
			/* @__PURE__ */ jsx(Sheet, {
				open: editing !== null,
				onOpenChange: (open) => !open && setEditing(null),
				children: /* @__PURE__ */ jsxs(SheetContent, {
					side: "right",
					className: "sm:max-w-2xl",
					children: [
						/* @__PURE__ */ jsxs(SheetHeader, { children: [/* @__PURE__ */ jsxs(SheetTitle, { children: ["Permissions · ", editing?.name] }), /* @__PURE__ */ jsxs(SheetDescription, { children: [
							"Their ",
							editing?.role,
							" role grants the checked-and-locked items. Anything you tick here is an override on top."
						] })] }),
						/* @__PURE__ */ jsxs("div", {
							className: "flex-1 space-y-4 overflow-auto p-5 scrollbar-thin",
							children: [permissions === null && /* @__PURE__ */ jsx("p", {
								className: "text-xs text-muted-foreground",
								children: "Loading…"
							}), permissions?.map((module) => /* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsxs("p", {
									className: "flex items-baseline justify-between text-xs font-semibold",
									children: [module.label, /* @__PURE__ */ jsxs("span", {
										className: "text-[11px] font-normal text-muted-foreground",
										children: [
											module.granted,
											"/",
											module.total
										]
									})]
								}), /* @__PURE__ */ jsx("div", {
									className: "grid gap-1 sm:grid-cols-2",
									children: module.permissions.map((row) => /* @__PURE__ */ jsxs("label", {
										className: cn("flex items-center gap-2 rounded px-1.5 py-1 text-[11px]", row.from_role && "opacity-60"),
										children: [/* @__PURE__ */ jsx("input", {
											type: "checkbox",
											checked: row.effective,
											disabled: row.from_role,
											onChange: (e) => toggle(row.permission, e.target.checked),
											className: "size-3 rounded border-input"
										}), /* @__PURE__ */ jsxs("span", {
											className: "truncate",
											children: [
												row.widget.replace(/_/g, " "),
												" · ",
												row.action
											]
										})]
									}, row.permission))
								})]
							}, module.module))]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "flex justify-between gap-2 border-t border-border px-5 py-3",
							children: [/* @__PURE__ */ jsxs(Button, {
								variant: "outline",
								size: "sm",
								className: "gap-1.5",
								onClick: async () => {
									if (!editing) return;
									await apiSend("POST", `admin/users/${editing.id}/permissions/reset`);
									toast.success("Reset to role defaults.");
									setEditing(null);
									load();
								},
								children: [/* @__PURE__ */ jsx(RotateCcw, { className: "size-3.5" }), "Reset to role"]
							}), /* @__PURE__ */ jsxs("div", {
								className: "flex gap-2",
								children: [/* @__PURE__ */ jsx(Button, {
									variant: "ghost",
									size: "sm",
									onClick: () => setEditing(null),
									children: "Cancel"
								}), /* @__PURE__ */ jsx(Button, {
									size: "sm",
									onClick: savePermissions,
									children: "Save"
								})]
							})]
						})
					]
				})
			})
		]
	});
}
/** Cost settings and benchmarks — the numbers every margin figure depends on. */
function SettingsEditor({ settings, onSaved, canManage }) {
	const [costs, setCosts] = useState(settings.cost_settings);
	const [benchmarks, setBenchmarks] = useState(settings.benchmarks);
	const [profile, setProfile] = useState(settings.tenant_profile ?? {});
	const [notifications, setNotifications] = useState(settings.notifications ?? {});
	const [whatsappToken, setWhatsappToken] = useState("");
	const [saving, setSaving] = useState(false);
	const list = (key) => (notifications[key] ?? []).join(", ");
	const setList = (key, value) => setNotifications((current) => ({
		...current,
		[key]: value.split(/[,\s]+/).map((item) => item.trim()).filter(Boolean)
	}));
	const save = async () => {
		setSaving(true);
		try {
			const response = await apiSend("PUT", "admin/settings", {
				cost_settings: costs,
				benchmarks,
				tenant_profile: profile,
				notifications: whatsappToken ? {
					...notifications,
					whatsapp_token: whatsappToken
				} : notifications
			});
			toast.success(response.message);
			setWhatsappToken("");
			onSaved();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not save.");
		} finally {
			setSaving(false);
		}
	};
	return /* @__PURE__ */ jsxs("div", {
		className: "grid gap-4 xl:grid-cols-2",
		children: [
			/* @__PURE__ */ jsx(ChartCard, {
				title: "Cost settings",
				subtitle: "These drive every margin number in the product",
				widgetKey: "admin.cost_settings",
				children: /* @__PURE__ */ jsx("div", {
					className: "grid gap-2 sm:grid-cols-2",
					children: Object.entries({
						packaging_cost: "Packaging per order (₹)",
						per_order_fixed_cost: "Fixed handling per order (₹)",
						cod_charge: "COD collection charge (₹)",
						return_handling_cost: "Return handling (₹)",
						rto_handling_cost: "RTO handling (₹)",
						default_shipping_cost: "Default shipping cost (₹)",
						monthly_fixed_opex: "Monthly fixed opex (₹)",
						gateway_fee_pct: "Payment gateway fee (%)"
					}).map(([key, label]) => /* @__PURE__ */ jsxs("div", {
						className: "space-y-1",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: key,
							children: label
						}), /* @__PURE__ */ jsx(Input, {
							id: key,
							type: "number",
							step: "0.01",
							disabled: !canManage,
							value: costs[key] ?? 0,
							onChange: (e) => setCosts((c) => ({
								...c,
								[key]: Number(e.target.value)
							}))
						})]
					}, key))
				})
			}),
			/* @__PURE__ */ jsx(ChartCard, {
				title: "Benchmarks",
				subtitle: "What the verdicts judge against",
				widgetKey: "admin.benchmarks",
				children: /* @__PURE__ */ jsx("div", {
					className: "grid gap-2 sm:grid-cols-2",
					children: Object.entries({
						target_roas: "Target ROAS (×)",
						target_margin_pct: "Target margin (%)",
						target_repeat_rate: "Target repeat rate (%)",
						dispatch_sla_days: "Dispatch SLA (days)",
						delivery_sla_days: "Delivery SLA (days)",
						rto_threshold_pct: "RTO alert threshold (%)",
						return_threshold_pct: "Return alert threshold (%)",
						days_of_cover_threshold: "Low stock threshold (days)",
						monthly_revenue_target: "Monthly revenue target (₹)"
					}).map(([key, label]) => /* @__PURE__ */ jsxs("div", {
						className: "space-y-1",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: key,
							children: label
						}), /* @__PURE__ */ jsx(Input, {
							id: key,
							type: "number",
							step: "0.1",
							disabled: !canManage,
							value: benchmarks[key] ?? 0,
							onChange: (e) => setBenchmarks((b) => ({
								...b,
								[key]: Number(e.target.value)
							}))
						})]
					}, key))
				})
			}),
			/* @__PURE__ */ jsx(ChartCard, {
				title: "Tax identity",
				subtitle: "Needed to split intra-state supply from inter-state on the GST report",
				widgetKey: "admin.cost_settings",
				children: /* @__PURE__ */ jsxs("div", {
					className: "grid gap-2 sm:grid-cols-2",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "space-y-1",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "gst_state",
							children: "Your GST state"
						}), /* @__PURE__ */ jsx(Input, {
							id: "gst_state",
							placeholder: "Maharashtra",
							disabled: !canManage,
							value: profile.gst_state ?? "",
							onChange: (e) => setProfile((current) => ({
								...current,
								gst_state: e.target.value
							}))
						})]
					}), /* @__PURE__ */ jsxs("div", {
						className: "space-y-1",
						children: [/* @__PURE__ */ jsx(Label, {
							htmlFor: "gstin",
							children: "GSTIN"
						}), /* @__PURE__ */ jsx(Input, {
							id: "gstin",
							placeholder: "27AAAAA0000A1Z5",
							disabled: !canManage,
							value: profile.gstin ?? "",
							onChange: (e) => setProfile((current) => ({
								...current,
								gstin: e.target.value
							}))
						})]
					})]
				})
			}),
			/* @__PURE__ */ jsx(ChartCard, {
				title: "Alert delivery",
				subtitle: "Where a firing alert actually goes",
				widgetKey: "admin.cost_settings",
				children: /* @__PURE__ */ jsxs("div", {
					className: "space-y-3",
					children: [
						/* @__PURE__ */ jsxs("div", {
							className: "space-y-1",
							children: [
								/* @__PURE__ */ jsx(Label, {
									htmlFor: "alert_emails",
									children: "Email recipients"
								}),
								/* @__PURE__ */ jsx(Input, {
									id: "alert_emails",
									placeholder: "founder@brand.com, ops@brand.com",
									disabled: !canManage,
									value: list("email_recipients"),
									onChange: (e) => setList("email_recipients", e.target.value)
								}),
								/* @__PURE__ */ jsx("p", {
									className: "text-[11px] text-muted-foreground",
									children: "Leave empty to email every active user."
								})
							]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "space-y-1",
							children: [/* @__PURE__ */ jsx(Label, {
								htmlFor: "slack_webhook",
								children: "Slack incoming webhook"
							}), /* @__PURE__ */ jsx(Input, {
								id: "slack_webhook",
								placeholder: "https://hooks.slack.com/services/…",
								disabled: !canManage,
								value: notifications.slack_webhook_url ?? "",
								onChange: (e) => setNotifications((c) => ({
									...c,
									slack_webhook_url: e.target.value
								}))
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "grid gap-2 sm:grid-cols-2",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "wa_phone",
										children: "WhatsApp phone number ID"
									}), /* @__PURE__ */ jsx(Input, {
										id: "wa_phone",
										disabled: !canManage,
										value: notifications.whatsapp_phone_number_id ?? "",
										onChange: (e) => setNotifications((c) => ({
											...c,
											whatsapp_phone_number_id: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsxs(Label, {
										htmlFor: "wa_token",
										children: ["WhatsApp token ", notifications.whatsapp_token_set ? "(stored)" : ""]
									}), /* @__PURE__ */ jsx(Input, {
										id: "wa_token",
										type: "password",
										placeholder: notifications.whatsapp_token_set ? "Leave blank to keep" : "Cloud API token",
										disabled: !canManage,
										value: whatsappToken,
										onChange: (e) => setWhatsappToken(e.target.value)
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "wa_template",
										children: "Approved template name"
									}), /* @__PURE__ */ jsx(Input, {
										id: "wa_template",
										placeholder: "analytics_alert",
										disabled: !canManage,
										value: notifications.whatsapp_template ?? "",
										onChange: (e) => setNotifications((c) => ({
											...c,
											whatsapp_template: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "wa_recipients",
										children: "WhatsApp numbers"
									}), /* @__PURE__ */ jsx(Input, {
										id: "wa_recipients",
										placeholder: "919812345678",
										disabled: !canManage,
										value: list("whatsapp_recipients"),
										onChange: (e) => setList("whatsapp_recipients", e.target.value)
									})]
								})
							]
						})
					]
				})
			}),
			/* @__PURE__ */ jsxs(ChartCard, {
				title: "Digests",
				subtitle: "Standing emails your team gets without asking",
				widgetKey: "admin.cost_settings",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "space-y-3",
					children: [
						/* @__PURE__ */ jsxs("div", {
							className: "grid gap-2 sm:grid-cols-[1fr_7rem]",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "space-y-1",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "daily_brief",
									children: "Morning brief"
								}), /* @__PURE__ */ jsx(Input, {
									id: "daily_brief",
									placeholder: "founder@brand.com",
									disabled: !canManage,
									value: list("daily_brief_recipients"),
									onChange: (e) => setList("daily_brief_recipients", e.target.value)
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "space-y-1",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "daily_hour",
									children: "Hour"
								}), /* @__PURE__ */ jsx(Input, {
									id: "daily_hour",
									type: "number",
									min: 0,
									max: 23,
									disabled: !canManage,
									value: notifications.daily_brief_hour ?? 8,
									onChange: (e) => setNotifications((c) => ({
										...c,
										daily_brief_hour: Number(e.target.value)
									}))
								})]
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "grid gap-2 sm:grid-cols-[1fr_7rem]",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "space-y-1",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "weekly_review",
									children: "Weekly business review (PDF)"
								}), /* @__PURE__ */ jsx(Input, {
									id: "weekly_review",
									disabled: !canManage,
									value: list("weekly_review_recipients"),
									onChange: (e) => setList("weekly_review_recipients", e.target.value)
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "space-y-1",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "weekly_day",
									children: "Day"
								}), /* @__PURE__ */ jsxs(Select, {
									value: String(notifications.weekly_review_day ?? 1),
									onValueChange: (value) => setNotifications((c) => ({
										...c,
										weekly_review_day: Number(value)
									})),
									children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsx(SelectContent, { children: [
										"Sun",
										"Mon",
										"Tue",
										"Wed",
										"Thu",
										"Fri",
										"Sat"
									].map((day, index) => /* @__PURE__ */ jsx(SelectItem, {
										value: String(index),
										children: day
									}, day)) })]
								})]
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "space-y-1",
							children: [/* @__PURE__ */ jsx(Label, {
								htmlFor: "monthly_pnl",
								children: "Monthly P&L (PDF, sent on the 1st)"
							}), /* @__PURE__ */ jsx(Input, {
								id: "monthly_pnl",
								disabled: !canManage,
								value: list("monthly_pnl_recipients"),
								onChange: (e) => setList("monthly_pnl_recipients", e.target.value)
							})]
						})
					]
				}), canManage && /* @__PURE__ */ jsx(Button, {
					size: "sm",
					className: "mt-3 w-full",
					disabled: saving,
					onClick: save,
					children: "Save settings"
				})]
			})
		]
	});
}
//#endregion
export { AdminUsers as default };

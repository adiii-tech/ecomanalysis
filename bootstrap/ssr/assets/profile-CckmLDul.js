import { t as cn } from "./utils-BVTyW6jK.js";
import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { a as SelectContent, c as SelectValue, i as Select, n as ACCENTS, o as SelectItem, r as useAppearance, s as SelectTrigger, t as AppLayout } from "./app-layout-DdOsQy6Y.js";
import { a as apiSend, i as apiGet, n as WidgetError } from "./empty-state-DjIQjBC7.js";
import { c as formatDateTime, t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as ChartCard } from "./chart-card-CZPTjzl-.js";
import { n as SkeletonChart } from "./skeleton-DUakt-29.js";
import { Head, router } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { useCallback, useEffect, useState } from "react";
import { Copy, Key, Loader2, LogOut, Monitor, ShieldCheck, Trash2 } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/settings/profile.tsx
function Profile() {
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [saving, setSaving] = useState(false);
	const [identity, setIdentity] = useState({
		name: "",
		email: ""
	});
	const [passwords, setPasswords] = useState({
		current_password: "",
		password: "",
		password_confirmation: ""
	});
	const [tokenName, setTokenName] = useState("");
	const [newToken, setNewToken] = useState(null);
	const { theme, setTheme, accent, setAccent } = useAppearance();
	const load = useCallback(() => {
		apiGet("/profile").then((response) => {
			setData(response.data);
			setIdentity({
				name: response.data.user.name,
				email: response.data.user.email
			});
			setError(null);
		}).catch((err) => setError(err instanceof Error ? err.message : "Could not load your profile."));
	}, []);
	useEffect(load, [load]);
	const saveIdentity = async () => {
		setSaving(true);
		try {
			const response = await apiSend("PUT", "/profile", identity);
			toast.success(response.message);
			load();
			router.reload({ only: ["auth"] });
		} catch (err) {
			toast.error(err instanceof Error ? err.message : "Could not save.");
		} finally {
			setSaving(false);
		}
	};
	/** Appearance is applied locally at once, then stored so other devices match. */
	const saveAppearance = async (next) => {
		if (next.theme) setTheme(next.theme);
		if (next.accent_color) setAccent(next.accent_color);
		try {
			await apiSend("PUT", "/profile", next);
		} catch {
			toast.error("Saved on this device, but could not sync to your account.");
		}
	};
	const changePassword = async () => {
		try {
			const response = await apiSend("PUT", "/profile/password", passwords);
			toast.success(response.message);
			setPasswords({
				current_password: "",
				password: "",
				password_confirmation: ""
			});
			load();
		} catch (err) {
			toast.error(err instanceof Error ? err.message : "Could not change your password.");
		}
	};
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Profile & security",
		description: "Your account, devices and API access",
		showFilters: false,
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Profile & security" }),
			error && /* @__PURE__ */ jsx(WidgetError, {
				message: error,
				onRetry: load
			}),
			!data && !error && /* @__PURE__ */ jsx(SkeletonChart, { className: "h-64" }),
			data && /* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 xl:grid-cols-2",
				children: [
					/* @__PURE__ */ jsx(ChartCard, {
						title: "Your details",
						subtitle: `${data.user.role ?? "No role"} · joined ${data.user.created_at ? formatDateTime(data.user.created_at) : "—"}`,
						children: /* @__PURE__ */ jsxs("div", {
							className: "space-y-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "name",
										children: "Name"
									}), /* @__PURE__ */ jsx(Input, {
										id: "name",
										value: identity.name,
										onChange: (e) => setIdentity((c) => ({
											...c,
											name: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "email",
										children: "Email"
									}), /* @__PURE__ */ jsx(Input, {
										id: "email",
										type: "email",
										value: identity.email,
										onChange: (e) => setIdentity((c) => ({
											...c,
											email: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs(Button, {
									size: "sm",
									onClick: saveIdentity,
									disabled: saving,
									children: [saving && /* @__PURE__ */ jsx(Loader2, { className: "size-3.5 animate-spin" }), " Save details"]
								})
							]
						})
					}),
					/* @__PURE__ */ jsx(ChartCard, {
						title: "Password",
						subtitle: "Changing it signs out your other devices",
						children: /* @__PURE__ */ jsxs("div", {
							className: "space-y-3",
							children: [
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "current_password",
										children: "Current password"
									}), /* @__PURE__ */ jsx(Input, {
										id: "current_password",
										type: "password",
										autoComplete: "current-password",
										value: passwords.current_password,
										onChange: (e) => setPasswords((c) => ({
											...c,
											current_password: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [
										/* @__PURE__ */ jsx(Label, {
											htmlFor: "password",
											children: "New password"
										}),
										/* @__PURE__ */ jsx(Input, {
											id: "password",
											type: "password",
											autoComplete: "new-password",
											value: passwords.password,
											onChange: (e) => setPasswords((c) => ({
												...c,
												password: e.target.value
											}))
										}),
										/* @__PURE__ */ jsx("p", {
											className: "text-[11px] text-muted-foreground",
											children: "At least 10 characters, with letters and numbers."
										})
									]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "space-y-1",
									children: [/* @__PURE__ */ jsx(Label, {
										htmlFor: "password_confirmation",
										children: "Confirm new password"
									}), /* @__PURE__ */ jsx(Input, {
										id: "password_confirmation",
										type: "password",
										autoComplete: "new-password",
										value: passwords.password_confirmation,
										onChange: (e) => setPasswords((c) => ({
											...c,
											password_confirmation: e.target.value
										}))
									})]
								}),
								/* @__PURE__ */ jsx(Button, {
									size: "sm",
									onClick: changePassword,
									children: "Change password"
								})
							]
						})
					}),
					/* @__PURE__ */ jsx(ChartCard, {
						title: "Appearance",
						subtitle: "Stored on your account, so every device matches",
						children: /* @__PURE__ */ jsxs("div", {
							className: "space-y-3",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsx(Label, { children: "Theme" }), /* @__PURE__ */ jsxs(Select, {
									value: theme,
									onValueChange: (value) => saveAppearance({ theme: value }),
									children: [/* @__PURE__ */ jsx(SelectTrigger, { children: /* @__PURE__ */ jsx(SelectValue, {}) }), /* @__PURE__ */ jsxs(SelectContent, { children: [
										/* @__PURE__ */ jsx(SelectItem, {
											value: "light",
											children: "Light"
										}),
										/* @__PURE__ */ jsx(SelectItem, {
											value: "dark",
											children: "Dark"
										}),
										/* @__PURE__ */ jsx(SelectItem, {
											value: "system",
											children: "Match my system"
										})
									] })]
								})]
							}), /* @__PURE__ */ jsxs("div", {
								className: "space-y-1.5",
								children: [/* @__PURE__ */ jsx(Label, { children: "Accent" }), /* @__PURE__ */ jsx("div", {
									className: "flex flex-wrap gap-2",
									children: ACCENTS.map((option) => /* @__PURE__ */ jsx("button", {
										type: "button",
										onClick: () => saveAppearance({ accent_color: option }),
										className: cn("rounded-lg border px-3 py-1.5 text-xs capitalize transition-colors", accent === option ? "border-primary bg-primary/5 font-medium" : "border-border hover:bg-accent/50"),
										children: option
									}, option))
								})]
							})]
						})
					}),
					/* @__PURE__ */ jsx(ChartCard, {
						title: "Two-factor authentication",
						subtitle: "A second step when signing in",
						children: /* @__PURE__ */ jsxs("div", {
							className: "flex items-center justify-between gap-3",
							children: [/* @__PURE__ */ jsxs("div", {
								className: "flex items-center gap-2",
								children: [/* @__PURE__ */ jsx(ShieldCheck, { className: cn("size-5", data.user.mfa_enabled ? "text-good" : "text-muted-foreground") }), /* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
									className: "text-sm font-medium",
									children: data.user.mfa_enabled ? "Enabled" : "Not enabled"
								}), /* @__PURE__ */ jsx("p", {
									className: "text-xs text-muted-foreground",
									children: data.user.mfa_enabled ? "You are asked for a code from your authenticator app at sign-in." : "Anyone with your password can sign in as you."
								})] })]
							}), /* @__PURE__ */ jsx(Button, {
								variant: data.user.mfa_enabled ? "outline" : "default",
								size: "sm",
								asChild: true,
								children: /* @__PURE__ */ jsx("a", {
									href: "/settings/mfa",
									children: data.user.mfa_enabled ? "Manage" : "Set up"
								})
							})]
						})
					}),
					/* @__PURE__ */ jsx(ChartCard, {
						title: "Signed-in devices",
						subtitle: `${data.sessions.length} active session${data.sessions.length === 1 ? "" : "s"}`,
						children: /* @__PURE__ */ jsxs("div", {
							className: "space-y-2",
							children: [data.sessions.map((session) => /* @__PURE__ */ jsx(Card, {
								className: "flex items-center justify-between gap-3 p-3",
								children: /* @__PURE__ */ jsxs("div", {
									className: "flex items-center gap-2.5",
									children: [/* @__PURE__ */ jsx(Monitor, { className: "size-4 text-muted-foreground" }), /* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsxs("p", {
										className: "text-sm font-medium",
										children: [session.device, session.is_current && /* @__PURE__ */ jsx(Badge, {
											variant: "good",
											className: "ml-2",
											children: "This device"
										})]
									}), /* @__PURE__ */ jsxs("p", {
										className: "text-[11px] text-muted-foreground",
										children: [
											session.ip_address,
											" · last active ",
											formatDateTime(session.last_active)
										]
									})] })]
								})
							}, session.id)), data.sessions.length > 1 && /* @__PURE__ */ jsxs(Button, {
								variant: "outline",
								size: "sm",
								className: "w-full",
								onClick: async () => {
									try {
										const response = await apiSend("DELETE", "/profile/sessions");
										toast.success(response.message);
										load();
									} catch {
										toast.error("Could not sign out the other devices.");
									}
								},
								children: [/* @__PURE__ */ jsx(LogOut, { className: "size-3.5" }), " Sign out everywhere else"]
							})]
						})
					}),
					/* @__PURE__ */ jsx(ChartCard, {
						title: "API tokens",
						subtitle: "For scripts and integrations that call this API directly",
						children: /* @__PURE__ */ jsxs("div", {
							className: "space-y-3",
							children: [
								newToken && /* @__PURE__ */ jsxs(Card, {
									className: "space-y-1.5 border-primary/40 bg-primary/5 p-3",
									children: [/* @__PURE__ */ jsx("p", {
										className: "text-xs font-medium",
										children: "Copy this now — it is not shown again."
									}), /* @__PURE__ */ jsxs("div", {
										className: "flex items-center gap-2",
										children: [/* @__PURE__ */ jsx("code", {
											className: "min-w-0 flex-1 truncate rounded bg-background px-2 py-1 text-[11px]",
											children: newToken
										}), /* @__PURE__ */ jsx(Button, {
											size: "icon",
											variant: "ghost",
											onClick: () => {
												navigator.clipboard?.writeText(newToken).catch(() => void 0);
												toast.success("Token copied.");
											},
											"aria-label": "Copy token",
											children: /* @__PURE__ */ jsx(Copy, { className: "size-4" })
										})]
									})]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "flex items-end gap-2",
									children: [/* @__PURE__ */ jsxs("div", {
										className: "flex-1 space-y-1",
										children: [/* @__PURE__ */ jsx(Label, {
											htmlFor: "token_name",
											children: "Token name"
										}), /* @__PURE__ */ jsx(Input, {
											id: "token_name",
											placeholder: "Warehouse script",
											value: tokenName,
											onChange: (e) => setTokenName(e.target.value)
										})]
									}), /* @__PURE__ */ jsxs(Button, {
										size: "sm",
										disabled: tokenName.trim() === "",
										onClick: async () => {
											try {
												const response = await apiSend("POST", "/profile/tokens", { name: tokenName });
												setNewToken(response.data.plain_text_token);
												setTokenName("");
												load();
											} catch (err) {
												toast.error(err instanceof Error ? err.message : "Could not create the token.");
											}
										},
										children: [/* @__PURE__ */ jsx(Key, { className: "size-3.5" }), " Create"]
									})]
								}),
								data.tokens.map((token) => /* @__PURE__ */ jsxs(Card, {
									className: "flex items-center justify-between gap-3 p-3",
									children: [/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx("p", {
										className: "text-sm font-medium",
										children: token.name
									}), /* @__PURE__ */ jsx("p", {
										className: "text-[11px] text-muted-foreground",
										children: token.last_used_at ? `last used ${formatDateTime(token.last_used_at)}` : "never used"
									})] }), /* @__PURE__ */ jsx(Button, {
										variant: "ghost",
										size: "icon",
										"aria-label": "Revoke token",
										onClick: async () => {
											try {
												await apiSend("DELETE", `/profile/tokens/${token.id}`);
												toast.success("Token revoked.");
												load();
											} catch {
												toast.error("Could not revoke that token.");
											}
										},
										children: /* @__PURE__ */ jsx(Trash2, { className: "size-4" })
									})]
								}, token.id))
							]
						})
					})
				]
			})
		]
	});
}
//#endregion
export { Profile as default };

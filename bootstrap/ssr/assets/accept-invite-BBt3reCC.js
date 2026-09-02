import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as AuthLayout } from "./auth-layout-DuLD23aV.js";
import { useForm } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { LoaderCircle } from "lucide-react";
//#region resources/js/pages/auth/accept-invite.tsx
function AcceptInvite({ token, email, name, role, tenant }) {
	const { data, setData, post, processing, errors } = useForm({
		name: name ?? "",
		password: "",
		password_confirmation: ""
	});
	return /* @__PURE__ */ jsxs(AuthLayout, {
		title: `Join ${tenant}`,
		description: "Set a password to activate your account.",
		children: [/* @__PURE__ */ jsxs("div", {
			className: "flex items-center gap-2 rounded-lg border border-border bg-accent/40 px-3 py-2 text-xs",
			children: [/* @__PURE__ */ jsx("span", {
				className: "text-muted-foreground",
				children: email
			}), /* @__PURE__ */ jsx(Badge, {
				variant: "secondary",
				children: role
			})]
		}), /* @__PURE__ */ jsxs("form", {
			className: "space-y-4",
			onSubmit: (event) => {
				event.preventDefault();
				post(`/accept-invite/${token}`);
			},
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "name",
							children: "Your name"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "name",
							autoFocus: true,
							value: data.name,
							onChange: (event) => setData("name", event.target.value)
						}),
						errors.name && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: errors.name
						})
					]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "password",
							children: "Password"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "password",
							type: "password",
							value: data.password,
							onChange: (event) => setData("password", event.target.value)
						}),
						errors.password && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: errors.password
						})
					]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [/* @__PURE__ */ jsx(Label, {
						htmlFor: "password_confirmation",
						children: "Confirm password"
					}), /* @__PURE__ */ jsx(Input, {
						id: "password_confirmation",
						type: "password",
						value: data.password_confirmation,
						onChange: (event) => setData("password_confirmation", event.target.value)
					})]
				}),
				/* @__PURE__ */ jsxs(Button, {
					type: "submit",
					className: "w-full",
					disabled: processing,
					children: [processing && /* @__PURE__ */ jsx(LoaderCircle, { className: "animate-spin" }), "Create account"]
				})
			]
		})]
	});
}
//#endregion
export { AcceptInvite as default };

import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AuthLayout } from "./auth-layout-DuLD23aV.js";
import { Link, useForm } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { LoaderCircle } from "lucide-react";
//#region resources/js/pages/auth/forgot-password.tsx
function ForgotPassword({ status }) {
	const { data, setData, post, processing, errors } = useForm({ email: "" });
	return /* @__PURE__ */ jsxs(AuthLayout, {
		title: "Reset your password",
		description: "We'll email you a link to set a new one.",
		children: [status && /* @__PURE__ */ jsx("div", {
			className: "rounded-lg bg-good-soft px-3 py-2 text-xs text-good",
			children: status
		}), /* @__PURE__ */ jsxs("form", {
			className: "space-y-4",
			onSubmit: (event) => {
				event.preventDefault();
				post("/forgot-password");
			},
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "email",
							children: "Email"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "email",
							type: "email",
							autoFocus: true,
							value: data.email,
							onChange: (event) => setData("email", event.target.value)
						}),
						errors.email && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: errors.email
						})
					]
				}),
				/* @__PURE__ */ jsxs(Button, {
					type: "submit",
					className: "w-full",
					disabled: processing,
					children: [processing && /* @__PURE__ */ jsx(LoaderCircle, { className: "animate-spin" }), "Email reset link"]
				}),
				/* @__PURE__ */ jsx(Link, {
					href: "/login",
					className: "block text-center text-xs text-muted-foreground hover:text-foreground",
					children: "Back to sign in"
				})
			]
		})]
	});
}
//#endregion
export { ForgotPassword as default };

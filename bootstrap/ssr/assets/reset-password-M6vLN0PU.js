import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AuthLayout } from "./auth-layout-DuLD23aV.js";
import { useForm } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { LoaderCircle } from "lucide-react";
//#region resources/js/pages/auth/reset-password.tsx
function ResetPassword({ token, email }) {
	const { data, setData, post, processing, errors } = useForm({
		token,
		email,
		password: "",
		password_confirmation: ""
	});
	return /* @__PURE__ */ jsx(AuthLayout, {
		title: "Set a new password",
		children: /* @__PURE__ */ jsxs("form", {
			className: "space-y-4",
			onSubmit: (event) => {
				event.preventDefault();
				post("/reset-password");
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
							value: data.email,
							onChange: (event) => setData("email", event.target.value),
							readOnly: true
						}),
						errors.email && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: errors.email
						})
					]
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "password",
							children: "New password"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "password",
							type: "password",
							autoFocus: true,
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
					children: [processing && /* @__PURE__ */ jsx(LoaderCircle, { className: "animate-spin" }), "Update password"]
				})
			]
		})
	});
}
//#endregion
export { ResetPassword as default };

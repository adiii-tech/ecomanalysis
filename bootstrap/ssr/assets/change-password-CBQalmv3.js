import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AuthLayout } from "./auth-layout-DuLD23aV.js";
import { useForm } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { LoaderCircle } from "lucide-react";
//#region resources/js/pages/auth/change-password.tsx
function ChangePassword() {
	const { data, setData, post, processing, errors } = useForm({
		current_password: "",
		password: "",
		password_confirmation: ""
	});
	return /* @__PURE__ */ jsx(AuthLayout, {
		title: "Choose a new password",
		description: "An admin has asked you to reset it before continuing.",
		children: /* @__PURE__ */ jsxs("form", {
			className: "space-y-4",
			onSubmit: (event) => {
				event.preventDefault();
				post("/password/change");
			},
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "current_password",
							children: "Current password"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "current_password",
							type: "password",
							autoFocus: true,
							value: data.current_password,
							onChange: (event) => setData("current_password", event.target.value)
						}),
						errors.current_password && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: errors.current_password
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
						children: "Confirm new password"
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
export { ChangePassword as default };

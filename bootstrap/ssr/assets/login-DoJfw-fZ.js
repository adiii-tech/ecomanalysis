import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AuthLayout } from "./auth-layout-DuLD23aV.js";
import { Link, useForm } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { LoaderCircle } from "lucide-react";
//#region resources/js/pages/auth/login.tsx
function Login({ canResetPassword, demoCredentials, status }) {
	const { data, setData, post, processing, errors, reset } = useForm({
		email: demoCredentials?.email ?? "",
		password: demoCredentials?.password ?? "",
		remember: false
	});
	return /* @__PURE__ */ jsxs(AuthLayout, {
		title: "Sign in",
		description: "Your profitability command centre.",
		children: [
			status && /* @__PURE__ */ jsx("div", {
				className: "rounded-lg bg-good-soft px-3 py-2 text-xs text-good",
				children: status
			}),
			/* @__PURE__ */ jsxs("form", {
				className: "space-y-4",
				onSubmit: (event) => {
					event.preventDefault();
					post("/login", { onFinish: () => reset("password") });
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
								autoComplete: "email",
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
					/* @__PURE__ */ jsxs("div", {
						className: "space-y-1.5",
						children: [
							/* @__PURE__ */ jsxs("div", {
								className: "flex items-center justify-between",
								children: [/* @__PURE__ */ jsx(Label, {
									htmlFor: "password",
									children: "Password"
								}), canResetPassword && /* @__PURE__ */ jsx(Link, {
									href: "/forgot-password",
									className: "text-xs text-muted-foreground hover:text-foreground",
									children: "Forgot?"
								})]
							}),
							/* @__PURE__ */ jsx(Input, {
								id: "password",
								type: "password",
								autoComplete: "current-password",
								value: data.password,
								onChange: (event) => setData("password", event.target.value)
							}),
							errors.password && /* @__PURE__ */ jsx("p", {
								className: "text-xs text-bad",
								children: errors.password
							})
						]
					}),
					/* @__PURE__ */ jsxs("label", {
						className: "flex items-center gap-2 text-xs text-muted-foreground",
						children: [/* @__PURE__ */ jsx("input", {
							type: "checkbox",
							checked: data.remember,
							onChange: (event) => setData("remember", event.target.checked),
							className: "size-3.5 rounded border-input"
						}), "Keep me signed in"]
					}),
					/* @__PURE__ */ jsxs(Button, {
						type: "submit",
						className: "w-full",
						disabled: processing,
						children: [processing && /* @__PURE__ */ jsx(LoaderCircle, { className: "animate-spin" }), "Sign in"]
					})
				]
			}),
			demoCredentials && /* @__PURE__ */ jsxs("p", {
				className: "rounded-lg border border-dashed border-border px-3 py-2 text-[11px] text-muted-foreground",
				children: [
					"Local demo tenant is pre-filled. Other seeded roles use the same password:",
					" ",
					/* @__PURE__ */ jsx("span", {
						className: "font-mono",
						children: "finance@"
					}),
					", ",
					/* @__PURE__ */ jsx("span", {
						className: "font-mono",
						children: "marketing@"
					}),
					",",
					" ",
					/* @__PURE__ */ jsx("span", {
						className: "font-mono",
						children: "ops@"
					}),
					", ",
					/* @__PURE__ */ jsx("span", {
						className: "font-mono",
						children: "analyst@"
					}),
					",",
					" ",
					/* @__PURE__ */ jsx("span", {
						className: "font-mono",
						children: "demo@kairaliving.test"
					}),
					"."
				]
			})
		]
	});
}
//#endregion
export { Login as default };

import { n as Label, r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AuthLayout } from "./auth-layout-DuLD23aV.js";
import { router, useForm } from "@inertiajs/react";
import { jsx, jsxs } from "react/jsx-runtime";
import { LoaderCircle } from "lucide-react";
//#region resources/js/pages/auth/mfa-challenge.tsx
function MfaChallenge() {
	const { data, setData, post, processing, errors } = useForm({ code: "" });
	return /* @__PURE__ */ jsx(AuthLayout, {
		title: "Two-factor code",
		description: "Enter the 6-digit code from your authenticator app.",
		children: /* @__PURE__ */ jsxs("form", {
			className: "space-y-4",
			onSubmit: (event) => {
				event.preventDefault();
				post("/mfa/verify");
			},
			children: [
				/* @__PURE__ */ jsxs("div", {
					className: "space-y-1.5",
					children: [
						/* @__PURE__ */ jsx(Label, {
							htmlFor: "code",
							children: "Authentication code"
						}),
						/* @__PURE__ */ jsx(Input, {
							id: "code",
							autoFocus: true,
							inputMode: "numeric",
							autoComplete: "one-time-code",
							placeholder: "123456",
							className: "text-center text-lg tracking-[0.4em] tnum",
							value: data.code,
							onChange: (event) => setData("code", event.target.value)
						}),
						errors.code && /* @__PURE__ */ jsx("p", {
							className: "text-xs text-bad",
							children: errors.code
						}),
						/* @__PURE__ */ jsx("p", {
							className: "text-[11px] text-muted-foreground",
							children: "Lost your device? Enter one of your recovery codes instead."
						})
					]
				}),
				/* @__PURE__ */ jsxs(Button, {
					type: "submit",
					className: "w-full",
					disabled: processing,
					children: [processing && /* @__PURE__ */ jsx(LoaderCircle, { className: "animate-spin" }), "Verify"]
				}),
				/* @__PURE__ */ jsx(Button, {
					type: "button",
					variant: "ghost",
					className: "w-full text-xs",
					onClick: () => router.post("/mfa/resend"),
					children: "I don’t have a code"
				})
			]
		})
	});
}
//#endregion
export { MfaChallenge as default };

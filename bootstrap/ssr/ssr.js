import { createInertiaApp } from "@inertiajs/react";
import createServer from "@inertiajs/react/server";
import ReactDOMServer from "react-dom/server";
import { jsx } from "react/jsx-runtime";
//#region node_modules/laravel-vite-plugin/inertia-helpers/index.js
async function resolvePageComponent(path, pages) {
	for (const p of Array.isArray(path) ? path : [path]) {
		const page = pages[p];
		if (typeof page === "undefined") continue;
		return typeof page === "function" ? page() : page;
	}
	throw new Error(`Page not found: ${path}`);
}
//#endregion
//#region resources/js/ssr.tsx
var appName = process.env.VITE_APP_NAME || "Ledgerloop";
createServer((page) => createInertiaApp({
	page,
	render: ReactDOMServer.renderToString,
	title: (title) => title ? `${title} · ${appName}` : appName,
	resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, /* #__PURE__ */ Object.assign({
		"./pages/admin/users.tsx": () => import("./assets/users-42fA1qpS.js"),
		"./pages/ai/index.tsx": () => import("./assets/ai-DC5VZrP6.js"),
		"./pages/ai/shared.tsx": () => import("./assets/shared-DHnEKQMw.js"),
		"./pages/alerts/index.tsx": () => import("./assets/alerts-ty-D71eX.js"),
		"./pages/auth/accept-invite.tsx": () => import("./assets/accept-invite-BBt3reCC.js"),
		"./pages/auth/change-password.tsx": () => import("./assets/change-password-CBQalmv3.js"),
		"./pages/auth/forgot-password.tsx": () => import("./assets/forgot-password-Cfoohqrn.js"),
		"./pages/auth/login.tsx": () => import("./assets/login-DoJfw-fZ.js"),
		"./pages/auth/mfa-challenge.tsx": () => import("./assets/mfa-challenge-CCB7tRy9.js"),
		"./pages/auth/reset-password.tsx": () => import("./assets/reset-password-M6vLN0PU.js"),
		"./pages/catalog/index.tsx": () => import("./assets/catalog-B1pFs9RB.js"),
		"./pages/connectors/index.tsx": () => import("./assets/connectors-2YCqwZSE.js"),
		"./pages/customers/explorer.tsx": () => import("./assets/explorer-CRsVKBZA.js"),
		"./pages/customers/index.tsx": () => import("./assets/customers-AVlokR1e.js"),
		"./pages/customers/show.tsx": () => import("./assets/show-NUEyE1KQ.js"),
		"./pages/dashboard/index.tsx": () => import("./assets/dashboard-BuAOXnoa.js"),
		"./pages/finance/index.tsx": () => import("./assets/finance-BA4MJS5s.js"),
		"./pages/instagram/index.tsx": () => import("./assets/instagram-Chhi1bSi.js"),
		"./pages/marketing/index.tsx": () => import("./assets/marketing-BTLoGH-g.js"),
		"./pages/marketplace/index.tsx": () => import("./assets/marketplace-CqXHiDBH.js"),
		"./pages/onboarding/index.tsx": () => import("./assets/onboarding-CzWE374p.js"),
		"./pages/operations/index.tsx": () => import("./assets/operations-B3_kOQVv.js"),
		"./pages/reports/index.tsx": () => import("./assets/reports-jh4n1ic9.js"),
		"./pages/reports/shared.tsx": () => import("./assets/shared-Bd9nwEXI.js"),
		"./pages/reports/show.tsx": () => import("./assets/show-ChuOMWPc.js"),
		"./pages/settings/profile.tsx": () => import("./assets/profile-B97WfPF4.js")
	})),
	setup: ({ App, props }) => /* @__PURE__ */ jsx(App, { ...props })
}));
//#endregion
export {};

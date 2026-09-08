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
		"./pages/admin/users.tsx": () => import("./assets/users-BU3shHn7.js"),
		"./pages/ai/index.tsx": () => import("./assets/ai-BzFPaUFf.js"),
		"./pages/ai/shared.tsx": () => import("./assets/shared-DHnEKQMw.js"),
		"./pages/alerts/index.tsx": () => import("./assets/alerts-B4Lb3MKq.js"),
		"./pages/auth/accept-invite.tsx": () => import("./assets/accept-invite-BBt3reCC.js"),
		"./pages/auth/change-password.tsx": () => import("./assets/change-password-CBQalmv3.js"),
		"./pages/auth/forgot-password.tsx": () => import("./assets/forgot-password-Cfoohqrn.js"),
		"./pages/auth/login.tsx": () => import("./assets/login-DoJfw-fZ.js"),
		"./pages/auth/mfa-challenge.tsx": () => import("./assets/mfa-challenge-CCB7tRy9.js"),
		"./pages/auth/reset-password.tsx": () => import("./assets/reset-password-M6vLN0PU.js"),
		"./pages/catalog/index.tsx": () => import("./assets/catalog-DK6UYZpE.js"),
		"./pages/connectors/index.tsx": () => import("./assets/connectors-CxM9lANb.js"),
		"./pages/customers/explorer.tsx": () => import("./assets/explorer-BdwgowSl.js"),
		"./pages/customers/index.tsx": () => import("./assets/customers-CA_sK-nM.js"),
		"./pages/customers/show.tsx": () => import("./assets/show-w0wAh7jT.js"),
		"./pages/dashboard/index.tsx": () => import("./assets/dashboard-Db-NCJhM.js"),
		"./pages/finance/index.tsx": () => import("./assets/finance-nlHZLeL7.js"),
		"./pages/instagram/index.tsx": () => import("./assets/instagram-D3sB71sX.js"),
		"./pages/inventory/counts.tsx": () => import("./assets/counts-6Dmsm9tr.js"),
		"./pages/inventory/index.tsx": () => import("./assets/inventory-DJeb_kSX.js"),
		"./pages/inventory/purchasing.tsx": () => import("./assets/purchasing-DsiL93tQ.js"),
		"./pages/marketing/index.tsx": () => import("./assets/marketing-5axRixjn.js"),
		"./pages/marketplace/index.tsx": () => import("./assets/marketplace-BljcrpNl.js"),
		"./pages/onboarding/index.tsx": () => import("./assets/onboarding-QAS472t-.js"),
		"./pages/operations/index.tsx": () => import("./assets/operations-BsVw2PiV.js"),
		"./pages/reports/index.tsx": () => import("./assets/reports-aFxko7-s.js"),
		"./pages/reports/shared.tsx": () => import("./assets/shared-Bd9nwEXI.js"),
		"./pages/reports/show.tsx": () => import("./assets/show-CnMpKUQM.js"),
		"./pages/settings/profile.tsx": () => import("./assets/profile-DL9rFpru.js")
	})),
	setup: ({ App, props }) => /* @__PURE__ */ jsx(App, { ...props })
}));
//#endregion
export {};

import { s as usePermissions } from "./empty-state-DjIQjBC7.js";
import { Fragment, jsx } from "react/jsx-runtime";
//#region resources/js/components/app/permission-guard.tsx
/**
* Hides a widget entirely when the user lacks its permission — no empty shell,
* no "restricted" placeholder. If it is not theirs to see, it is not there.
*/
function PermissionGuard({ permission, anyOf, children, fallback = null }) {
	const { can, canAny } = usePermissions();
	return (permission ? can(permission) : anyOf ? canAny(...anyOf) : true) ? /* @__PURE__ */ jsx(Fragment, { children }) : /* @__PURE__ */ jsx(Fragment, { children: fallback });
}
//#endregion
export { PermissionGuard as t };

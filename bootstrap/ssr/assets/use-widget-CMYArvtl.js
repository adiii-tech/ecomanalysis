import { i as apiGet, o as useFilters, r as ApiError } from "./empty-state-DjIQjBC7.js";
import { useEffect, useRef, useState } from "react";
//#region resources/js/hooks/use-widget.ts
/**
* Fetches one widget's payload with the current global filters attached.
* A 403 is not an error state — the widget simply is not visible to this user,
* so it renders nothing rather than an empty shell.
*/
function useWidget(path, extraParams = {}, enabled = true) {
	const { queryParams, filterKey } = useFilters();
	const [data, setData] = useState(null);
	const [meta, setMeta] = useState(null);
	const [loading, setLoading] = useState(enabled);
	const [error, setError] = useState(null);
	const [forbidden, setForbidden] = useState(false);
	const [nonce, setNonce] = useState(0);
	const extraKey = JSON.stringify(extraParams);
	const controllerRef = useRef(null);
	useEffect(() => {
		if (!enabled) {
			setLoading(false);
			return;
		}
		controllerRef.current?.abort();
		const controller = new AbortController();
		controllerRef.current = controller;
		setLoading(true);
		setError(null);
		apiGet(path, {
			...queryParams,
			...extraParams
		}, controller.signal).then((body) => {
			setData(body.data);
			setMeta(body.meta);
			setForbidden(false);
		}).catch((err) => {
			if (controller.signal.aborted) return;
			if (err instanceof ApiError && err.status === 403) {
				setForbidden(true);
				return;
			}
			setError(err instanceof Error ? err.message : "Something went wrong.");
		}).finally(() => {
			if (!controller.signal.aborted) setLoading(false);
		});
		return () => controller.abort();
	}, [
		path,
		filterKey,
		extraKey,
		enabled,
		nonce
	]);
	return {
		data,
		meta,
		loading,
		error,
		forbidden,
		reload: () => setNonce((n) => n + 1)
	};
}
//#endregion
export { useWidget as t };

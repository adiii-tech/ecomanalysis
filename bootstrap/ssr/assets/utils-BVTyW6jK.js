import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";
//#region resources/js/lib/utils.ts
function cn(...inputs) {
	return twMerge(clsx(inputs));
}
//#endregion
export { cn as t };

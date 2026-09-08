import { t as cn } from "./utils-BVTyW6jK.js";
import { r as Button, t as Input } from "./input-C0zE_xzz.js";
import { t as AppLayout } from "./app-layout-DhPbmqLN.js";
import { a as apiSend, i as apiGet, o as useFilters, t as EmptyState } from "./empty-state-DjIQjBC7.js";
import { t as Card } from "./card-DJDNvUnK.js";
import { t as Badge } from "./badge-CFyLZ3R-.js";
import { t as CaveatNote } from "./caveat-note-Dr_rbZyQ.js";
import { Head } from "@inertiajs/react";
import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { useEffect, useRef, useState } from "react";
import { Loader2, MessageSquarePlus, Send, Share2, Sparkles, Trash2, Wrench } from "lucide-react";
import { toast } from "sonner";
//#region resources/js/pages/ai/index.tsx
function AskAi() {
	const { queryParams } = useFilters();
	const [status, setStatus] = useState(null);
	const [sessions, setSessions] = useState([]);
	const [sessionId, setSessionId] = useState(null);
	const [messages, setMessages] = useState([]);
	const [input, setInput] = useState("");
	const [sending, setSending] = useState(false);
	const bottomRef = useRef(null);
	useEffect(() => {
		apiGet("ai/status").then((r) => setStatus(r.data)).catch(() => setStatus(null));
		refreshSessions();
	}, []);
	useEffect(() => {
		bottomRef.current?.scrollIntoView({ behavior: "smooth" });
	}, [messages]);
	function refreshSessions() {
		apiGet("ai/ask/history").then((r) => setSessions(r.data.rows)).catch(() => void 0);
	}
	async function openSession(id) {
		setSessionId(id);
		const response = await apiGet(`ai/ask/session/${id}`);
		setMessages(response.data.messages);
	}
	function newChat() {
		setSessionId(null);
		setMessages([]);
		setInput("");
	}
	async function send(question) {
		if (!question.trim() || sending) return;
		setInput("");
		setSending(true);
		setMessages((current) => [
			...current,
			{
				id: `local-${Date.now()}`,
				role: "user",
				content: question
			},
			{
				id: "pending",
				role: "assistant",
				content: "",
				pending: true
			}
		]);
		try {
			const response = await apiSend("POST", `ai/ask/chat?${new URLSearchParams(queryParams)}`, {
				message: question,
				session_id: sessionId
			});
			setSessionId(response.data.session_id);
			setMessages((current) => [...current.filter((m) => m.id !== "pending"), response.data.message]);
			setStatus((current) => current ? {
				...current,
				credits: response.data.credits
			} : current);
			refreshSessions();
		} catch (error) {
			setMessages((current) => current.filter((m) => m.id !== "pending"));
			toast.error(error instanceof Error ? error.message : "Something went wrong.");
		} finally {
			setSending(false);
		}
	}
	async function share() {
		if (!sessionId) return;
		try {
			const response = await apiSend("POST", `ai/ask/session/${sessionId}/share`);
			await navigator.clipboard.writeText(response.data.url).catch(() => void 0);
			toast.success("Share link copied. It is read-only and you can revoke it any time.");
			refreshSessions();
		} catch (error) {
			toast.error(error instanceof Error ? error.message : "Could not share.");
		}
	}
	async function remove(id) {
		await apiSend("DELETE", `ai/ask/session/${id}`).catch(() => void 0);
		if (id === sessionId) newChat();
		refreshSessions();
	}
	return /* @__PURE__ */ jsxs(AppLayout, {
		title: "Ask AI",
		description: "Ask about your own numbers",
		children: [
			/* @__PURE__ */ jsx(Head, { title: "Ask AI" }),
			status && !status.configured && /* @__PURE__ */ jsx(CaveatNote, { caveat: {
				message: status.caveat ?? "",
				level: "warning",
				connector: null
			} }),
			/* @__PURE__ */ jsxs("div", {
				className: "grid gap-4 lg:grid-cols-[240px_1fr]",
				children: [/* @__PURE__ */ jsxs("div", {
					className: "space-y-2",
					children: [/* @__PURE__ */ jsxs(Button, {
						size: "sm",
						className: "w-full gap-1.5",
						onClick: newChat,
						children: [/* @__PURE__ */ jsx(MessageSquarePlus, { className: "size-3.5" }), "New chat"]
					}), /* @__PURE__ */ jsxs("div", {
						className: "space-y-1",
						children: [sessions.map((session) => /* @__PURE__ */ jsxs("div", {
							className: cn("group flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs transition-colors", session.id === sessionId ? "bg-accent text-accent-foreground" : "hover:bg-accent/60"),
							children: [
								/* @__PURE__ */ jsx("button", {
									type: "button",
									onClick: () => openSession(session.id),
									className: "min-w-0 flex-1 truncate text-left",
									children: session.title
								}),
								session.shared && /* @__PURE__ */ jsx(Share2, { className: "size-3 shrink-0 text-muted-foreground" }),
								/* @__PURE__ */ jsx("button", {
									type: "button",
									onClick: () => remove(session.id),
									className: "shrink-0 opacity-0 transition group-hover:opacity-100",
									"aria-label": "Delete chat",
									children: /* @__PURE__ */ jsx(Trash2, { className: "size-3 text-muted-foreground hover:text-bad" })
								})
							]
						}, session.id)), sessions.length === 0 && /* @__PURE__ */ jsx("p", {
							className: "px-2 py-3 text-[11px] text-muted-foreground",
							children: "No chats yet."
						})]
					})]
				}), /* @__PURE__ */ jsxs(Card, {
					className: "flex h-[calc(100vh-15rem)] flex-col",
					children: [
						/* @__PURE__ */ jsxs("div", {
							className: "flex items-center justify-between border-b border-border px-4 py-2.5",
							children: [/* @__PURE__ */ jsxs("p", {
								className: "flex items-center gap-1.5 text-xs text-muted-foreground",
								children: [
									/* @__PURE__ */ jsx(Sparkles, { className: "size-3.5 text-primary" }),
									status?.model ?? "AI",
									" · reads only what your role can see"
								]
							}), /* @__PURE__ */ jsxs("div", {
								className: "flex items-center gap-2",
								children: [status && /* @__PURE__ */ jsxs(Badge, {
									variant: status.credits.remaining > 0 ? "muted" : "bad",
									children: [status.credits.remaining, " credits"]
								}), sessionId && /* @__PURE__ */ jsxs(Button, {
									size: "xs",
									variant: "ghost",
									onClick: share,
									className: "gap-1",
									children: [/* @__PURE__ */ jsx(Share2, { className: "size-3" }), "Share"]
								})]
							})]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "flex-1 space-y-4 overflow-auto p-4 scrollbar-thin",
							children: [
								messages.length === 0 && /* @__PURE__ */ jsxs("div", {
									className: "flex h-full flex-col items-center justify-center gap-4",
									children: [/* @__PURE__ */ jsx(EmptyState, {
										compact: true,
										title: "Ask about your numbers",
										description: "Answers come from your live data through a fixed set of read-only metrics — never invented."
									}), /* @__PURE__ */ jsx("div", {
										className: "grid w-full max-w-lg gap-1.5 sm:grid-cols-2",
										children: (status?.suggested_prompts ?? []).map((prompt) => /* @__PURE__ */ jsx("button", {
											type: "button",
											disabled: !status?.configured,
											onClick: () => send(prompt),
											className: "rounded-lg border border-border px-3 py-2 text-left text-xs transition-colors hover:bg-accent disabled:opacity-50",
											children: prompt
										}, prompt))
									})]
								}),
								messages.map((message) => /* @__PURE__ */ jsx("div", {
									className: cn("flex", message.role === "user" ? "justify-end" : "justify-start"),
									children: /* @__PURE__ */ jsx("div", {
										className: cn("max-w-[85%] rounded-xl px-3.5 py-2.5 text-sm leading-relaxed", message.role === "user" ? "bg-primary text-primary-foreground" : "bg-muted"),
										children: message.pending ? /* @__PURE__ */ jsxs("span", {
											className: "flex items-center gap-2 text-xs text-muted-foreground",
											children: [/* @__PURE__ */ jsx(Loader2, { className: "size-3.5 animate-spin" }), "Reading your data…"]
										}) : /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx("p", {
											className: "whitespace-pre-wrap",
											children: message.content
										}), (message.tool_calls?.length ?? 0) > 0 && /* @__PURE__ */ jsxs("p", {
											className: "mt-2 flex flex-wrap items-center gap-1 border-t border-border/40 pt-1.5 text-[10px] text-muted-foreground",
											children: [/* @__PURE__ */ jsx(Wrench, { className: "size-2.5" }), message.tool_calls.map((call, index) => /* @__PURE__ */ jsx("span", {
												className: cn("rounded bg-background/60 px-1", !call.ok && "text-bad"),
												children: call.tool
											}, index))]
										})] })
									})
								}, message.id)),
								/* @__PURE__ */ jsx("div", { ref: bottomRef })
							]
						}),
						/* @__PURE__ */ jsxs("form", {
							className: "flex items-center gap-2 border-t border-border p-3",
							onSubmit: (event) => {
								event.preventDefault();
								send(input);
							},
							children: [/* @__PURE__ */ jsx(Input, {
								value: input,
								onChange: (event) => setInput(event.target.value),
								placeholder: status?.configured ? "Ask about sales, margin, returns, campaigns…" : "AI is not configured on this server",
								disabled: !status?.configured || sending
							}), /* @__PURE__ */ jsx(Button, {
								type: "submit",
								size: "icon",
								disabled: !status?.configured || sending || !input.trim(),
								children: sending ? /* @__PURE__ */ jsx(Loader2, { className: "animate-spin" }) : /* @__PURE__ */ jsx(Send, {})
							})]
						})
					]
				})]
			})
		]
	});
}
//#endregion
export { AskAi as default };

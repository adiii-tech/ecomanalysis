<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Support\TenantContext;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A publicly shared AI answer.
 *
 * Read-only, revocable, and scoped to one conversation — the token grants no
 * access to anything else in the tenant. Revoking it clears the token, so the
 * link stops working immediately.
 */
class SharedAnswerController extends Controller
{
    public function __construct(private readonly TenantContext $context) {}

    public function show(string $token): Response
    {
        $session = $this->context->withoutScope(fn (): ?AiChatSession => AiChatSession::query()
            ->with('tenant:id,name')
            ->where('share_token', $token)
            ->whereNotNull('shared_at')
            ->first());

        if ($session === null) {
            throw new NotFoundHttpException('That shared answer is no longer available.');
        }

        $messages = $this->context->withoutScope(fn () => AiChatMessage::query()
            ->where('ai_chat_session_id', $session->id)
            ->orderBy('id')
            ->get()
            ->map(static fn (AiChatMessage $m): array => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all());

        return Inertia::render('ai/shared', [
            'title' => $session->title,
            'brand' => $session->tenant?->name,
            'sharedAt' => $session->shared_at?->toIso8601String(),
            'messages' => $messages,
        ]);
    }
}

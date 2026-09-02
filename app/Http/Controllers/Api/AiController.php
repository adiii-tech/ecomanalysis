<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\AI\Services\AnalystAgent;
use App\Domain\AI\Services\ClaudeClient;
use App\Domain\AI\Services\CreditMeter;
use App\Domain\AI\Services\InsightWriter;
use App\Domain\AI\Tools\ToolRegistry;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Models\AiInsightCache;
use App\Support\Facades\Tenant;
use App\Support\MetricCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AiController extends Controller
{
    use ResolvesFilters;

    public function __construct(
        private readonly ClaudeClient $claude,
        private readonly AnalystAgent $agent,
        private readonly CreditMeter $credits,
        private readonly InsightWriter $insights,
        private readonly ToolRegistry $tools,
    ) {}

    /** What the UI needs to decide whether to offer AI at all. */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::ok([
            'configured' => $this->claude->isConfigured(),
            'model' => $this->claude->model(),
            'credits' => $this->credits->summary($user, Tenant::current()),
            'tools' => $this->tools->forUser($user)->map(static fn ($tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
            ])->all(),
            'suggested_prompts' => [
                "How's this month's sales vs last month?",
                'Which channel is most profitable right now?',
                'How are returns and RTO trending?',
                'What should I fix first to grow next month?',
            ],
            'caveat' => $this->claude->isConfigured()
                ? null
                : 'No Anthropic API key is configured on this server, so AI features are switched off. An admin needs to add ANTHROPIC_API_KEY.',
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'session_id' => ['nullable', 'integer'],
        ]);

        if (! $this->claude->isConfigured()) {
            return ApiResponse::error('AI is not configured on this server.', 503);
        }

        $user = $request->user();
        $tenant = Tenant::current();

        if (! $this->credits->canSpend($user, $tenant, 'chat')) {
            return ApiResponse::error(
                'You are out of AI credits for this period.',
                402,
                ['credits' => $this->credits->summary($user, $tenant)],
            );
        }

        $session = $this->resolveSession($user, $validated['session_id'] ?? null, $validated['message']);
        $filters = $this->filters($request);

        AiChatMessage::query()->create([
            'tenant_id' => $tenant->id,
            'ai_chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $validated['message'],
        ]);

        $startedAt = hrtime(true);

        try {
            $result = $this->agent->ask($user, $tenant, $validated['message'], $filters, $this->transcript($session));
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('The AI request failed: '.$e->getMessage(), 502);
        }

        $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        $message = AiChatMessage::query()->create([
            'tenant_id' => $tenant->id,
            'ai_chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $result['answer'],
            'tool_calls' => $result['tool_calls'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
            'latency_ms' => $latency,
            'model' => $result['model'],
        ]);

        $session->forceFill(['last_message_at' => now()])->save();

        $this->credits->spend($user, $tenant, 'chat', $result['input_tokens'], $result['output_tokens'], $result['model']);

        return ApiResponse::ok([
            'session_id' => $session->id,
            'session_title' => $session->title,
            'message' => [
                'id' => $message->id,
                'role' => 'assistant',
                'content' => $result['answer'],
                'tool_calls' => $result['tool_calls'],
                'latency_ms' => $latency,
            ],
            'credits' => $this->credits->summary($user->fresh(), $tenant->fresh()),
        ]);
    }

    /**
     * Replays the conversation so far.
     *
     * @return list<array{role: string, content: string}>
     */
    private function transcript(?AiChatSession $session = null): array
    {
        if ($session === null) {
            return [];
        }

        // Only the plain text turns are replayed; tool traffic from previous
        // turns would balloon the prompt for no benefit.
        return $session->messages()
            ->orderBy('id')
            ->get()
            ->filter(static fn (AiChatMessage $m): bool => filled($m->content))
            ->map(static fn (AiChatMessage $m): array => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    public function sessions(Request $request): JsonResponse
    {
        $sessions = AiChatSession::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get()
            ->map(static fn (AiChatSession $s): array => [
                'id' => $s->id,
                'title' => $s->title,
                'last_message_at' => $s->last_message_at?->toIso8601String(),
                'shared' => $s->share_token !== null,
            ]);

        return ApiResponse::ok(['rows' => $sessions->all()]);
    }

    public function session(Request $request, int $session): JsonResponse
    {
        $model = AiChatSession::query()->where('user_id', $request->user()->id)->find($session);

        if ($model === null) {
            return ApiResponse::error('Chat not found.', 404);
        }

        return ApiResponse::ok([
            'id' => $model->id,
            'title' => $model->title,
            'share_token' => $model->share_token,
            'messages' => $model->messages()->orderBy('id')->get()->map(static fn (AiChatMessage $m): array => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'tool_calls' => $m->tool_calls,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function renameSession(Request $request, int $session): JsonResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'max:120']]);
        $model = AiChatSession::query()->where('user_id', $request->user()->id)->find($session);

        if ($model === null) {
            return ApiResponse::error('Chat not found.', 404);
        }

        $model->forceFill(['title' => $validated['title']])->save();

        return ApiResponse::ok(['title' => $model->title], message: 'Renamed.');
    }

    public function deleteSession(Request $request, int $session): JsonResponse
    {
        AiChatSession::query()->where('user_id', $request->user()->id)->find($session)?->delete();

        return ApiResponse::ok(null, message: 'Chat deleted.');
    }

    /** Creates or revokes a read-only public link to one answer thread. */
    public function share(Request $request, int $session): JsonResponse
    {
        $model = AiChatSession::query()->where('user_id', $request->user()->id)->find($session);

        if ($model === null) {
            return ApiResponse::error('Chat not found.', 404);
        }

        if ($request->boolean('revoke')) {
            $model->forceFill(['share_token' => null, 'shared_at' => null])->save();

            return ApiResponse::ok(['share_token' => null], message: 'Link revoked.');
        }

        $model->forceFill([
            'share_token' => $model->share_token ?? Str::random(40),
            'shared_at' => now(),
        ])->save();

        return ApiResponse::ok([
            'share_token' => $model->share_token,
            'url' => url('/ask-ai/shared/'.$model->share_token),
        ], message: 'Share link ready.');
    }

    /**
     * The ✨ button. Insights are cached per widget and date range, so the
     * second person to look at the same chart today pays nothing.
     */
    public function chartInsight(Request $request, MetricCache $cache): JsonResponse
    {
        $validated = $request->validate([
            'widget_key' => ['required', 'string', 'max:96'],
            'title' => ['required', 'string', 'max:120'],
            'payload' => ['required', 'array'],
            'refresh' => ['boolean'],
        ]);

        if (! $this->claude->isConfigured()) {
            return ApiResponse::error('AI is not configured on this server.', 503);
        }

        $user = $request->user();
        $tenant = Tenant::current();

        if (! $user->can($validated['widget_key'].'.view')) {
            return ApiResponse::error('You do not have access to that widget.', 403);
        }

        $filters = $this->filters($request);
        $cacheKey = $cache->key($tenant->id, 'insight', $validated['widget_key'], $filters->cacheKey());

        if ($request->boolean('refresh')) {
            $this->insights->forget($cacheKey);
        }

        // Only a generation costs a credit; a cache hit is free.
        $wasCached = AiInsightCache::query()
            ->where('cache_key', $cacheKey)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $wasCached && ! $this->credits->canSpend($user, $tenant, 'chart_insight')) {
            return ApiResponse::error('You are out of AI credits.', 402, [
                'credits' => $this->credits->summary($user, $tenant),
            ]);
        }

        try {
            $content = $this->insights->forWidget(
                $validated['widget_key'],
                $validated['title'],
                $validated['payload'],
                $cacheKey,
            );
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error('Could not generate an insight: '.$e->getMessage(), 502);
        }

        if (! $wasCached) {
            $this->credits->spend($user, $tenant, 'chart_insight', model: $this->claude->model());
        }

        return ApiResponse::ok([
            'content' => $content,
            'cached' => $wasCached,
            'credits' => $this->credits->summary($user->fresh(), $tenant->fresh()),
        ]);
    }

    private function resolveSession($user, ?int $sessionId, string $firstMessage): AiChatSession
    {
        if ($sessionId !== null) {
            $existing = AiChatSession::query()->where('user_id', $user->id)->find($sessionId);

            if ($existing !== null) {
                return $existing;
            }
        }

        return AiChatSession::query()->create([
            'tenant_id' => Tenant::id(),
            'user_id' => $user->id,
            'title' => Str::limit($firstMessage, 60),
            'last_message_at' => now(),
        ]);
    }
}

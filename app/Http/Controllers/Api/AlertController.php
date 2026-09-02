<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Alerts\Services\MetricResolver;
use App\Domain\Alerts\Services\RuleEvaluator;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\NotificationSetting;
use App\Support\Facades\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Pennant\Feature;

class AlertController extends Controller
{
    /** Everything the rule builder needs to render itself. */
    public function schema(): JsonResponse
    {
        // A channel is only offered when it is actually configured, so nobody
        // picks a delivery route that would silently go nowhere.
        $notifications = NotificationSetting::query()->firstOrNew(['tenant_id' => Tenant::id()]);

        return ApiResponse::ok([
            'metrics' => collect(MetricResolver::catalogue())
                ->map(static fn (array $meta, string $key): array => ['key' => $key, ...$meta])
                ->values()
                ->all(),
            'operators' => [
                ['key' => 'gt', 'label' => 'is above'],
                ['key' => 'gte', 'label' => 'is at or above'],
                ['key' => 'lt', 'label' => 'is below'],
                ['key' => 'lte', 'label' => 'is at or below'],
            ],
            'channels' => [
                ['key' => 'in_app', 'label' => 'In-app bell', 'available' => true],
                ['key' => 'email', 'label' => 'Email', 'available' => true],
                ['key' => 'whatsapp', 'label' => 'WhatsApp', 'available' => $notifications->hasWhatsapp(),
                    'note' => $notifications->hasWhatsapp() ? null : 'Add your WhatsApp Cloud API details in Admin → Settings.'],
                ['key' => 'slack', 'label' => 'Slack webhook', 'available' => $notifications->hasSlack(),
                    'note' => $notifications->hasSlack() ? null : 'Add a Slack incoming webhook in Admin → Settings.'],
            ],
            'windows' => [1, 3, 7, 14, 30],
        ]);
    }

    public function index(): JsonResponse
    {
        $rules = AlertRule::query()
            ->withCount(['events as recent_events' => fn ($q) => $q->where('created_at', '>', now()->subDays(30))])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(static fn (AlertRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'metric' => $rule->metric,
                'metric_label' => MetricResolver::catalogue()[$rule->metric]['label'] ?? $rule->metric,
                'operator' => $rule->operator,
                'threshold' => (float) $rule->threshold,
                'window_days' => $rule->window_days,
                'scope' => $rule->scope,
                'scope_values' => $rule->scope_values,
                'channels' => $rule->channels,
                'is_active' => $rule->is_active,
                'is_muted' => $rule->is_muted,
                'last_triggered_at' => $rule->last_triggered_at?->toIso8601String(),
                'last_evaluated_at' => $rule->last_evaluated_at?->toIso8601String(),
                // Set by the withCount alias above, so it is read as an attribute.
                'recent_events' => (int) $rule->getAttribute('recent_events'),
            ]);

        return ApiResponse::ok(['rows' => $rules->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $limit = (int) Feature::value('alert-rule-limit');
        $existing = AlertRule::query()->count();

        if ($existing >= $limit) {
            return ApiResponse::error(
                $limit === 0
                    ? 'Alerts are not part of the demo plan. Upgrade to be told when something breaks.'
                    : sprintf('Your %s plan allows %d alert rules and you have %d. Delete one or upgrade.',
                        Tenant::current()->plan->label(), $limit, $existing),
                422,
                ['upgrade_required' => true, 'limit' => $limit],
            );
        }

        $rule = AlertRule::query()->create([
            ...$this->validated($request),
            'tenant_id' => Tenant::id(),
            'created_by' => $request->user()->id,
        ]);

        activity('alerts')->performedOn($rule)->withProperties(['name' => $rule->name])->log('alert_rule.created');

        return ApiResponse::ok(['id' => $rule->id], message: 'Rule created.');
    }

    public function update(Request $request, int $rule): JsonResponse
    {
        $model = AlertRule::query()->find($rule);

        if ($model === null) {
            return ApiResponse::error('Rule not found.', 404);
        }

        $model->forceFill($this->validated($request))->save();

        activity('alerts')->performedOn($model)->log('alert_rule.updated');

        return ApiResponse::ok(null, message: 'Rule saved.');
    }

    public function destroy(int $rule): JsonResponse
    {
        AlertRule::query()->find($rule)?->delete();

        return ApiResponse::ok(null, message: 'Rule deleted.');
    }

    /**
     * Runs the rule against live data without raising an event, so the author
     * can see whether it would fire before committing to it.
     */
    public function test(Request $request, RuleEvaluator $evaluator): JsonResponse
    {
        $data = $this->validated($request);

        $draft = new AlertRule([...$data, 'tenant_id' => Tenant::id()]);
        $draft->id = 0;

        $events = $evaluator->evaluate($draft, dryRun: true);

        return ApiResponse::ok([
            'would_fire' => $events->isNotEmpty(),
            'preview' => $events->map(static fn (AlertEvent $e): array => [
                'title' => $e->title,
                'body' => $e->body,
                'severity' => $e->severity,
                'observed_value' => $e->observed_value,
            ])->all(),
            'message' => $events->isEmpty()
                ? 'Nothing breaches this rule right now — it would stay quiet.'
                : 'This rule would fire on current data.',
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $events = AlertEvent::query()
            ->with('rule:id,name,metric')
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->where(fn ($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<', now()))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(static fn (AlertEvent $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'body' => $event->body,
                'severity' => $event->severity,
                'observed_value' => $event->observed_value,
                'threshold' => $event->threshold,
                'dimension_value' => $event->dimension_value,
                'rule' => $event->rule?->only(['id', 'name', 'metric']),
                'read_at' => $event->read_at?->toIso8601String(),
                'created_at' => $event->created_at?->toIso8601String(),
            ]);

        return ApiResponse::ok([
            'rows' => $events->all(),
            'unread' => AlertEvent::query()->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        AlertEvent::query()
            ->when($request->filled('id'), fn ($q) => $q->whereKey($request->integer('id')))
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::ok(null, message: 'Marked as read.');
    }

    public function snooze(Request $request, int $event): JsonResponse
    {
        $days = min(max($request->integer('days', 7), 1), 90);

        AlertEvent::query()->find($event)?->forceFill(['snoozed_until' => now()->addDays($days)])->save();

        return ApiResponse::ok(null, message: "Snoozed for {$days} days.");
    }

    public function mute(Request $request, int $rule): JsonResponse
    {
        $model = AlertRule::query()->find($rule);

        if ($model === null) {
            return ApiResponse::error('Rule not found.', 404);
        }

        $muted = $request->boolean('muted', ! $model->is_muted);

        $model->forceFill([
            'is_muted' => $muted,
            'muted_until' => $muted ? now()->addDays($request->integer('days', 30)) : null,
        ])->save();

        return ApiResponse::ok(['is_muted' => $muted], message: $muted ? 'Rule muted.' : 'Rule unmuted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'metric' => ['required', 'string', Rule::in(array_keys(MetricResolver::catalogue()))],
            'operator' => ['required', Rule::in(['gt', 'gte', 'lt', 'lte', 'eq'])],
            'threshold' => ['required', 'numeric'],
            'window_days' => ['required', 'integer', 'min:1', 'max:90'],
            'scope' => ['nullable', Rule::in(['global', 'state', 'campaign', 'sku', 'channel'])],
            'scope_values' => ['nullable', 'array'],
            'scope_values.*' => ['string', 'max:96'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', Rule::in(['in_app', 'email', 'whatsapp', 'slack'])],
            'frequency' => ['nullable', Rule::in(['realtime', 'daily', 'weekly'])],
            'is_active' => ['boolean'],
        ]);
    }
}

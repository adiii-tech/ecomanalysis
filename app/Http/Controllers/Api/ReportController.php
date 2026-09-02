<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Reports\Reports\Report;
use App\Domain\Reports\Reports\ReportRegistry;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ReportFavourite;
use App\Models\ReportSchedule;
use App\Models\ReportShare;
use App\Support\Facades\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

class ReportController extends Controller
{
    use ResolvesFilters;

    public function __construct(private readonly ReportRegistry $registry) {}

    /**
     * The library index: only the reports this user may open, with their
     * favourites and recent usage folded in.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $reports = $this->registry->forUser($user);
        $restricted = $this->registry->restrictedFor(Tenant::current());

        $records = ReportFavourite::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('report_key');

        return ApiResponse::ok([
            'reports' => array_map(static fn (Report $report): array => [
                ...$report->meta(),
                'requires_upgrade' => in_array($report->key(), $restricted, true),
                'is_favourite' => (bool) ($records->get($report->key())?->is_favourite ?? false),
                'last_used_at' => $records->get($report->key())?->last_used_at?->toIso8601String(),
            ], $reports),
            'categories' => array_values(array_unique(array_map(
                static fn (Report $report): string => $report->category(),
                $reports,
            ))),
        ]);
    }

    public function show(Request $request, string $report): JsonResponse
    {
        $definition = $this->registry->find($report);

        if ($definition === null) {
            return ApiResponse::error('That report does not exist.', 404);
        }

        if ($request->user()->cannot($definition->permission())) {
            return ApiResponse::error('You do not have access to this report.', 403);
        }

        $tenant = Tenant::current();

        if (in_array($definition->key(), $this->registry->restrictedFor($tenant), true)) {
            return ApiResponse::error(
                sprintf('%s is part of the Growth plan. Your %s plan does not include it yet.',
                    $definition->label(), $tenant->plan->label()),
                403,
                ['upgrade_required' => true],
            );
        }

        $filters = $this->filters($request);

        // Reports fan out across several queries, so the whole payload is
        // cached as one unit under the report's own key.
        $payload = $this->cached('report', $definition->key(), $filters,
            fn (): array => $definition->build($filters)->toArray());

        $this->touchUsage($request, $definition);

        return ApiResponse::ok([
            'report' => $definition->meta(),
            ...$payload,
        ], $definition->usesPeriod() ? $filters : null);
    }

    public function toggleFavourite(Request $request, string $report): JsonResponse
    {
        $definition = $this->registry->find($report);

        if ($definition === null) {
            return ApiResponse::error('That report does not exist.', 404);
        }

        $record = ReportFavourite::query()->firstOrCreate(
            ['tenant_id' => Tenant::id(), 'user_id' => $request->user()->id, 'report_key' => $definition->key()],
        );

        $record->forceFill(['is_favourite' => ! $record->is_favourite])->save();

        return ApiResponse::ok(
            ['is_favourite' => $record->is_favourite],
            message: $record->is_favourite ? 'Added to favourites.' : 'Removed from favourites.',
        );
    }

    /**
     * A read-only snapshot link. The filters are frozen into the share so the
     * recipient sees exactly the window the sender was looking at.
     */
    public function share(Request $request, string $report): JsonResponse
    {
        $definition = $this->registry->find($report);

        if ($definition === null) {
            return ApiResponse::error('That report does not exist.', 404);
        }

        if ($request->user()->cannot($definition->permission())) {
            return ApiResponse::error('You cannot share a report you cannot open.', 403);
        }

        if (! Feature::active('scheduled-delivery')) {
            return ApiResponse::error('Share links are part of a paid plan.', 403, ['upgrade_required' => true]);
        }

        $validated = $request->validate([
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $filters = $this->filters($request);

        $share = ReportShare::query()->create([
            'tenant_id' => Tenant::id(),
            'user_id' => $request->user()->id,
            'report_key' => $definition->key(),
            'token' => Str::random(48),
            'filters' => [
                'from' => $filters->period->fromDate(),
                'to' => $filters->period->toDate(),
                'channel' => $filters->channelScope,
                'returns_basis' => $filters->returnsBasis,
                'payment_mode' => $filters->paymentMode,
            ],
            'expires_at' => now()->addDays($validated['expires_in_days'] ?? 30),
        ]);

        activity('reports')->performedOn($share)
            ->withProperties(['report' => $definition->key()])
            ->log('report.shared');

        return ApiResponse::ok([
            'token' => $share->token,
            'url' => url('/shared/reports/'.$share->token),
            'expires_at' => $share->expires_at?->toIso8601String(),
        ], message: 'Share link created.');
    }

    public function shares(Request $request, string $report): JsonResponse
    {
        $definition = $this->registry->find($report);

        if ($definition === null) {
            return ApiResponse::error('That report does not exist.', 404);
        }

        $shares = ReportShare::query()
            ->where('report_key', $definition->key())
            ->whereNull('revoked_at')
            ->latest('id')
            ->get()
            ->map(static fn (ReportShare $share): array => [
                'id' => $share->id,
                'url' => url('/shared/reports/'.$share->token),
                'created_at' => $share->created_at?->toIso8601String(),
                'expires_at' => $share->expires_at?->toIso8601String(),
                'view_count' => $share->view_count,
                'is_expired' => $share->expires_at !== null && $share->expires_at->isPast(),
            ]);

        return ApiResponse::ok(['rows' => $shares->all()]);
    }

    public function revokeShare(Request $request, string $report, int $share): JsonResponse
    {
        $model = ReportShare::query()->find($share);

        if ($model === null) {
            return ApiResponse::error('Share link not found.', 404);
        }

        $model->forceFill(['revoked_at' => now()])->save();

        activity('reports')->performedOn($model)->log('report.share_revoked');

        return ApiResponse::ok(null, message: 'Share link revoked.');
    }

    /** Scheduled email delivery of a report. */
    public function schedules(Request $request, string $report): JsonResponse
    {
        $definition = $this->registry->find($report);

        if ($definition === null) {
            return ApiResponse::error('That report does not exist.', 404);
        }

        $rows = ReportSchedule::query()
            ->where('report_key', $definition->key())
            ->latest('id')
            ->get()
            ->map(static fn (ReportSchedule $schedule): array => [
                'id' => $schedule->id,
                'cadence' => $schedule->cadence,
                'hour' => $schedule->hour,
                'day_of_week' => $schedule->day_of_week,
                'day_of_month' => $schedule->day_of_month,
                'recipients' => $schedule->recipients,
                'format' => $schedule->format,
                'is_active' => $schedule->is_active,
                'last_sent_at' => $schedule->last_sent_at?->toIso8601String(),
            ]);

        return ApiResponse::ok(['rows' => $rows->all()]);
    }

    public function saveSchedule(Request $request, string $report): JsonResponse
    {
        $definition = $this->registry->find($report);

        if ($definition === null) {
            return ApiResponse::error('That report does not exist.', 404);
        }

        if ($request->user()->cannot($definition->permission())) {
            return ApiResponse::error('You cannot schedule a report you cannot open.', 403);
        }

        if (! Feature::active('scheduled-delivery')) {
            return ApiResponse::error('Scheduled delivery is part of a paid plan.', 403, ['upgrade_required' => true]);
        }

        $validated = $request->validate([
            'cadence' => ['required', 'in:daily,weekly,monthly'],
            'hour' => ['required', 'integer', 'min:0', 'max:23'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:28'],
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['email'],
            'format' => ['required', 'in:pdf,csv,xlsx'],
        ]);

        $filters = $this->filters($request);

        $schedule = ReportSchedule::query()->create([
            ...$validated,
            'tenant_id' => Tenant::id(),
            'user_id' => $request->user()->id,
            'report_key' => $definition->key(),
            'filters' => ['preset' => $request->string('preset')->toString() ?: 'last_30_days', 'channel' => $filters->channelScope],
            'is_active' => true,
        ]);

        activity('reports')->performedOn($schedule)
            ->withProperties(['report' => $definition->key(), 'cadence' => $schedule->cadence])
            ->log('report.scheduled');

        return ApiResponse::ok(['id' => $schedule->id], message: 'Schedule saved.');
    }

    public function deleteSchedule(Request $request, string $report, int $schedule): JsonResponse
    {
        $model = ReportSchedule::query()->find($schedule);

        if ($model === null) {
            return ApiResponse::error('Schedule not found.', 404);
        }

        $model->delete();

        activity('reports')->log('report.schedule_deleted');

        return ApiResponse::ok(null, message: 'Schedule deleted.');
    }

    /**
     * Recording a visit is what makes "recently used" in the library real
     * rather than a guess.
     */
    private function touchUsage(Request $request, Report $definition): void
    {
        ReportFavourite::query()->updateOrCreate(
            ['tenant_id' => Tenant::id(), 'user_id' => $request->user()->id, 'report_key' => $definition->key()],
            ['last_used_at' => now()],
        );
    }
}

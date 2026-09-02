<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Customers\Segments\SegmentBuilder;
use App\Domain\Customers\Segments\SegmentExporter;
use App\Domain\Customers\Segments\SegmentFieldRegistry;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Support\Facades\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The customer explorer: build a segment from whitelisted rules, see who is in
 * it before saving, and export it to the tool that will actually message them.
 */
class SegmentController extends Controller
{
    public function __construct(
        private readonly SegmentBuilder $builder,
        private readonly SegmentExporter $exporter,
    ) {}

    public function index(): JsonResponse
    {
        $segments = CustomerSegment::query()->latest('id')->get()->map(static fn (CustomerSegment $segment): array => [
            'id' => $segment->id,
            'name' => $segment->name,
            'description' => $segment->description,
            'rules' => $segment->rules,
            'member_count' => $segment->member_count,
            'member_value' => $segment->member_value,
            'computed_at' => $segment->computed_at?->toIso8601String(),
            'owner' => $segment->user?->name,
        ]);

        return ApiResponse::ok([
            'rows' => $segments->all(),
            'fields' => SegmentFieldRegistry::catalogue(),
            'destinations' => collect(SegmentExporter::DESTINATIONS)
                ->map(static fn (array $meta, string $key): array => ['key' => $key, ...$meta])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Counts and samples a rule set without saving it, so the builder can show
     * who is in the segment while it is still being written.
     */
    public function preview(Request $request): JsonResponse
    {
        $rules = $this->rulesFrom($request);
        $problems = $this->builder->problems($rules);

        if ($problems !== []) {
            return ApiResponse::error($problems[0], 422, ['problems' => $problems]);
        }

        $query = $this->builder->query($rules);

        $summary = (clone $query)
            ->selectRaw('COUNT(*) AS members, COALESCE(SUM(total_spent),0) AS value, COALESCE(AVG(aov),0) AS aov')
            ->selectRaw('SUM(CASE WHEN accepts_marketing = 1 THEN 1 ELSE 0 END) AS contactable')
            ->first();

        $unmask = $request->user()->can('pii.unmask.view');

        $sample = (clone $query)
            ->orderByDesc('total_spent')
            ->limit(25)
            ->get()
            ->map(static fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $unmask ? $customer->email_encrypted : $customer->masked_email,
                'city' => $customer->city,
                'state' => $customer->state,
                'orders_count' => $customer->orders_count,
                'total_spent' => $customer->total_spent,
                'rfm_segment' => $customer->rfm_segment?->value,
                'days_since_last_order' => $customer->days_since_last_order,
            ]);

        $members = (int) ($summary->members ?? 0);
        $contactable = (int) ($summary->contactable ?? 0);

        return ApiResponse::ok([
            'member_count' => $members,
            'member_value' => (int) ($summary->value ?? 0),
            'average_aov' => (int) round((float) ($summary->aov ?? 0)),
            'contactable' => $contactable,
            'sample' => $sample->all(),
            'caveat' => $members > 0 && $contactable < $members
                ? sprintf('%d of these %d never opted into marketing, so a Meta, Klaviyo or WhatsApp export will contain %d people.', $members - $contactable, $members, $contactable)
                : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $rules = $this->rulesFrom($request);
        $problems = $this->builder->problems($rules);

        if ($problems !== []) {
            return ApiResponse::error($problems[0], 422, ['problems' => $problems]);
        }

        $segment = CustomerSegment::query()->updateOrCreate(
            ['tenant_id' => Tenant::id(), 'name' => $validated['name']],
            [
                'user_id' => $request->user()->id,
                'description' => $validated['description'] ?? null,
                'rules' => $rules,
            ],
        );

        $this->recount($segment);

        activity('customers')->performedOn($segment)
            ->withProperties(['name' => $segment->name, 'members' => $segment->member_count])
            ->log('segment.saved');

        return ApiResponse::ok([
            'id' => $segment->id,
            'member_count' => $segment->member_count,
        ], message: sprintf('Saved. %s customers match right now.', number_format($segment->member_count)));
    }

    public function destroy(int $segment): JsonResponse
    {
        $model = CustomerSegment::query()->find($segment);

        if ($model === null) {
            return ApiResponse::error('Segment not found.', 404);
        }

        $model->delete();

        return ApiResponse::ok(null, message: 'Segment deleted.');
    }

    /**
     * A segment is a live rule set, so its size is recomputed on demand rather
     * than trusted from whenever it was last saved.
     */
    public function refresh(int $segment): JsonResponse
    {
        $model = CustomerSegment::query()->find($segment);

        if ($model === null) {
            return ApiResponse::error('Segment not found.', 404);
        }

        $this->recount($model);

        return ApiResponse::ok([
            'member_count' => $model->member_count,
            'member_value' => $model->member_value,
            'computed_at' => $model->computed_at?->toIso8601String(),
        ]);
    }

    public function export(Request $request, int $segment, string $destination): StreamedResponse|JsonResponse
    {
        $model = CustomerSegment::query()->find($segment);

        if ($model === null) {
            return ApiResponse::error('Segment not found.', 404);
        }

        if (! array_key_exists($destination, SegmentExporter::DESTINATIONS)) {
            return ApiResponse::error("Unknown destination [{$destination}].", 422);
        }

        $meta = SegmentExporter::DESTINATIONS[$destination];

        // Exporting real contact details is a privacy decision, so it needs the
        // permission that governs unmasked PII.
        if ($meta['pii'] === 'plain' && $request->user()->cannot('pii.unmask.view')) {
            return ApiResponse::error(
                'This export contains real email addresses or phone numbers, which needs the unmasked-PII permission.',
                403,
                ['required_permission' => ['pii.unmask.view']],
            );
        }

        $result = $this->exporter->export($model, $destination, $request->user()->can('pii.unmask.view'));

        activity('customers')->performedOn($model)
            ->withProperties(['destination' => $destination, 'rows' => count($result['rows'])])
            ->log('segment.exported');

        $filename = sprintf('%s-%s-%s.csv', str($model->name)->slug(), $destination, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($result): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $result['headers']);

            foreach ($result['rows'] as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function rulesFrom(Request $request): array
    {
        $request->validate([
            'rules' => ['required', 'array'],
            'rules.match' => ['nullable', 'in:all,any'],
            'rules.conditions' => ['array', 'max:20'],
            'rules.conditions.*.field' => ['required', 'string'],
            'rules.conditions.*.operator' => ['required', 'string'],
        ]);

        return $request->input('rules');
    }

    private function recount(CustomerSegment $segment): void
    {
        $summary = $this->builder->query($segment->rules)
            ->selectRaw('COUNT(*) AS members, COALESCE(SUM(total_spent),0) AS value')
            ->first();

        $segment->forceFill([
            'member_count' => (int) ($summary->members ?? 0),
            'member_value' => (int) ($summary->value ?? 0),
            'computed_at' => now(),
        ])->save();
    }
}

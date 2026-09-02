<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SavedView;
use App\Support\Facades\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Named filter sets per surface. A view is private to the person who made it
 * unless they share it, in which case everyone on the tenant can apply it but
 * only the owner can change it.
 */
class SavedViewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $surface = $request->string('surface')->toString();

        $views = SavedView::query()
            ->when($surface !== '', fn ($query) => $query->where('surface', $surface))
            ->where(fn ($query) => $query->where('user_id', $request->user()->id)->orWhere('is_shared', true))
            ->orderBy('name')
            ->get()
            ->map(fn (SavedView $view): array => $this->present($view, $request));

        return ApiResponse::ok(['rows' => $views->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'surface' => ['required', 'string', 'max:96'],
            'name' => ['required', 'string', 'max:60'],
            'state' => ['required', 'array'],
            'is_shared' => ['boolean'],
        ]);

        $view = SavedView::query()->updateOrCreate(
            [
                'tenant_id' => Tenant::id(),
                'user_id' => $request->user()->id,
                'surface' => $validated['surface'],
                'name' => $validated['name'],
            ],
            [
                'state' => $validated['state'],
                'is_shared' => $validated['is_shared'] ?? false,
            ],
        );

        return ApiResponse::ok($this->present($view, $request), message: 'View saved.');
    }

    public function update(Request $request, int $view): JsonResponse
    {
        $model = SavedView::query()->find($view);

        if ($model === null) {
            return ApiResponse::error('View not found.', 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return ApiResponse::error('Only the person who saved a view can change it.', 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'state' => ['sometimes', 'array'],
            'is_shared' => ['sometimes', 'boolean'],
        ]);

        $model->forceFill($validated)->save();

        return ApiResponse::ok($this->present($model, $request), message: 'View updated.');
    }

    public function destroy(Request $request, int $view): JsonResponse
    {
        $model = SavedView::query()->find($view);

        if ($model === null) {
            return ApiResponse::error('View not found.', 404);
        }

        if ($model->user_id !== $request->user()->id) {
            return ApiResponse::error('Only the person who saved a view can delete it.', 403);
        }

        $model->delete();

        return ApiResponse::ok(null, message: 'View deleted.');
    }

    /** @return array<string, mixed> */
    private function present(SavedView $view, Request $request): array
    {
        return [
            'id' => $view->id,
            'surface' => $view->surface,
            'name' => $view->name,
            'state' => $view->state,
            'is_shared' => $view->is_shared,
            'is_mine' => $view->user_id === $request->user()->id,
            'owner' => $view->user?->name,
        ];
    }
}

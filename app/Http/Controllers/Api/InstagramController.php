<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Marketing\Queries\SocialQuery;
use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstagramController extends Controller
{
    use ResolvesFilters;

    public function __construct(private readonly SocialQuery $social) {}

    public function kpis(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok(
            $this->cached('instagram', 'kpis', $filters, fn (): array => $this->social->kpis($filters)),
            $filters,
            caveat: $this->social->caveat(),
        );
    }

    public function accountTrend(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'account_trend', $filters, fn (): array => $this->social->accountTrend($filters)), $filters);
    }

    public function engagementTrend(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'engagement', $filters, fn (): array => $this->social->engagementTrend($filters)), $filters);
    }

    public function content(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $sort = $request->string('sort', 'reach')->toString();

        return ApiResponse::ok(
            $this->cached('instagram', 'content:'.$sort, $filters, fn (): array => $this->social->content($filters, $sort)),
            $filters,
        );
    }

    public function reelsVsFeed(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'reels_vs_feed', $filters, fn (): array => $this->social->reelsVsFeed($filters)), $filters);
    }

    public function stories(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'stories', $filters, fn (): array => $this->social->stories($filters)), $filters);
    }

    public function audience(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'audience', $filters, fn (): array => $this->social->audience()), $filters);
    }

    public function bestTime(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'best_time', $filters, fn (): array => $this->social->bestTimeToPost($filters)), $filters);
    }

    public function hashtags(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'hashtags', $filters, fn (): array => $this->social->hashtags($filters)), $filters);
    }

    public function salesCorrelation(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'sales_correlation', $filters, fn (): array => $this->social->salesCorrelation($filters)), $filters);
    }

    public function facebookPage(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('instagram', 'fb_page', $filters, fn (): array => $this->social->facebookPage($filters)), $filters);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesFilters;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Facades\Tenant;
use App\Support\Num;
use App\Support\Verdict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    use ResolvesFilters;

    public function trend(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('reviews', 'trend', $filters, function () use ($filters): array {
            $offset = now(Tenant::timezone())->format('P');

            $rows = DB::table('reviews')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('reviewed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->selectRaw("DATE(CONVERT_TZ(reviewed_at, '+00:00', '{$offset}')) AS d")
                ->selectRaw('COUNT(*) AS reviews, AVG(rating) AS avg_rating')
                ->selectRaw('SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) AS positive')
                ->selectRaw('SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) AS negative')
                ->groupBy('d')
                ->get()
                ->keyBy('d');

            return [
                'series' => $filters->period->dateKeys()->map(static fn (string $date): array => [
                    'date' => $date,
                    'reviews' => (int) ($rows[$date]->reviews ?? 0),
                    'avg_rating' => round((float) ($rows[$date]->avg_rating ?? 0), 2),
                    'positive' => (int) ($rows[$date]->positive ?? 0),
                    'negative' => (int) ($rows[$date]->negative ?? 0),
                ])->all(),
            ];
        }), $filters);
    }

    public function topRated(Request $request): JsonResponse
    {
        return $this->ranked($request, 'top');
    }

    public function worstRated(Request $request): JsonResponse
    {
        return $this->ranked($request, 'worst');
    }

    /**
     * Sentiment comes from the LLM analysis job, never from the star rating.
     * Until that job has run these are empty and say so — inferring "negative"
     * from two stars would be inventing data.
     */
    public function sentiment(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('reviews', 'sentiment', $filters, function () use ($filters): array {
            $base = DB::table('reviews')
                ->where('tenant_id', Tenant::id())
                ->whereBetween('reviewed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')]);

            $total = (clone $base)->count();
            $analysed = (clone $base)->whereNotNull('sentiment')->count();

            $rows = (clone $base)
                ->whereNotNull('sentiment')
                ->selectRaw('sentiment, COUNT(*) AS count, AVG(rating) AS avg_rating, AVG(sentiment_score) AS avg_score')
                ->groupBy('sentiment')
                ->get()
                ->map(static fn (object $r): array => [
                    'sentiment' => $r->sentiment,
                    'count' => (int) $r->count,
                    'share_pct' => 0.0,
                    'avg_rating' => round((float) $r->avg_rating, 2),
                    'avg_score' => round((float) $r->avg_score, 3),
                ]);

            return [
                'rows' => $rows->map(static fn (array $row): array => [
                    ...$row,
                    'share_pct' => Num::pct($row['count'], $analysed),
                ])->all(),
                'total' => $total,
                'analysed' => $analysed,
                'pending' => $total - $analysed,
                'caveat' => $analysed === 0
                    ? 'No review has been analysed yet. Sentiment is produced by the LLM analysis job and is never inferred from the star rating, so this stays empty until that runs.'
                    : sprintf('%d of %d reviews analysed. The rest are queued.', $analysed, $total),
            ];
        }), $filters);
    }

    public function themes(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('reviews', 'themes', $filters, function () use ($filters): array {
            $reviews = DB::table('reviews')
                ->where('tenant_id', Tenant::id())
                ->whereNotNull('themes')
                ->whereBetween('reviewed_at', [$filters->period->from->setTimezone('UTC'), $filters->period->to->setTimezone('UTC')])
                ->get(['themes', 'rating', 'sentiment']);

            $tally = [];

            foreach ($reviews as $review) {
                foreach (json_decode((string) $review->themes, true) ?: [] as $theme) {
                    $label = is_array($theme) ? ($theme['label'] ?? null) : $theme;

                    if ($label === null) {
                        continue;
                    }

                    $tally[$label] ??= ['theme' => $label, 'mentions' => 0, 'rating_sum' => 0, 'negative' => 0];
                    $tally[$label]['mentions']++;
                    $tally[$label]['rating_sum'] += (int) $review->rating;
                    $tally[$label]['negative'] += (int) $review->rating <= 2 ? 1 : 0;
                }
            }

            $rows = collect($tally)
                ->map(static fn (array $row): array => [
                    'theme' => $row['theme'],
                    'mentions' => $row['mentions'],
                    'avg_rating' => round($row['rating_sum'] / max($row['mentions'], 1), 2),
                    'negative_share_pct' => Num::pct($row['negative'], $row['mentions']),
                ])
                ->sortByDesc('mentions')
                ->values();

            return [
                'rows' => $rows->all(),
                'caveat' => $rows->isEmpty()
                    ? 'Themes are extracted by the LLM analysis job. Nothing has been analysed yet.'
                    : 'Themes are extracted from review text by an LLM, so wording is normalised across reviews.',
            ];
        }), $filters);
    }

    private function ranked(Request $request, string $direction): JsonResponse
    {
        $filters = $this->filters($request);

        return ApiResponse::ok($this->cached('reviews', 'ranked:'.$direction, $filters, function () use ($direction): array {
            $rows = DB::table('reviews as r')
                ->leftJoin('skus as s', 's.id', '=', 'r.sku_id')
                ->where('r.tenant_id', Tenant::id())
                ->selectRaw('s.id AS sku_id, s.sku_code, COALESCE(s.name, r.product_sku) AS name, s.image_url')
                ->selectRaw('COUNT(*) AS review_count, AVG(r.rating) AS avg_rating')
                ->selectRaw('SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) AS negative')
                ->groupByRaw('s.id, s.sku_code, COALESCE(s.name, r.product_sku), s.image_url')
                // Below three reviews an average is noise, not a signal.
                ->havingRaw('COUNT(*) >= 3')
                ->orderBy('avg_rating', $direction === 'top' ? 'desc' : 'asc')
                ->limit(15)
                ->get()
                ->map(static fn (object $r): array => [
                    'sku_id' => $r->sku_id,
                    'sku_code' => $r->sku_code,
                    'name' => $r->name,
                    'review_count' => (int) $r->review_count,
                    'avg_rating' => round((float) $r->avg_rating, 2),
                    'negative' => (int) $r->negative,
                    'negative_share_pct' => Num::pct((int) $r->negative, (int) $r->review_count),
                ]);

            $worst = $rows->first();

            return [
                'rows' => $rows->all(),
                'caveat' => 'Products with fewer than three reviews are excluded — an average of one review is not a rating.',
                'verdict' => ($direction === 'worst' && $worst !== null && $worst['avg_rating'] < 3.0
                    ? Verdict::bad(
                        sprintf('%s is rated %.1f across %d reviews.', $worst['sku_code'] ?? $worst['name'], $worst['avg_rating'], $worst['review_count']),
                        sprintf('%.0f%% of its reviews are two stars or worse.', $worst['negative_share_pct']),
                        'Read the reviews before reordering — this is usually sizing, quality or a photo that oversells.',
                    )->toArray()
                    : null),
            ];
        }), $filters);
    }
}

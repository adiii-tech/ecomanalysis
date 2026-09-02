<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * AI credit metering.
 *
 * Both the tenant and the individual user have a ceiling; the stricter one
 * wins. Credits are consumed after a successful call, never before — a failed
 * request should not cost the customer anything.
 */
class CreditMeter
{
    public function remaining(User $user, Tenant $tenant): int
    {
        return min($user->aiCreditsRemaining(), $tenant->aiCreditsRemaining());
    }

    public function canSpend(User $user, Tenant $tenant, string $feature = 'chat'): bool
    {
        return $this->remaining($user, $tenant) >= $this->cost($feature);
    }

    public function cost(string $feature): int
    {
        return (int) config("ai.credits.{$feature}", 1);
    }

    public function spend(User $user, Tenant $tenant, string $feature, int $inputTokens = 0, int $outputTokens = 0, ?string $model = null): void
    {
        $credits = $this->cost($feature);

        DB::transaction(function () use ($user, $tenant, $feature, $credits, $inputTokens, $outputTokens, $model): void {
            $user->increment('ai_credits_used', $credits);
            $tenant->increment('ai_credits_used', $credits);

            AiUsageLog::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'feature' => $feature,
                'model' => $model,
                'credits' => $credits,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
            ]);
        });
    }

    /** @return array<string, mixed> */
    public function summary(User $user, Tenant $tenant): array
    {
        return [
            'remaining' => $this->remaining($user, $tenant),
            'user_used' => $user->ai_credits_used,
            'user_limit' => $user->ai_credit_limit,
            'tenant_used' => $tenant->ai_credits_used,
            'tenant_limit' => $tenant->ai_credit_limit,
            'limited_by' => $user->aiCreditsRemaining() <= $tenant->aiCreditsRemaining() ? 'user' : 'tenant',
        ];
    }
}

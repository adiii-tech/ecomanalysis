<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The complete set of things the AI agent may read.
 *
 * Tools are filtered by the asking user's permissions before they are ever
 * shown to the model, so the agent physically cannot answer a question about
 * data that user is not allowed to see — it does not know the tool exists.
 */
class ToolRegistry
{
    /** @var list<class-string<MetricTool>> */
    private const TOOLS = [
        GetKpisTool::class,
        GetChannelBreakdownTool::class,
        GetSkuPerformanceTool::class,
        GetReturnsTool::class,
        GetCampaignsTool::class,
        GetCustomersTool::class,
        GetLossMakersTool::class,
    ];

    /** @return Collection<int, MetricTool> */
    public function all(): Collection
    {
        return collect(self::TOOLS)->map(static fn (string $class): MetricTool => app($class));
    }

    /** @return Collection<int, MetricTool> */
    public function forUser(User $user): Collection
    {
        return $this->all()->filter(static fn (MetricTool $tool): bool => $user->can($tool->permission()))->values();
    }

    public function find(User $user, string $name): ?MetricTool
    {
        return $this->forUser($user)->first(static fn (MetricTool $tool): bool => $tool->name() === $name);
    }

    /**
     * Tool definitions in the shape the Anthropic PHP SDK expects.
     *
     * @return list<array<string, mixed>>
     */
    public function definitionsFor(User $user): array
    {
        return $this->forUser($user)->map(static fn (MetricTool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->schema(),
        ])->all();
    }
}

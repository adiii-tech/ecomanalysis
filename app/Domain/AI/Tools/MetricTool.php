<?php

declare(strict_types=1);

namespace App\Domain\AI\Tools;

use App\Support\WidgetFilters;

/**
 * A read-only metric the AI agent is allowed to call.
 *
 * The model never writes SQL. It picks a tool from this whitelist and supplies
 * typed parameters; the tool runs a query object that already exists behind a
 * dashboard widget, so an answer can never diverge from what the screen shows.
 * Each tool declares the permission it needs, and the registry hides tools the
 * asking user is not allowed to see — so the agent cannot read around RBAC.
 */
interface MetricTool
{
    public function name(): string;

    public function description(): string;

    /** @return array<string, mixed> JSON schema for the tool's parameters. */
    public function schema(): array;

    /** Permission the calling user must hold. */
    public function permission(): string;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(array $input, WidgetFilters $filters): array;
}

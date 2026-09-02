<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Alerts\Services\AlertDispatcher;
use App\Domain\Alerts\Services\RuleEvaluator;
use App\Models\AlertRule;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class EvaluateAlertRules extends Command
{
    protected $signature = 'alerts:evaluate {--tenant= : Limit to one tenant}';

    protected $description = 'Evaluate every active alert rule and raise events for breaches';

    public function handle(TenantContext $context, RuleEvaluator $evaluator, AlertDispatcher $dispatcher): int
    {
        $fired = 0;

        Tenant::query()
            ->when($this->option('tenant'), fn ($q) => $q->whereKey($this->option('tenant')))
            ->each(function (Tenant $tenant) use ($context, $evaluator, $dispatcher, &$fired): void {
                $context->runAs($tenant, function () use ($evaluator, $dispatcher, $tenant, &$fired): void {
                    $rules = AlertRule::query()
                        ->where('is_active', true)
                        ->where(fn ($q) => $q->where('is_muted', false)->orWhere('muted_until', '<', now()))
                        ->get();

                    foreach ($rules as $rule) {
                        foreach ($evaluator->evaluate($rule) as $event) {
                            $fired++;

                            // Raising an event is not the same as telling anyone.
                            $delivered = $dispatcher->dispatch($event, $tenant);
                            $summary = collect($delivered->delivery_status ?? [])
                                ->map(fn (array $status, string $channel): string => $channel.'='.($status['delivered'] ? 'ok' : ($status['skipped'] ? 'skipped' : 'failed')))
                                ->implode(' ');

                            $this->line("  <fg=yellow>fired</> {$event->title}  <fg=gray>{$summary}</>");
                        }
                    }
                });
            });

        $this->info("Raised {$fired} alert".($fired === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}

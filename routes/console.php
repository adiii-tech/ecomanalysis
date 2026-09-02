<?php

declare(strict_types=1);

use App\Domain\Rollups\Jobs\RebuildRollups;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schedule;

/*
| Connector cadence is defined per entity on each driver (orders every 15 min,
| ads hourly, inventory every 30 min, reviews every 6 hours). This command runs
| often and queues only what is actually due, so adding a connector needs no
| change here.
|
| Requires `php artisan schedule:work` (or a cron entry) plus a queue worker.
*/
Schedule::command('connectors:schedule')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Nightly full recompute, so a missed sync or a cost-settings change cannot
// leave the rollups permanently drifted from the raw orders.
Schedule::call(function (): void {
    Tenant::query()->each(fn (Tenant $tenant) => RebuildRollups::dispatch($tenant->id, full: true));
})->dailyAt('02:30')->name('rollups:nightly')->withoutOverlapping();

// Alert rules read the same rollups the dashboard does, so they only need
// to run after a sync has plausibly landed.
Schedule::command('alerts:evaluate')->hourly()->withoutOverlapping();

// Scheduled report emails. Each schedule picks its own hour in its tenant's
// timezone, so this only needs to look once an hour.
Schedule::command('reports:deliver')->hourly()->withoutOverlapping();

// Standing digests: morning brief, weekly review, monthly P&L. Each tenant
// picks its own hour, so this checks hourly and queues only what is due.
Schedule::command('digests:send')->hourly()->withoutOverlapping();

Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('activitylog:clean')->daily();

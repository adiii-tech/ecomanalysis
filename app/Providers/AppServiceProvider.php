<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Connectors\ConnectorRegistry;
use App\Domain\Connectors\QueueNames;
use App\Domain\Sales\Services\CostResolver;
use App\Support\MetricCache;
use App\Support\ResponseMeta;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request: notes gathered anywhere reach the response envelope.
        $this->app->scoped(ResponseMeta::class);

        // The tenant scope, the permission team id and every scoped query read
        // from one instance per request/job — never a fresh one.
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(ConnectorRegistry::class);
        $this->app->singleton(MetricCache::class);
        $this->app->scoped(CostResolver::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();
        Date::use(CarbonImmutable::class);
        Vite::prefetch(concurrency: 3);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // `php artisan dev` runs serve + queue + vite by default. Connector
        // syncs are queued on a cadence, so the scheduler has to run too or
        // nothing ever syncs on its own in local development.
        if ($this->app->runningInConsole() && ! $this->app->isProduction()) {
            DevCommands::artisan('schedule:work', 'scheduler');

            // The default dev worker only listens on `default`, so connector
            // jobs would queue and never run.
            DevCommands::artisan('queue:listen --tries=1 --timeout=0 --queue='.QueueNames::asList(), 'sync-queue');
        }

        RateLimiter::for('connector-sync', fn () => Limit::perMinute(30));
        RateLimiter::for('manual-sync', fn ($request) => Limit::perMinute(6)->by((string) $request->user()?->tenant_id));
        RateLimiter::for('ai', fn ($request) => Limit::perMinute(20)->by((string) $request->user()?->id));
        RateLimiter::for('api', fn ($request) => Limit::perMinute(300)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('exports', fn ($request) => Limit::perMinute(10)->by((string) $request->user()?->id));
    }
}

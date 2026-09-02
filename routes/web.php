<?php

declare(strict_types=1);

use App\Http\Controllers\OAuthController;
use App\Http\Controllers\SharedAnswerController;
use App\Http\Controllers\SharedReportController;
use App\Http\Controllers\WebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

/*
| Webhooks are unauthenticated by nature — they carry an HMAC signature that the
| controller verifies against the shop's stored secret instead of a session.
*/
Route::post('webhooks/shopify', [WebhookController::class, 'shopify'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->name('webhooks.shopify');

/**
 * Every page is gated on its module's entry permission, so a role that cannot
 * see a module never reaches its page — the sidebar hides the link too.
 */
/*
| OAuth redirects. The outbound leg needs a session; the callback is authorised
| by its single-use state, because a provider may return the browser without
| the session cookie attached.
*/
Route::middleware(['auth'])
    ->get('connectors/oauth/{connector}/redirect', [OAuthController::class, 'redirect'])
    ->name('connectors.oauth.redirect');

Route::get('connectors/oauth/{connector}/callback', [OAuthController::class, 'callback'])
    ->name('connectors.oauth.callback');

// A shared AI answer is a revocable, read-only snapshot — no session required.
Route::get('ask-ai/shared/{token}', [SharedAnswerController::class, 'show'])->name('ai.shared');

Route::middleware(['auth'])->group(function (): void {
    $pages = [
        'dashboard' => ['dashboard/index', 'dashboard.kpi_strip.view'],
        'finance' => ['finance/index', 'finance.kpi_strip.view'],
        'marketing' => ['marketing/index', 'marketing.kpi_strip.view'],
        'instagram' => ['instagram/index', 'instagram.kpi_strip.view'],
        'marketplace' => ['marketplace/index', 'marketplace.kpi_strip.view'],
        'operations' => ['operations/index', 'operations.kpi_strip.view'],
        'catalog' => ['catalog/index', 'catalog.kpi_strip.view'],
        'customers' => ['customers/index', 'customer_intelligence.kpi_strip.view'],
        'reports' => ['reports/index', 'reports.library.view'],
        'ask-ai' => ['ai/index', 'ai.chat.view'],
        'alerts' => ['alerts/index', 'alerts.events.view'],
        'connectors' => ['connectors/index', 'connectors.index.view'],
    ];

    foreach ($pages as $path => [$component, $permission]) {
        Route::get($path, fn () => Inertia::render($component))
            ->middleware("permission.widget:{$permission}")
            ->name(str_replace('/', '.', $path));
    }

    Route::get('customers/explorer', fn () => Inertia::render('customers/explorer'))
        ->middleware('permission.widget:customer_intelligence.explorer.view')
        ->name('customers.explorer');

    Route::get('customers/{customer}', fn (int $customer) => Inertia::render('customers/show', ['customerId' => $customer]))
        ->middleware('permission.widget:customer_intelligence.customer_360.view')
        ->whereNumber('customer')
        ->name('customers.show');

    Route::get('reports/{report}', fn (string $report) => Inertia::render('reports/show', ['reportKey' => $report]))
        ->middleware('permission.widget:reports.library.view')
        ->name('reports.show');

    Route::get('admin/users', fn () => Inertia::render('admin/users'))
        ->middleware('permission.widget:admin.users.view')
        ->name('admin.users');

    Route::get('settings/profile', fn () => Inertia::render('settings/profile'))
        ->name('settings.profile');

    Route::get('onboarding', fn () => Inertia::render('onboarding/index'))
        ->name('onboarding');
});

/*
| Public, read-only report snapshots. No auth: the token is the credential, and
| it carries the exact filters the sender froze into it.
*/
Route::get('shared/reports/{token}', [SharedReportController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('reports.shared');

<?php

declare(strict_types=1);

use App\Domain\AI\Services\ClaudeClient;
use App\Models\AiSetting;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->admin = $this->userFor($this->tenant);
});

it('lets a tenant save their own Anthropic key and model, and never reads the key back', function (): void {
    $this->actingAs($this->admin)->putJson('/api/admin/settings', [
        'ai_settings' => ['api_key' => 'sk-ant-tenant-secret', 'model' => 'claude-sonnet-5'],
    ])->assertOk();

    $stored = DB::table('ai_settings')->where('tenant_id', $this->tenant->id)->value('api_key');
    expect($stored)->not->toContain('sk-ant-tenant-secret');

    $response = $this->actingAs($this->admin)->getJson('/api/admin/settings');

    expect(json_encode($response->json()))->not->toContain('sk-ant-tenant-secret')
        ->and($response->json('data.ai_settings.api_key_set'))->toBeTrue()
        ->and($response->json('data.ai_settings.model'))->toBe('claude-sonnet-5');
});

it('keeps an existing Anthropic key when the field is left blank', function (): void {
    AiSetting::query()->create(['tenant_id' => $this->tenant->id, 'api_key' => 'sk-ant-original']);

    $this->actingAs($this->admin)->putJson('/api/admin/settings', [
        'ai_settings' => ['model' => 'claude-opus-5', 'api_key' => null],
    ])->assertOk();

    $setting = AiSetting::query()->where('tenant_id', $this->tenant->id)->first();

    expect($setting->api_key)->toBe('sk-ant-original')
        ->and($setting->model)->toBe('claude-opus-5');
});

it('reports the system key as the fallback when a tenant has not set their own', function (): void {
    config(['ai.api_key' => 'sk-ant-system', 'ai.model' => 'claude-opus-5']);

    $payload = $this->actingAs($this->admin)->getJson('/api/admin/settings')->assertOk()->json('data.ai_settings');

    expect($payload['api_key_set'])->toBeFalse()
        ->and($payload['system_key_configured'])->toBeTrue()
        ->and($payload['system_model_default'])->toBe('claude-opus-5');
});

it('will not show or save AI settings to a role without admin access', function (): void {
    $viewer = $this->userFor($this->tenant, ['dashboard.kpi_strip.view']);

    $this->actingAs($viewer)->getJson('/api/admin/settings')->assertForbidden();

    $this->actingAs($viewer)
        ->putJson('/api/admin/settings', ['ai_settings' => ['model' => 'claude-opus-5']])
        ->assertForbidden();
});

it("prefers a tenant's own key and model over the system-wide config", function (): void {
    config(['ai.api_key' => 'sk-ant-system', 'ai.model' => 'claude-opus-5']);
    AiSetting::query()->create([
        'tenant_id' => $this->tenant->id, 'api_key' => 'sk-ant-tenant', 'model' => 'claude-sonnet-5',
    ]);

    app(TenantContext::class)->set($this->tenant);
    $client = app(ClaudeClient::class);

    expect($client->isConfigured())->toBeTrue()
        ->and($client->model())->toBe('claude-sonnet-5');
});

it('falls back to the system key and model once a tenant row exists but sets neither', function (): void {
    config(['ai.api_key' => 'sk-ant-system', 'ai.model' => 'claude-opus-5']);
    AiSetting::query()->create(['tenant_id' => $this->tenant->id]);

    app(TenantContext::class)->set($this->tenant);
    $client = app(ClaudeClient::class);

    expect($client->isConfigured())->toBeTrue()
        ->and($client->model())->toBe('claude-opus-5');
});

it("never resolves another tenant's key when no tenant is in context", function (): void {
    // TenantScope silently stops filtering once no tenant is resolved, so
    // without the explicit TenantContext::has() guard in ClaudeClient this
    // would hand back whichever tenant's row the query happened to find
    // first — a real cross-tenant key leak, not just a missing fallback.
    config(['ai.api_key' => null]);
    AiSetting::query()->create(['tenant_id' => $this->tenant->id, 'api_key' => 'sk-ant-tenant']);

    app(TenantContext::class)->set(null);
    $client = app(ClaudeClient::class);

    expect($client->isConfigured())->toBeFalse();
});

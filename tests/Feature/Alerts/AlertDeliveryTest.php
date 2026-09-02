<?php

declare(strict_types=1);

use App\Domain\Alerts\Services\AlertDispatcher;
use App\Mail\AlertMail;
use App\Models\AlertEvent;
use App\Models\AlertRule;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->tenant = $this->tenant();
    $this->user = $this->userFor($this->tenant);
});

function raiseEvent(array $channels): AlertEvent
{
    $rule = AlertRule::query()->create([
        'tenant_id' => test()->tenant->id,
        'created_by' => test()->user->id,
        'name' => 'RTO watch',
        'metric' => 'rto_rate',
        'operator' => 'gt',
        'threshold' => 15,
        'window_days' => 7,
        'channels' => $channels,
    ]);

    return AlertEvent::query()->create([
        'tenant_id' => test()->tenant->id,
        'alert_rule_id' => $rule->id,
        'title' => 'RTO in Bihar is 31.2%',
        'body' => 'Above your 15% threshold across 42 orders in the last 7 days.',
        'severity' => 'critical',
        'observed_value' => 31.2,
        'threshold' => 15,
        'dimension_value' => 'Bihar',
    ]);
}

it('emails an alert to the configured recipients', function (): void {
    Mail::fake();

    NotificationSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'email_recipients' => ['ops@brand.test'],
    ]);

    $event = app(AlertDispatcher::class)->dispatch(raiseEvent(['email']), $this->tenant);

    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => $mail->hasTo('ops@brand.test'));

    expect($event->delivery_status['email']['delivered'])->toBeTrue();
});

it('falls back to every active user when no recipients are set', function (): void {
    Mail::fake();

    app(AlertDispatcher::class)->dispatch(raiseEvent(['email']), $this->tenant);

    Mail::assertSent(AlertMail::class, fn (AlertMail $mail): bool => $mail->hasTo($this->user->email));
});

it('posts an alert to Slack when a webhook is configured', function (): void {
    Http::fake(['hooks.slack.com/*' => Http::response(['ok' => true])]);

    NotificationSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/xyz',
    ]);

    $event = app(AlertDispatcher::class)->dispatch(raiseEvent(['slack']), $this->tenant);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'hooks.slack.com')
        && str_contains(json_encode($request->data()), 'RTO in Bihar'));

    expect($event->delivery_status['slack']['delivered'])->toBeTrue();
});

it('records a channel as skipped rather than delivered when it is not configured', function (): void {
    Http::fake();

    $event = app(AlertDispatcher::class)->dispatch(raiseEvent(['slack', 'whatsapp']), $this->tenant);

    // Silence is the worst outcome for an alert, so an unconfigured channel is
    // recorded as skipped and never as sent.
    expect($event->delivery_status['slack']['delivered'])->toBeFalse()
        ->and($event->delivery_status['slack']['skipped'])->toBeTrue()
        ->and($event->delivery_status['whatsapp']['skipped'])->toBeTrue()
        ->and($event->delivery_status['slack']['message'])->toContain('Slack webhook');

    Http::assertNothingSent();
});

it('sends a WhatsApp template message through the Cloud API', function (): void {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    NotificationSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'whatsapp_phone_number_id' => '123456',
        'whatsapp_token' => 'secret-token',
        'whatsapp_template' => 'analytics_alert',
        'whatsapp_recipients' => ['919812345678'],
    ]);

    $event = app(AlertDispatcher::class)->dispatch(raiseEvent(['whatsapp']), $this->tenant);

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), 'graph.facebook.com/v21.0/123456/messages')
            && $request['template']['name'] === 'analytics_alert'
            && $request->hasHeader('Authorization', 'Bearer secret-token');
    });

    expect($event->delivery_status['whatsapp']['delivered'])->toBeTrue();
});

it('keeps the WhatsApp token encrypted at rest and out of the API', function (): void {
    NotificationSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'whatsapp_token' => 'secret-token',
    ]);

    $stored = DB::table('notification_settings')->where('tenant_id', $this->tenant->id)->value('whatsapp_token');

    expect($stored)->not->toContain('secret-token');

    $response = $this->actingAs($this->user)->getJson('/api/admin/settings');

    expect(json_encode($response->json()))->not->toContain('secret-token')
        ->and($response->json('data.notifications.whatsapp_token_set'))->toBeTrue();
});

it('keeps an existing WhatsApp token when the field is left blank', function (): void {
    NotificationSetting::query()->create(['tenant_id' => $this->tenant->id, 'whatsapp_token' => 'secret-token']);

    $this->actingAs($this->user)->putJson('/api/admin/settings', [
        'notifications' => ['whatsapp_template' => 'renamed', 'whatsapp_token' => null],
    ])->assertOk();

    $settings = NotificationSetting::query()->where('tenant_id', $this->tenant->id)->first();

    expect($settings->whatsapp_token)->toBe('secret-token')
        ->and($settings->whatsapp_template)->toBe('renamed');
});

it('marks an in-app alert as delivered because the event itself is the notification', function (): void {
    $event = app(AlertDispatcher::class)->dispatch(raiseEvent(['in_app']), $this->tenant);

    expect($event->delivery_status['in_app']['delivered'])->toBeTrue();
});

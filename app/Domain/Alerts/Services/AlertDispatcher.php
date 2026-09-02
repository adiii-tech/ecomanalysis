<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Services;

use App\Mail\AlertMail;
use App\Models\AlertEvent;
use App\Models\NotificationSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Delivers a raised alert on the channels its rule asked for.
 *
 * Every attempt is recorded on the event, including the ones that could not be
 * made: a channel with nothing configured is stored as `skipped`, never as
 * sent. An alert that silently went nowhere is worse than no alert at all.
 */
class AlertDispatcher
{
    public function dispatch(AlertEvent $event, Tenant $tenant): AlertEvent
    {
        $channels = $event->rule?->channels ?? ['in_app'];
        $settings = NotificationSetting::query()->firstOrNew(['tenant_id' => $tenant->id]);
        $status = [];

        foreach ($channels as $channel) {
            $status[$channel] = match ($channel) {
                // The event row is the in-app notification; storing it is the delivery.
                'in_app' => $this->result(true, 'Shown in the notification centre.'),
                'email' => $this->email($event, $tenant, $settings),
                'slack' => $this->slack($event, $settings),
                'whatsapp' => $this->whatsapp($event, $settings),
                default => $this->result(false, "Unknown channel [{$channel}]."),
            };
        }

        $event->forceFill(['delivery_status' => $status])->save();

        return $event;
    }

    /** @return array<string, mixed> */
    private function email(AlertEvent $event, Tenant $tenant, NotificationSetting $settings): array
    {
        $recipients = $settings->email_recipients ?: $this->defaultRecipients($tenant);

        if ($recipients === []) {
            return $this->result(false, 'No email recipients configured for this tenant.', skipped: true);
        }

        try {
            Mail::to($recipients)->send(new AlertMail($event, $tenant->name));

            return $this->result(true, sprintf('Emailed %d recipient(s).', count($recipients)));
        } catch (Throwable $exception) {
            Log::error('Alert email failed.', ['event' => $event->id, 'error' => $exception->getMessage()]);

            return $this->result(false, 'Email failed: '.$exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function slack(AlertEvent $event, NotificationSetting $settings): array
    {
        if (! $settings->hasSlack()) {
            return $this->result(false, 'No Slack webhook configured in Admin → Settings.', skipped: true);
        }

        try {
            $response = Http::timeout(10)->post($settings->slack_webhook_url, [
                'text' => sprintf('*%s*', $event->title),
                'blocks' => [
                    ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => mb_substr($event->title, 0, 150)]],
                    ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $event->body]],
                    ['type' => 'context', 'elements' => [
                        ['type' => 'mrkdwn', 'text' => sprintf('%s · <%s|Open alerts>', ucfirst($event->severity), url('/alerts'))],
                    ]],
                ],
            ]);

            return $response->successful()
                ? $this->result(true, 'Posted to Slack.')
                : $this->result(false, 'Slack rejected the message: '.$response->status());
        } catch (Throwable $exception) {
            return $this->result(false, 'Slack failed: '.$exception->getMessage());
        }
    }

    /**
     * WhatsApp Cloud API. Business-initiated messages must use an approved
     * template, so the tenant supplies the template name and this sends the
     * alert title and body as its two parameters.
     *
     * @return array<string, mixed>
     */
    private function whatsapp(AlertEvent $event, NotificationSetting $settings): array
    {
        if (! $settings->hasWhatsapp()) {
            return $this->result(false, 'WhatsApp Cloud API is not configured in Admin → Settings.', skipped: true);
        }

        $template = $settings->whatsapp_template ?: 'analytics_alert';
        $sent = 0;
        $errors = [];

        foreach ($settings->whatsapp_recipients as $number) {
            try {
                $response = Http::timeout(10)
                    ->withToken($settings->whatsapp_token)
                    ->post(sprintf('https://graph.facebook.com/v21.0/%s/messages', $settings->whatsapp_phone_number_id), [
                        'messaging_product' => 'whatsapp',
                        'to' => $number,
                        'type' => 'template',
                        'template' => [
                            'name' => $template,
                            'language' => ['code' => 'en'],
                            'components' => [[
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => mb_substr($event->title, 0, 200)],
                                    ['type' => 'text', 'text' => mb_substr($event->body, 0, 500)],
                                ],
                            ]],
                        ],
                    ]);

                $response->successful() ? $sent++ : $errors[] = $response->json('error.message') ?? (string) $response->status();
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return $sent > 0
            ? $this->result(true, sprintf('Sent to %d number(s).%s', $sent, $errors === [] ? '' : ' Some failed: '.implode('; ', $errors)))
            : $this->result(false, 'WhatsApp failed: '.implode('; ', $errors ?: ['no recipients']));
    }

    /** @return list<string> */
    private function defaultRecipients(Tenant $tenant): array
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->pluck('email')
            ->all();
    }

    /** @return array<string, mixed> */
    private function result(bool $delivered, string $message, bool $skipped = false): array
    {
        return [
            'delivered' => $delivered,
            'skipped' => $skipped,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ];
    }
}

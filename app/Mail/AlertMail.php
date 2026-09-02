<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AlertEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AlertEvent $event,
        public readonly string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[%s] %s', strtoupper($this->event->severity), $this->event->title),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.alert', with: [
            'tenantName' => $this->tenantName,
            'title' => $this->event->title,
            'body' => $this->event->body,
            'severity' => $this->event->severity,
            'rule' => $this->event->rule?->name,
            'url' => url('/alerts'),
        ]);
    }
}

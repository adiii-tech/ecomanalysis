<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // Where alerts go. A channel with nothing configured is reported as
            // skipped rather than silently swallowed.
            $table->json('email_recipients')->nullable();
            $table->string('slack_webhook_url', 512)->nullable();
            $table->string('whatsapp_phone_number_id', 64)->nullable();
            $table->text('whatsapp_token')->nullable();
            $table->string('whatsapp_template', 96)->nullable();
            $table->json('whatsapp_recipients')->nullable();

            // Digests: the morning brief, the weekly review, the monthly P&L.
            $table->json('daily_brief_recipients')->nullable();
            $table->unsignedTinyInteger('daily_brief_hour')->default(8);
            $table->json('weekly_review_recipients')->nullable();
            $table->unsignedTinyInteger('weekly_review_day')->default(1);
            $table->json('monthly_pnl_recipients')->nullable();
            $table->timestamp('daily_brief_sent_at')->nullable();
            $table->timestamp('weekly_review_sent_at')->nullable();
            $table->timestamp('monthly_pnl_sent_at')->nullable();

            $table->timestamps();
            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};

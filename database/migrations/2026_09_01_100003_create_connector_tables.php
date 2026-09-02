<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('connector_id', 64);
            $table->string('status', 24)->default('disconnected');
            $table->string('auth_type', 24)->default('token');
            $table->text('credentials')->nullable();
            $table->string('account_label')->nullable();
            $table->boolean('has_secret')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('sync_cursor')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'connector_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('connector_id', 64);
            $table->string('entity', 64);
            $table->string('status', 24)->default('queued');
            $table->string('trigger', 24)->default('scheduled');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_upserted')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->json('cursor_before')->nullable();
            $table->json('cursor_after')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'connector_id', 'started_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('connector_id', 64);
            $table->string('topic', 128);
            $table->string('external_id')->nullable();
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['connector_id', 'topic', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('connectors');
    }
};

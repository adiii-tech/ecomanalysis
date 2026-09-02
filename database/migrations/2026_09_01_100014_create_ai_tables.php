<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New chat');
            $table->string('share_token', 64)->nullable()->unique();
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'user_id', 'last_message_at'], 'acs_tenant_user_last');
        });

        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('model', 64)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'ai_chat_session_id'], 'acm_tenant_session');
        });

        Schema::create('ai_insight_cache', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('cache_key', 191);
            $table->string('widget_key', 96);
            $table->longText('content');
            $table->json('payload_digest')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'cache_key'], 'aic_unique_idx');
            $table->index(['tenant_id', 'widget_key'], 'aic_tenant_widget');
        });

        Schema::create('ai_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24)->default('insight');
            $table->string('category', 48)->nullable();
            $table->string('severity', 16)->default('info');
            $table->string('title');
            $table->text('body');
            $table->bigInteger('impact_amount')->default(0);
            $table->unsignedTinyInteger('rank_score')->default(0);
            $table->json('evidence')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind', 'rank_score'], 'ai_tenant_kind_rank');
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 48);
            $table->string('model', 64)->nullable();
            $table->unsignedInteger('credits')->default(1);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'aul_tenant_created');
        });

        Schema::create('metric_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric', 64);
            $table->string('dimension', 96)->nullable();
            $table->decimal('value', 20, 4)->default(0);
            $table->decimal('expected', 20, 4)->default(0);
            $table->decimal('z_score', 10, 4)->default(0);
            $table->string('direction', 8)->default('up');
            $table->string('severity', 16)->default('info');
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'metric', 'dimension'], 'ma_unique_idx');
            $table->index(['tenant_id', 'date'], 'ma_tenant_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_anomalies');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_insights');
        Schema::dropIfExists('ai_insight_cache');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('metric', 64);
            $table->string('dimension', 48)->nullable();
            $table->string('operator', 16)->default('gt');
            $table->decimal('threshold', 20, 4)->default(0);
            $table->unsignedSmallInteger('window_days')->default(7);
            $table->string('scope', 32)->default('global');
            $table->json('scope_values')->nullable();
            $table->json('channels')->nullable();
            $table->string('frequency', 24)->default('daily');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('alert_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('severity', 16)->default('warning');
            $table->decimal('observed_value', 20, 4)->default(0);
            $table->decimal('threshold', 20, 4)->default(0);
            $table->string('dimension_value', 96)->nullable();
            $table->json('delivery_status')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'read_at']);
        });

        Schema::create('report_favourites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 96);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'report_key'], 'rf_unique_idx');
        });

        Schema::create('report_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 96);
            $table->string('token', 64)->unique();
            $table->json('filters')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });

        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 96);
            $table->string('cadence', 24)->default('weekly');
            $table->unsignedTinyInteger('hour')->default(8);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->json('recipients');
            $table->string('format', 16)->default('pdf');
            $table->json('filters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('surface', 96);
            $table->string('name');
            $table->json('state');
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'surface']);
        });

        Schema::create('customer_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('rules');
            $table->unsignedInteger('member_count')->default(0);
            $table->bigInteger('member_value')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_shares');
        Schema::dropIfExists('report_favourites');
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_rules');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name');
            $table->string('currency', 3)->default('INR');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name');
            $table->string('objective', 48)->nullable();
            $table->string('status', 24)->default('active');
            $table->bigInteger('daily_budget')->default(0);
            $table->bigInteger('lifetime_budget')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
            $table->index(['tenant_id', 'platform', 'status']);
        });

        Schema::create('ad_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name');
            $table->string('status', 24)->default('active');
            $table->bigInteger('daily_budget')->default(0);
            $table->json('targeting')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
        });

        Schema::create('ads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_set_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
        });

        Schema::create('ad_creatives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('format', 24)->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->text('body')->nullable();
            $table->string('title')->nullable();
            $table->string('call_to_action', 48)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
        });

        Schema::create('ad_insights_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('platform', 32);
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_set_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('breakdown_key', 32)->default('total');
            $table->string('placement', 48)->nullable();
            $table->string('age', 24)->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('state')->nullable();
            $table->bigInteger('spend')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->bigInteger('conversion_value')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('checkouts')->default(0);
            $table->unsignedBigInteger('video_views_3s')->default(0);
            $table->unsignedBigInteger('video_views_thruplay')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date', 'platform'], 'adid_tenant_date_platform');
            $table->index(['tenant_id', 'campaign_id', 'date'], 'adid_tenant_campaign_date');
            $table->index(['tenant_id', 'breakdown_key', 'date'], 'adid_tenant_breakdown_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_insights_daily');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_sets');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('ad_accounts');
    }
};

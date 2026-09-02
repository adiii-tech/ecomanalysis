<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('external_id');
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('follows_count')->default(0);
            $table->unsignedBigInteger('media_count')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'external_id']);
        });

        Schema::create('social_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 32);
            $table->string('media_id');
            $table->string('type', 24)->default('post');
            $table->text('caption')->nullable();
            $table->string('permalink')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamp('published_at');
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('saves')->default(0);
            $table->unsignedBigInteger('replies')->default(0);
            $table->decimal('avg_watch_time', 8, 2)->default(0);
            $table->decimal('engagement_rate', 6, 3)->default(0);
            $table->json('hashtags')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'platform', 'media_id']);
            $table->index(['tenant_id', 'published_at']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('social_account_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('platform', 32);
            $table->unsignedBigInteger('followers')->default(0);
            $table->integer('new_followers')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('profile_views')->default(0);
            $table->unsignedBigInteger('website_clicks')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'social_account_id', 'date'], 'sad_unique_idx');
            $table->index(['tenant_id', 'date']);
        });

        Schema::create('social_audience_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('dimension', 24);
            $table->string('bucket', 128);
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date', 'dimension'], 'saud_tenant_date_dim');
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('judgeme');
            $table->string('external_id');
            $table->string('product_sku')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('reviewer')->nullable();
            $table->boolean('verified')->default(false);
            $table->boolean('has_photos')->default(false);
            $table->string('sentiment', 16)->nullable();
            $table->decimal('sentiment_score', 5, 3)->nullable();
            $table->json('themes')->nullable();
            $table->timestamp('analysed_at')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'reviewed_at']);
            $table->index(['tenant_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('social_audience_daily');
        Schema::dropIfExists('social_account_daily');
        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('social_accounts');
    }
};

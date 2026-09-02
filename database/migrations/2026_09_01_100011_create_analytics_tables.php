<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('channel_group', 64)->default('Direct');
            $table->string('source', 128)->nullable();
            $table->string('medium', 128)->nullable();
            $table->string('campaign', 191)->nullable();
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            $table->unsignedInteger('engaged_sessions')->default(0);
            $table->decimal('bounce_rate', 6, 3)->default(0);
            $table->unsignedInteger('item_views')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('checkouts')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'channel_group', 'date']);
        });

        Schema::create('analytics_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('page_path', 512);
            $table->string('page_title')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('events')->default(0);
            $table->decimal('avg_time_seconds', 8, 2)->default(0);
            $table->decimal('bounce_rate', 6, 3)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        Schema::create('analytics_cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country', 64)->nullable();
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        Schema::create('analytics_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('sku_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_id')->nullable();
            $table->string('item_name')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('checkouts')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        Schema::create('analytics_demographics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('age', 24)->nullable();
            $table->string('gender', 16)->nullable();
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
        });

        Schema::create('analytics_realtime', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedInteger('active_users')->default(0);
            $table->json('by_country')->nullable();
            $table->json('by_page')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_realtime');
        Schema::dropIfExists('analytics_demographics');
        Schema::dropIfExists('analytics_products');
        Schema::dropIfExists('analytics_cities');
        Schema::dropIfExists('analytics_pages');
        Schema::dropIfExists('analytics_daily');
    }
};

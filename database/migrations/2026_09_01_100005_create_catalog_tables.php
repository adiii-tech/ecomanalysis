<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->string('handle')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('brand')->nullable();
            $table->string('product_type')->nullable();
            $table->string('status', 24)->default('active');
            $table->string('image_url')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'category']);
        });

        Schema::create('skus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id')->nullable();
            $table->string('sku_code');
            $table->string('name');
            $table->string('variant_title')->nullable();
            $table->string('category')->nullable();
            $table->string('subcategory')->nullable();
            $table->string('brand')->nullable();
            $table->bigInteger('mrp')->default(0);
            $table->bigInteger('selling_price')->default(0);
            $table->bigInteger('cost_price')->default(0);
            $table->unsignedInteger('weight_grams')->default(0);
            $table->string('hsn', 16)->nullable();
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->string('barcode')->nullable();
            $table->boolean('is_combo')->default(false);
            $table->json('combo_children')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'sku_code']);
            $table->index(['tenant_id', 'category']);
        });

        Schema::create('sku_cost_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('cost_price');
            $table->date('effective_from');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sku_id', 'effective_from']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
        });

        Schema::create('inventory', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);
            $table->integer('available')->default(0);
            $table->integer('incoming')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku_id', 'location_id', 'source'], 'inventory_unique_idx');
            $table->index(['tenant_id', 'available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('sku_cost_history');
        Schema::dropIfExists('skus');
        Schema::dropIfExists('products');
    }
};

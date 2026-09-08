<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch tracking and bundles — the two things that make stock in beauty, food
 * and combo-selling brands behave differently from a simple count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_code', 64);
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->nullable();

            // Quantity left in this batch, and what this batch actually cost —
            // which is what makes FIFO valuation possible at all.
            $table->integer('quantity')->default(0);
            $table->integer('quantity_received')->default(0);
            $table->bigInteger('unit_cost')->default(0);
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->unique(['tenant_id', 'sku_id', 'location_id', 'batch_code'], 'batch_unique_idx');
            $table->index(['tenant_id', 'expires_on']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('stock_batch_id')->nullable()->after('location_id')->constrained()->nullOnDelete();
        });

        Schema::table('skus', function (Blueprint $table): void {
            // Shelf life drives the expiry warning when a batch has no date.
            $table->unsignedSmallInteger('shelf_life_days')->nullable()->after('tracks_inventory');
            $table->boolean('tracks_batches')->default(false)->after('shelf_life_days');
        });

        // A bundle's components. The JSON column that shipped earlier was never
        // queryable; availability has to be computed across components, so it
        // needs to be a real relation.
        Schema::create('sku_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_sku_id')->constrained('skus')->cascadeOnDelete();
            $table->foreignId('child_sku_id')->constrained('skus')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['parent_sku_id', 'child_sku_id'], 'sku_component_unique');
            $table->index(['tenant_id', 'parent_sku_id'], 'sku_component_tenant_parent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_components');

        Schema::table('skus', function (Blueprint $table): void {
            $table->dropColumn(['shelf_life_days', 'tracks_batches']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['stock_batch_id']);
            $table->dropColumn('stock_batch_id');
        });

        Schema::dropIfExists('stock_batches');
    }
};

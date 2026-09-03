<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns inventory from a read-only mirror of the sales channel into something a
 * brand actually operates: stock they own, movements that explain every change,
 * and the master data behind both.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every change to stock, ever. The inventory row is the running balance;
        // this is the audit trail that explains how it got there. Rows are
        // append-only — a mistake is corrected with another movement, never by
        // editing history.
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->bigInteger('unit_cost')->default(0);
            $table->string('reason', 64)->nullable();
            $table->text('note')->nullable();

            // What caused it: a purchase order, an order, a count, a transfer.
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['tenant_id', 'sku_id', 'happened_at'], 'sm_tenant_sku_time');
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('gstin', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->unsignedSmallInteger('lead_time_days')->default(7);
            $table->unsignedSmallInteger('payment_terms_days')->default(30);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('po_number', 32);
            $table->string('status', 24)->default('draft');
            $table->date('expected_at')->nullable();

            // Costs that belong to the shipment rather than any one line, spread
            // across lines by value when the goods are received.
            $table->bigInteger('freight_cost')->default(0);
            $table->bigInteger('other_cost')->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'po_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->bigInteger('unit_cost')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('line_total')->default(0);
            // Unit cost plus this line's share of freight, which is the number
            // that should become the SKU's cost price.
            $table->bigInteger('landed_unit_cost')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id'], 'poi_tenant_po');
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 32);
            $table->string('status', 24)->default('open');
            $table->string('scope', 24)->default('full');
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            // What the system believed when the sheet was opened, so the variance
            // is against that moment and not against a later value.
            $table->integer('expected_quantity');
            $table->integer('counted_quantity')->nullable();
            $table->string('reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['stock_count_id', 'sku_id'], 'sci_count_sku');
            $table->index(['tenant_id', 'stock_count_id'], 'sci_tenant_count');
        });

        Schema::table('skus', function (Blueprint $table): void {
            // Replenishment settings a human sets, as opposed to the ones the
            // reorder report derives from recent sell-through.
            $table->integer('reorder_point')->nullable()->after('is_active');
            $table->integer('safety_stock')->nullable()->after('reorder_point');
            $table->integer('reorder_quantity')->nullable()->after('safety_stock');
            $table->unsignedSmallInteger('lead_time_days')->nullable()->after('reorder_quantity');
            $table->foreignId('supplier_id')->nullable()->after('lead_time_days')->constrained()->nullOnDelete();
            // A service or made-to-order line has no stock to track.
            $table->boolean('tracks_inventory')->default(true)->after('supplier_id');
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->string('type', 24)->default('warehouse')->after('name');
            $table->text('address')->nullable()->after('type');
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->dropColumn(['type', 'address', 'is_default']);
        });

        Schema::table('skus', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'reorder_point', 'safety_stock', 'reorder_quantity',
                'lead_time_days', 'supplier_id', 'tracks_inventory',
            ]);
        });

        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_movements');
    }
};

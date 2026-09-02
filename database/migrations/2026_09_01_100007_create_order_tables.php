<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->string('external_id');
            $table->string('order_number', 64)->nullable();
            $table->timestamp('placed_at');
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('status', 24)->default('placed');
            $table->string('fulfillment_status', 24)->nullable();
            $table->string('payment_mode', 16)->default('prepaid');
            $table->string('shipping_state')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_pincode', 12)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->unsignedSmallInteger('items_count')->default(0);
            $table->unsignedSmallInteger('units_count')->default(0);

            $table->bigInteger('gross_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('shipping_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('invoiced_amount')->default(0);
            $table->bigInteger('cancelled_amount')->default(0);
            $table->bigInteger('returned_amount')->default(0);
            $table->bigInteger('rto_amount')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->bigInteger('cogs_amount')->default(0);
            $table->bigInteger('fees_amount')->default(0);
            $table->bigInteger('logistics_amount')->default(0);
            $table->bigInteger('packaging_amount')->default(0);
            $table->bigInteger('gateway_fee_amount')->default(0);
            $table->bigInteger('contribution_margin')->default(0);

            $table->boolean('is_first_order')->default(false);
            $table->boolean('has_return')->default(false);
            $table->boolean('is_rto')->default(false);
            $table->string('discount_codes')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'placed_at']);
            $table->index(['tenant_id', 'channel_id', 'placed_at']);
            $table->index(['tenant_id', 'status', 'placed_at']);
            $table->index(['tenant_id', 'payment_mode', 'placed_at']);
            $table->index(['tenant_id', 'shipping_state']);
            $table->index(['tenant_id', 'contribution_margin']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sku_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->string('sku_code')->nullable();
            $table->string('title')->nullable();
            $table->integer('qty')->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('cogs_unit')->default(0);
            $table->bigInteger('line_gross')->default(0);
            $table->bigInteger('line_net')->default(0);
            $table->bigInteger('line_fees')->default(0);
            $table->bigInteger('line_margin')->default(0);
            $table->string('status', 24)->default('placed');
            $table->integer('returned_qty')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'sku_id']);
        });

        Schema::create('order_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('code', 128);
            $table->string('type', 24)->default('percentage');
            $table->bigInteger('value')->default(0);
            $table->bigInteger('amount')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'code']);
        });

        Schema::create('discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id')->nullable();
            $table->string('code', 128);
            $table->string('type', 24)->default('percentage');
            $table->bigInteger('value')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->bigInteger('margin_burn')->default(0);
            $table->bigInteger('aov_with')->default(0);
            $table->bigInteger('aov_without')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('abandoned_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('abandoned_at');
            $table->bigInteger('cart_value')->default(0);
            $table->unsignedSmallInteger('items_count')->default(0);
            $table->boolean('recovered')->default(false);
            $table->timestamp('recovered_at')->nullable();
            $table->string('recovery_url')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'abandoned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_checkouts');
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

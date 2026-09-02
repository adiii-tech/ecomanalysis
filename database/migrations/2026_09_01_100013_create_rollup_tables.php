<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_metrics_rollup', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('payment_mode', 16)->default('prepaid');

            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('units_count')->default(0);
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('customers_count')->default(0);
            $table->unsignedInteger('new_customers')->default(0);
            $table->unsignedInteger('repeat_customers')->default(0);

            $table->bigInteger('gross_sales')->default(0);
            $table->bigInteger('discounts')->default(0);
            $table->bigInteger('cancelled_amount')->default(0);
            $table->unsignedInteger('cancelled_orders')->default(0);
            $table->bigInteger('invoiced_sales')->default(0);
            $table->unsignedInteger('invoiced_orders')->default(0);
            $table->bigInteger('returned_amount')->default(0);
            $table->unsignedInteger('returned_orders')->default(0);
            $table->bigInteger('rto_amount')->default(0);
            $table->unsignedInteger('rto_orders')->default(0);
            $table->bigInteger('net_sales')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('shipping_collected')->default(0);

            $table->bigInteger('cogs')->default(0);
            $table->bigInteger('marketplace_fees')->default(0);
            $table->bigInteger('logistics_cost')->default(0);
            $table->bigInteger('packaging_cost')->default(0);
            $table->bigInteger('gateway_fees')->default(0);
            $table->bigInteger('return_cost')->default(0);
            $table->bigInteger('contribution_margin')->default(0);

            $table->unsignedInteger('shipments_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('in_transit_count')->default(0);
            $table->unsignedInteger('loss_orders')->default(0);
            $table->bigInteger('loss_amount')->default(0);

            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'channel_id', 'payment_mode'], 'dmr_unique_idx');
            $table->index(['tenant_id', 'date'], 'dmr_tenant_date');
            $table->index(['tenant_id', 'channel_id', 'date'], 'dmr_tenant_channel_date');
        });

        Schema::create('ad_spend_rollup', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('platform', 32);
            $table->bigInteger('spend')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedInteger('conversions')->default(0);
            $table->bigInteger('conversion_value')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'platform'], 'asr_unique_idx');
            $table->index(['tenant_id', 'date'], 'asr_tenant_date');
        });

        Schema::create('sku_daily_rollup', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('sku_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('units_sold')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->bigInteger('gross_sales')->default(0);
            $table->bigInteger('net_sales')->default(0);
            $table->bigInteger('cogs')->default(0);
            $table->bigInteger('fees')->default(0);
            $table->bigInteger('margin')->default(0);
            $table->unsignedInteger('returned_units')->default(0);
            $table->bigInteger('returned_amount')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'sku_id', 'channel_id'], 'sdr_unique_idx');
            $table->index(['tenant_id', 'date'], 'sdr_tenant_date');
            $table->index(['tenant_id', 'sku_id', 'date'], 'sdr_tenant_sku_date');
        });

        Schema::create('state_daily_rollup', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('state', 96);
            $table->foreignId('channel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orders_count')->default(0);
            $table->bigInteger('gross_sales')->default(0);
            $table->bigInteger('net_sales')->default(0);
            $table->bigInteger('margin')->default(0);
            $table->unsignedInteger('rto_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('cod_orders')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'date', 'state', 'channel_id'], 'str_unique_idx');
            $table->index(['tenant_id', 'date'], 'str_tenant_date');
            $table->index(['tenant_id', 'state'], 'str_tenant_state');
        });

        Schema::create('cohort_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('cohort_month', 7);
            $table->unsignedTinyInteger('month_index');
            $table->unsignedInteger('cohort_size')->default(0);
            $table->unsignedInteger('active_customers')->default(0);
            $table->decimal('retention_pct', 6, 3)->default(0);
            $table->bigInteger('revenue')->default(0);
            $table->bigInteger('margin')->default(0);
            $table->bigInteger('cumulative_revenue')->default(0);
            $table->bigInteger('avg_ltv')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cohort_month', 'month_index'], 'cs_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_snapshots');
        Schema::dropIfExists('state_daily_rollup');
        Schema::dropIfExists('sku_daily_rollup');
        Schema::dropIfExists('ad_spend_rollup');
        Schema::dropIfExists('daily_metrics_rollup');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id')->nullable();
            $table->string('gateway', 64)->nullable();
            $table->string('method', 48)->nullable();
            $table->string('kind', 24)->default('sale');
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('fee')->default(0);
            $table->string('status', 24)->default('success');
            $table->string('failure_reason')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'processed_at']);
            $table->index(['tenant_id', 'kind', 'status']);
        });

        Schema::create('marketplace_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fee_type', 32)->default('commission');
            $table->bigInteger('amount')->default(0);
            $table->string('settlement_id')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->date('fee_date')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'channel_id', 'fee_type']);
            $table->index(['tenant_id', 'fee_date']);
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('settlement_id');
            $table->date('cycle_start');
            $table->date('cycle_end');
            $table->bigInteger('expected_amount')->default(0);
            $table->bigInteger('received_amount')->default(0);
            $table->bigInteger('variance_amount')->default(0);
            $table->string('status', 24)->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'settlement_id']);
            $table->index(['tenant_id', 'cycle_end']);
        });

        Schema::create('cost_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('cogs_method', 24)->default('sku_cost');
            $table->bigInteger('packaging_cost')->default(0);
            $table->bigInteger('per_order_fixed_cost')->default(0);
            $table->bigInteger('cod_charge')->default(0);
            $table->bigInteger('return_handling_cost')->default(0);
            $table->bigInteger('rto_handling_cost')->default(0);
            $table->bigInteger('default_shipping_cost')->default(0);
            $table->decimal('gateway_fee_pct', 5, 3)->default(2.0);
            $table->string('gst_mode', 24)->default('inclusive');
            $table->bigInteger('monthly_fixed_opex')->default(0);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('benchmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_roas', 6, 2)->default(3.0);
            $table->decimal('target_margin_pct', 6, 2)->default(25.0);
            $table->decimal('target_repeat_rate', 6, 2)->default(25.0);
            $table->decimal('target_cac', 12, 2)->default(0);
            $table->unsignedTinyInteger('dispatch_sla_days')->default(2);
            $table->unsignedTinyInteger('delivery_sla_days')->default(7);
            $table->decimal('rto_threshold_pct', 6, 2)->default(15.0);
            $table->decimal('return_threshold_pct', 6, 2)->default(10.0);
            $table->unsignedSmallInteger('days_of_cover_threshold')->default(14);
            $table->bigInteger('monthly_revenue_target')->default(0);
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmarks');
        Schema::dropIfExists('cost_settings');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('marketplace_fees');
        Schema::dropIfExists('transactions');
    }
};

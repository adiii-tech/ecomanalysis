<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->default('ithink');
            $table->string('external_id')->nullable();
            $table->string('courier', 64)->nullable();
            $table->string('awb', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->string('payment_mode', 16)->default('prepaid');
            $table->string('destination_state')->nullable();
            $table->string('destination_city')->nullable();
            $table->string('destination_pincode', 12)->nullable();
            $table->timestamp('manifested_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('promised_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ndr_reason')->nullable();
            $table->string('ndr_status', 32)->nullable();
            $table->boolean('is_rto')->default(false);
            $table->timestamp('rto_at')->nullable();
            $table->timestamp('cod_collected_at')->nullable();
            $table->timestamp('cod_remitted_at')->nullable();
            $table->bigInteger('cod_amount')->default(0);
            $table->bigInteger('shipping_cost')->default(0);
            $table->bigInteger('rto_cost')->default(0);
            $table->unsignedSmallInteger('transit_days')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'courier']);
            $table->index(['tenant_id', 'dispatched_at']);
            $table->index(['tenant_id', 'is_rto']);
            $table->index(['tenant_id', 'destination_state']);
        });

        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sku_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32);
            $table->string('external_id')->nullable();
            $table->string('type', 24)->default('customer_return');
            $table->string('reason_code', 64)->nullable();
            $table->string('reason_text')->nullable();
            $table->integer('qty')->default(1);
            $table->timestamp('initiated_at');
            $table->timestamp('received_at')->nullable();
            $table->bigInteger('refund_amount')->default(0);
            $table->bigInteger('loss_amount')->default(0);
            $table->boolean('restock')->default(true);
            $table->string('shipping_state')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'initiated_at']);
            $table->index(['tenant_id', 'type', 'initiated_at']);
            $table->index(['tenant_id', 'reason_code']);
        });

        Schema::create('pincode_risk', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('pincode', 12);
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->unsignedInteger('shipments_count')->default(0);
            $table->unsignedInteger('rto_count')->default(0);
            $table->decimal('rto_rate', 6, 3)->default(0);
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->string('risk_band', 16)->default('low');
            $table->boolean('cod_serviceable')->default(true);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pincode']);
            $table->index(['tenant_id', 'risk_band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pincode_risk');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('shipments');
    }
};

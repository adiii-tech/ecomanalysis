<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 32)->default('shopify');
            $table->string('external_id')->nullable();
            $table->string('email_hash', 64)->nullable();
            $table->string('masked_email')->nullable();
            $table->text('email_encrypted')->nullable();
            $table->string('phone_hash', 64)->nullable();
            $table->string('masked_phone', 32)->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('name')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 12)->nullable();
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->bigInteger('total_spent')->default(0);
            $table->bigInteger('aov')->default(0);
            $table->bigInteger('ltv')->default(0);
            $table->bigInteger('total_margin')->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->unsignedTinyInteger('rfm_r')->nullable();
            $table->unsignedTinyInteger('rfm_f')->nullable();
            $table->unsignedTinyInteger('rfm_m')->nullable();
            $table->string('rfm_segment', 32)->nullable();
            $table->boolean('is_vip')->default(false);
            $table->boolean('accepts_marketing')->default(false);
            $table->unsignedTinyInteger('churn_risk_score')->nullable();
            $table->unsignedSmallInteger('days_since_last_order')->nullable();
            $table->unsignedSmallInteger('avg_days_between_orders')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
            $table->index(['tenant_id', 'rfm_segment']);
            $table->index(['tenant_id', 'total_spent']);
            $table->index(['tenant_id', 'email_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

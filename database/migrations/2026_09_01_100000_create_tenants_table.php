<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('currency', 3)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->unsignedTinyInteger('fiscal_year_start')->default(4);
            $table->string('plan')->default('demo');
            $table->unsignedInteger('ai_credit_limit')->default(2);
            $table->unsignedInteger('ai_credits_used')->default(0);
            $table->boolean('is_demo')->default(true);
            $table->json('onboarding_state')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

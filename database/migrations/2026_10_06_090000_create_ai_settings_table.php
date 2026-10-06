<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // A tenant's own Anthropic key, so AI usage bills to them rather
            // than the shared app-wide key. Null means "use the system key" —
            // AI features stay available either way, never hard-gated on this.
            $table->text('api_key')->nullable();
            $table->string('model', 64)->nullable();

            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};

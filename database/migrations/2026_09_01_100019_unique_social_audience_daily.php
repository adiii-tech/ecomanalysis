<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audience buckets are re-pulled in full on every sync, so they need a natural
 * key to upsert against or each run duplicates the whole breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_audience_daily', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'social_account_id', 'date', 'dimension', 'bucket'], 'saud_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::table('social_audience_daily', function (Blueprint $table): void {
            $table->dropUnique('saud_unique_idx');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ad and analytics rows have no external id — their identity is the tuple of
 * dimensions (date, platform, campaign, breakdown, age, gender, …). Indexing
 * that tuple directly would blow past MySQL's key length limit and cannot span
 * nullable columns cleanly, so each row carries a hash of its own dimensions
 * and we upsert on that.
 *
 * Without this, every overlapping sync inserted duplicate rows and ad spend
 * multiplied on each run.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'ad_insights_daily',
        'analytics_daily',
        'analytics_pages',
        'analytics_cities',
        'analytics_products',
        'analytics_demographics',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->char('row_hash', 32)->nullable()->after('tenant_id');
                $blueprint->unique(['tenant_id', 'row_hash'], substr($table, 0, 20).'_row_hash_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropUnique(substr($table, 0, 20).'_row_hash_unique');
                $blueprint->dropColumn('row_hash');
            });
        }
    }
};

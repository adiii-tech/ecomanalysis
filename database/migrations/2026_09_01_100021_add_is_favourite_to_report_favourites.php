<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_favourites', function (Blueprint $table): void {
            // The row doubles as the "recently used" record, so opening a report
            // must not silently favourite it.
            $table->boolean('is_favourite')->default(false)->after('report_key');
        });

        // Rows that existed before this column were only ever created by
        // favouriting, so they keep that meaning.
        DB::table('report_favourites')->update(['is_favourite' => true]);
    }

    public function down(): void
    {
        Schema::table('report_favourites', function (Blueprint $table): void {
            $table->dropColumn('is_favourite');
        });
    }
};

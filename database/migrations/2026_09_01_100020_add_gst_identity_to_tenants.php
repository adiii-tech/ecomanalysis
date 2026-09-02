<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            // Place of supply decides CGST/SGST versus IGST, so the GST summary
            // cannot classify a single order without the seller's own state.
            $table->string('gst_state', 64)->nullable()->after('timezone');
            $table->string('gstin', 20)->nullable()->after('gst_state');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['gst_state', 'gstin']);
        });
    }
};

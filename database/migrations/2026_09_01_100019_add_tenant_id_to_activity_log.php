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
        Schema::table('activity_log', function (Blueprint $table): void {
            // Nullable because system-level activity (scheduler, install) has no
            // tenant; the audit screen only ever reads its own tenant's rows.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'id']);
        });

        // Existing rows were all written by a signed-in user, so their tenant is
        // recoverable; anything else stays system-level.
        DB::statement(
            'UPDATE activity_log a JOIN users u ON u.id = a.causer_id '
            ."SET a.tenant_id = u.tenant_id WHERE a.causer_type = 'App\\\\Models\\\\User'"
        );
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'id']);
            $table->dropColumn('tenant_id');
        });
    }
};

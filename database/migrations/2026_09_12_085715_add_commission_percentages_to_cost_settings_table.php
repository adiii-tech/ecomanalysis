<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A gateway charges a different rate per payment method, so one blended
     * percentage cannot describe the real cost. Each rate starts at zero —
     * a guessed commission would quietly move every margin.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'cod_commission_pct',
        'upi_commission_pct',
        'cards_commission_pct',
        'dc_commission_pct',
        'netbanking_commission_pct',
        'wallets_commission_pct',
    ];

    public function up(): void
    {
        Schema::table('cost_settings', function (Blueprint $table): void {
            $after = 'gateway_fee_pct';

            foreach (self::COLUMNS as $column) {
                $table->decimal($column, 5, 3)->default(0)->after($after);
                $after = $column;
            }
        });
    }

    public function down(): void
    {
        Schema::table('cost_settings', function (Blueprint $table): void {
            $table->dropColumn(self::COLUMNS);
        });
    }
};

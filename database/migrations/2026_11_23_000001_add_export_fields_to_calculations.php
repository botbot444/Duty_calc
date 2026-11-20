<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Export pivot (docs/EXPORT_PLAN.md). Additive only, so estimates saved before it still load.
 *
 * - valuation_basis / value_for_duty_zmw: what the charges were worked out on (FOB, CIF, …),
 *   taken from the rules' base components.
 * - display_*: the second currency the result is shown in (USD). Charges are still worked
 *   out, rounded and stored in ZMW; the display amounts are a conversion of those figures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculations', function (Blueprint $table) {
            $table->string('valuation_basis', 60)->nullable()->after('customs_value_zmw');
            $table->decimal('value_for_duty_zmw', 15, 2)->nullable()->after('valuation_basis');
            $table->char('display_currency', 3)->nullable()->after('total_zmw');
            $table->foreignId('display_rate_id')->nullable()->after('display_currency')
                ->constrained('exchange_rates')->restrictOnDelete();
            $table->decimal('display_rate_value', 18, 8)->nullable()->after('display_rate_id');
            $table->date('display_rate_effective_from')->nullable()->after('display_rate_value');
            $table->string('display_rate_source')->nullable()->after('display_rate_effective_from');
            $table->decimal('total_display', 15, 2)->nullable()->after('display_rate_source');
        });

        Schema::table('calculation_breakdowns', function (Blueprint $table) {
            $table->decimal('amount_display', 15, 2)->nullable()->after('amount_zmw');
        });
    }

    public function down(): void
    {
        Schema::table('calculation_breakdowns', function (Blueprint $table) {
            $table->dropColumn('amount_display');
        });

        Schema::table('calculations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('display_rate_id');
            $table->dropColumn([
                'valuation_basis', 'value_for_duty_zmw', 'display_currency', 'display_rate_value',
                'display_rate_effective_from', 'display_rate_source', 'total_display',
            ]);
        });
    }
};

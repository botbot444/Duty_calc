<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entered weekly by an admin. ZMW has no row: a ZMW input uses rate 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('currency', 3);
            $table->decimal('rate_to_zmw', 18, 8);     // 1 unit of currency = rate_to_zmw ZMW
            $table->date('effective_from');
            $table->date('effective_to')->nullable();  // inclusive; null = open-ended
            $table->string('source');
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['currency', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};

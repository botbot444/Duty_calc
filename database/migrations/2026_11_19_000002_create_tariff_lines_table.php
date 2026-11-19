<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per 8-digit national tariff code. Rates hang off these, not off products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_lines', function (Blueprint $table) {
            $table->id();
            $table->char('hs_code', 8);
            $table->text('official_description');
            $table->string('stat_unit', 8);           // App\Enums\StatUnit: kg, u, l
            $table->date('valid_from');
            $table->date('valid_to')->nullable();     // inclusive; null = open-ended
            $table->foreignId('tariff_source_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('source_page');
            $table->timestamps();

            $table->unique(['hs_code', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_lines');
    }
};

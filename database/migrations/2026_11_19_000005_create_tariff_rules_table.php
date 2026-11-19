<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per charge per tariff line. Tax order (sequence) and bases (base_components)
 * are data, not code. Rows are never edited in place once used: a change sets
 * effective_to on the old row and adds a new one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_line_id')->constrained()->restrictOnDelete();
            $table->string('tax_type', 20);           // App\Enums\TaxType
            $table->unsignedTinyInteger('sequence');
            $table->string('method', 20);             // App\Enums\RuleMethod
            $table->decimal('rate', 9, 6)->nullable();            // 0.250000 = 25%
            $table->decimal('specific_amount', 15, 4)->nullable();
            $table->char('specific_currency', 3)->nullable();
            $table->string('combine', 10)->nullable(); // App\Enums\CombineMode, compound only
            $table->json('base_components');           // e.g. ["customs_value","customs_duty"]
            $table->char('origin_country', 2)->nullable(); // null = standard rate for all origins

            // NULLs are distinct in a unique index, so two standard-rate rows (origin null)
            // would slip past it. Index a non-null copy instead.
            $table->char('origin_key', 2)->storedAs("COALESCE(`origin_country`, '')");

            $table->date('effective_from');
            $table->date('effective_to')->nullable();  // inclusive; null = open-ended
            $table->foreignId('tariff_source_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('source_page');
            $table->timestamps();

            $table->unique(
                ['tariff_line_id', 'tax_type', 'origin_key', 'effective_from'],
                'tariff_rules_line_tax_origin_from_unique'
            );
            $table->index(['tariff_line_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_rules');
    }
};

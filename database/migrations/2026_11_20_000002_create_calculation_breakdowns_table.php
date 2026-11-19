<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per charge in an estimate, with the rule's values copied in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('calculation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('tax_type', 20);
            $table->json('base_components');
            $table->decimal('taxable_base_zmw', 15, 2);
            $table->string('method', 20);
            $table->decimal('rate', 9, 6)->nullable();
            $table->decimal('specific_amount', 15, 4)->nullable();
            $table->char('specific_currency', 3)->nullable();
            $table->string('combine', 10)->nullable();
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('amount_zmw', 15, 2);

            $table->foreignId('tariff_rule_id')->constrained()->restrictOnDelete();
            $table->date('rule_effective_from');
            $table->date('rule_effective_to')->nullable();
            $table->foreignId('tariff_source_id')->constrained()->restrictOnDelete();
            $table->string('source_name');
            $table->unsignedSmallInteger('source_page');

            $table->unique(['calculation_id', 'sequence', 'tax_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_breakdowns');
    }
};

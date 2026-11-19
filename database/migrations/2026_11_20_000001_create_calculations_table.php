<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per estimate request, including ones that could not be estimated.
 * Every value the engine used is copied here, so later changes to rates, rules or
 * catalogue names never rewrite a saved estimate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable so `unsupported` requests for unknown items or routes are still saved.
            $table->string('catalogue_item_id', 20)->nullable();
            $table->string('catalogue_item_name')->nullable();
            $table->foreignId('tariff_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('hs_code', 8)->nullable();
            $table->text('official_description')->nullable();
            $table->string('stat_unit', 8)->nullable();
            $table->foreignId('trade_route_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('ai_product_analysis_id')->nullable()->index(); // FK added in B6

            $table->json('inputs');   // the request exactly as given

            $table->char('input_currency', 3)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('exchange_rate_value', 18, 8)->nullable();
            $table->date('exchange_rate_effective_from')->nullable();
            $table->string('exchange_rate_source')->nullable();

            $table->decimal('declared_value_zmw', 15, 2)->nullable();
            $table->decimal('freight_zmw', 15, 2)->nullable();
            $table->decimal('insurance_zmw', 15, 2)->nullable();
            $table->decimal('customs_value_zmw', 15, 2)->nullable();
            $table->decimal('quantity', 15, 4)->nullable();
            $table->string('quantity_unit', 8)->nullable();
            $table->decimal('total_zmw', 15, 2)->nullable();

            $table->string('status', 20);          // estimated / unsupported / incomplete
            $table->json('missing');
            $table->json('assumptions');
            $table->date('tariff_effective_date')->nullable();
            $table->date('calculated_on');          // Lusaka date used to pick rules and rates
            $table->string('engine_version', 20);

            // DATETIME, not TIMESTAMP: MariaDB rejects a NOT NULL TIMESTAMP without a default.
            $table->dateTime('created_at')->nullable();
            $table->dateTime('expires_at')->index();

            $table->foreign('catalogue_item_id')->references('id')->on('catalogue_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculations');
    }
};

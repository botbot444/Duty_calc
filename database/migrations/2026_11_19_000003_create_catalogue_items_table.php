<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What traders pick. Several items can share one tariff line.
 * The string ID (e.g. CAT-0012) is the only ID the UI and the AI pass around.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_items', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->foreignId('tariff_line_id')->constrained()->restrictOnDelete();
            $table->string('display_name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_items');
    }
};

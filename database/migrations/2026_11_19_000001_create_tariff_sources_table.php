<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where every tariff line and rate came from. Shown on results as "as of".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_url', 500)->nullable();
            $table->string('edition', 100)->nullable();
            $table->date('published_on')->nullable();
            $table->date('retrieved_on')->nullable();
            $table->string('verified_by')->nullable();
            $table->timestamps();

            $table->unique(['name', 'edition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_sources');
    }
};

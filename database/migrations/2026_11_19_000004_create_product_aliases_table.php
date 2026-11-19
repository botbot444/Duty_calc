<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Common names, misspellings and local terms (en / bem / sw) for catalogue search.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('catalogue_item_id', 20);
            $table->string('alias', 150);
            $table->string('language', 3);            // App\Enums\Language: en, bem, sw
            $table->timestamps();

            $table->foreign('catalogue_item_id')->references('id')->on('catalogue_items')->cascadeOnDelete();
            $table->unique(['catalogue_item_id', 'alias', 'language']);
            $table->fullText('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
    }
};

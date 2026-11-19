<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the AI suggested and what the trader chose. No image is ever stored here:
 * uploaded photos are deleted as soon as the analysis ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('input_type', 10);                 // text / image
            $table->string('language', 3);
            $table->string('description', 300)->nullable();   // what the trader typed, if anything
            $table->text('normalised_description')->nullable();
            $table->json('product_attributes');                // the AI's `attributes` (not named
            // `attributes`: that clashes with Eloquent)
            $table->json('candidates_sent');
            $table->json('ranked');
            $table->json('clarifications');                    // [{question, answer}] over all rounds
            $table->unsignedTinyInteger('rounds')->default(0); // clarification rounds used
            $table->string('status', 30);                      // ok / no_match / needs_clarification / error / fallback
            $table->string('failure_reason', 100)->nullable(); // why plain search was used instead
            $table->json('dropped_ids');                       // IDs the AI returned that Laravel refused
            $table->boolean('manual_review')->default(false);
            $table->string('confirmed_catalogue_item_id', 20)->nullable();
            $table->string('model_name', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('confirmed_catalogue_item_id')->references('id')->on('catalogue_items')->restrictOnDelete();
            $table->index('created_at');
        });

        Schema::table('calculations', function (Blueprint $table) {
            $table->foreign('ai_product_analysis_id')->references('id')->on('ai_product_analyses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calculations', function (Blueprint $table) {
            $table->dropForeign(['ai_product_analysis_id']);
        });
        Schema::dropIfExists('ai_product_analyses');
    }
};

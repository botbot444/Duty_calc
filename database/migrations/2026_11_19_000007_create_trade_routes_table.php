<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named trade_routes / TradeRoute to avoid clashing with Laravel's Route facade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_routes', function (Blueprint $table) {
            $table->id();
            $table->char('origin_iso2', 2);
            $table->char('destination_iso2', 2);
            $table->string('border_post', 100);
            $table->boolean('supported')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('origin_iso2')->references('iso2')->on('countries')->restrictOnDelete();
            $table->foreign('destination_iso2')->references('iso2')->on('countries')->restrictOnDelete();
            $table->unique(['origin_iso2', 'destination_iso2', 'border_post']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_routes');
    }
};

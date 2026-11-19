<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per admin save, written only by App\Services\Admin\AuditLog. Never updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);       // create / update / replace / end / import / toggle / delete
            $table->string('table_name', 64);
            $table->string('record_id', 64)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['table_name', 'record_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safe provider API call log. NEVER stores credentials — only
     * operational metadata (action, ids, status, error codes, timing).
     */
    public function up(): void
    {
        Schema::create('provider_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('activation_id', 100)->nullable();
            $table->string('status', 20); // success | error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('message', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'created_at']);
            $table->index('activation_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_logs');
    }
};

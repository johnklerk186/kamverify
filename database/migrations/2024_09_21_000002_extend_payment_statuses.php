<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 'expired' (provider timeout) and 'refunded' were used by the
        // payment service but missing from the enum — fatal on MySQL.
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'processing', 'completed', 'failed', 'cancelled', 'expired', 'refunded',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'processing', 'completed', 'failed', 'cancelled',
            ])->default('pending')->change();
        });
    }
};

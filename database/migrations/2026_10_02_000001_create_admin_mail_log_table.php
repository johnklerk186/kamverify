<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_mail_log', function (Blueprint $table) {
            $table->id();
            // One row per (event, entity) — the unique key makes admin
            // emails idempotent across webhook retries, repeated status
            // polls and queue redeliveries.
            $table->string('dedupe_key', 100)->unique();
            $table->string('subject');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_mail_log');
    }
};

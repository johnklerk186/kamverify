<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('popular_sort')->default(0);
            $table->timestamps();

            $table->unique(['service_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_countries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Static, admin-controlled "Popular" flag. Drives the Popular
     * badge and quick-filter chips in the buy flow — deliberately not
     * derived from usage, sales or stock.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('is_active');
        });

        DB::table('countries')
            ->whereIn('code', ['US', 'GB', 'AU', 'AT', 'MX', 'ES', 'DE', 'BR'])
            ->update(['is_popular' => true]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('is_popular');
        });
    }
};

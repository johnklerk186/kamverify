<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Temporarily unavailable" is a third storefront state distinct from
 * both flags we already have:
 *   is_active=false      platform kill-switch — hidden everywhere
 *   customer_enabled=false  storefront flag — hidden from customers
 *   temporarily_unavailable=true  still LISTED with an outage badge,
 *                        but server-side blocked from purchase.
 * Used for supplier-side failures (e.g. a carrier pool a platform
 * rejects) where the service should stay visible and return later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('temporarily_unavailable')->default(false)->after('customer_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('temporarily_unavailable');
        });
    }
};

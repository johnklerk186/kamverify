<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting bookkeeping for cancelled/expired/refunded orders:
     * records WHY the order was wound down and what the provider did
     * with the activation cost, so analytics can separate real provider
     * losses from costs that were recovered.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider_refund_status', 30)->nullable()->after('refund_amount');
            $table->string('cancellation_reason', 80)->nullable()->after('provider_refund_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['provider_refund_status', 'cancellation_reason']);
        });
    }
};

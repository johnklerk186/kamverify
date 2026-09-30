<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer-facing service catalogue is gated independently of
        // is_active so admin keeps managing the full service list while
        // only whitelisted services are sellable.
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('customer_enabled')->default(false)->after('is_active');
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            // MySQL cannot put a unique index on a TEXT column — the
            // sha256 hash is fixed-length and enforces the same
            // one-subscription-per-endpoint-per-user rule.
            $table->string('endpoint_hash', 64);
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding', 20)->default('aesgcm');
            $table->timestamps();
            $table->unique(['user_id', 'endpoint_hash'], 'push_sub_user_endpoint_unique');
        });

        // Enable only the four launch services for customers.
        \App\Models\Service::whereIn('slug', ['facebook', 'whatsapp', 'telegram', 'tiktok'])
            ->update(['customer_enabled' => true]);

        // Currency + deposit configuration.
        \App\Models\Setting::set('site_currency', 'XAF');
        \App\Models\Setting::set('min_deposit_amount', 100);
        \App\Models\Setting::set('max_deposit_amount', 1000000);
        \App\Models\Setting::set('usd_to_xaf_rate', 600);
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('customer_enabled');
        });
    }
};

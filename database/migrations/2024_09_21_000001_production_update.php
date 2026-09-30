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
        // only whitelisted services are sellable. Guarded so the
        // migration can be re-run after a partial failure (MySQL DDL
        // is not transactional, so an earlier attempt may have already
        // added the column).
        if (!Schema::hasColumn('services', 'customer_enabled')) {
            Schema::table('services', function (Blueprint $table) {
                $table->boolean('customer_enabled')->default(false)->after('is_active');
            });
        }

        if (!Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('public_key')->nullable();
                $table->string('auth_token')->nullable();
                $table->string('content_encoding', 20)->default('aesgcm');
                $table->timestamps();
            });
        }

        // MySQL cannot index a full TEXT column — use a prefix length
        // there; other drivers take the plain unique key. Try/catch
        // covers installs where the table was created before this fix
        // (the CREATE succeeded, then the old index statement failed).
        try {
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                \Illuminate\Support\Facades\DB::statement(
                    'ALTER TABLE push_subscriptions ADD UNIQUE KEY push_sub_user_endpoint_unique (user_id, endpoint(191))'
                );
            } else {
                Schema::table('push_subscriptions', function (Blueprint $table) {
                    $table->unique(['user_id', 'endpoint'], 'push_sub_user_endpoint_unique');
                });
            }
        } catch (\Throwable $e) {
            // Index already present — nothing to do.
        }

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
        if (Schema::hasColumn('services', 'customer_enabled')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('customer_enabled');
            });
        }
    }
};

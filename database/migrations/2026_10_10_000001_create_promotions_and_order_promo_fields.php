<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-boxed promotional pricing layer.
 *
 * A promotion never rewrites normal pricing — services/countries keep their
 * own markup config. PromotionService computes the effective selling price
 * per purchase while a promotion is enabled and inside its window; after
 * ends_at the normal price applies automatically with no cleanup needed.
 *
 * Orders record which promotion (if any) priced them plus the normal price
 * and discount so reporting and refunds stay correct forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // {facebook_price, whatsapp_us_price, whatsapp_discount, telegram_price}
            $table->json('config');
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('provider_id')
                ->constrained('promotions')->nullOnDelete();
            $table->decimal('normal_price', 10, 2)->nullable()->after('selling_price');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('normal_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['normal_price', 'discount_amount']);
        });
        Schema::dropIfExists('promotions');
    }
};

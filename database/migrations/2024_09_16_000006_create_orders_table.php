<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('country_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('provider_id')->constrained();
            $table->string('provider_activation_id')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('profit', 15, 2)->default(0);
            $table->enum('status', [
                'pending', 'processing', 'number_assigned', 'waiting_for_sms', 
                'sms_received', 'completed', 'cancelled', 'refunded', 'expired', 'failed'
            ])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->json('provider_response')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
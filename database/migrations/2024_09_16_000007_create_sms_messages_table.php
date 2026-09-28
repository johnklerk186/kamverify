<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('sender')->nullable();
            $table->text('message');
            $table->string('otp_code')->nullable();
            $table->timestamp('received_at');
            $table->string('provider_message_id')->nullable();
            $table->json('raw_provider_response')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
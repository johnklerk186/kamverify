<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    protected $fillable = [
        'order_id',
        'sender',
        'message',
        'otp_code',
        'received_at',
        'provider_message_id',
        'raw_provider_response',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'raw_provider_response' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function extractOtp(): ?string
    {
        // Try to extract OTP code from message
        if (preg_match('/\b\d{4,8}\b/', $this->message, $matches)) {
            return $matches[0];
        }
        return null;
    }
}
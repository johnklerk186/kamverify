<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    protected $fillable = [
        'referral_id',
        'user_id',
        'order_id',
        'reward_amount',
        'reward_type',
        'description',
        'status',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'processed_at' => now(),
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'processed_at' => now(),
        ]);
    }
}
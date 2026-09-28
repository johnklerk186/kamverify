<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_user_id',
        'referral_code',
        'ip_address',
        'user_agent',
        'qualified_at',
        'is_active',
    ];

    protected $casts = [
        'qualified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class);
    }

    public function markAsQualified(): void
    {
        $this->update([
            'qualified_at' => now(),
            'is_active' => true,
        ]);
    }

    public function isQualified(): bool
    {
        return $this->qualified_at !== null;
    }
}
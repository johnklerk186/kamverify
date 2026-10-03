<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'balance',
        'total_deposited',
        'total_withdrawn',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'total_deposited' => 'decimal:2',
        'total_withdrawn' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function deposit(float $amount, string $description = null, string $type = 'deposit', string $reference = null): WalletTransaction
    {
        return $this->transactions()->create([
            'transaction_id' => 'TXN-' . strtoupper(uniqid()),
            'user_id' => $this->user_id,
            'wallet_id' => $this->id,
            'amount' => $amount,
            'type' => $type,
            'status' => 'completed',
            'reference' => $reference,
            'description' => $description ?? 'Wallet deposit',
        ]);
    }

    public function withdraw(float $amount, string $description = null, string $type = 'withdrawal', string $reference = null): WalletTransaction
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }

        return $this->transactions()->create([
            'transaction_id' => 'TXN-' . strtoupper(uniqid()),
            'user_id' => $this->user_id,
            'wallet_id' => $this->id,
            'amount' => -$amount,
            'type' => $type,
            'status' => 'completed',
            'reference' => $reference,
            'description' => $description ?? 'Wallet withdrawal',
        ]);
    }
}
<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\DepositSuccessful;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function getWallet(User $user): Wallet
    {
        return $user->wallet ?? $this->createWallet($user);
    }

    public function createWallet(User $user): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
            'total_deposited' => 0,
            'total_withdrawn' => 0,
            'currency' => 'XAF',
            'is_active' => true,
        ]);
    }

    /**
     * Fetch the wallet row with a pessimistic lock inside the current
     * transaction — the only safe way to check-then-mutate a balance.
     */
    protected function lockWallet(User $user): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

        return $wallet ?? $this->createWallet($user);
    }

    /**
     * Credit the wallet. $type must reflect WHY money came in:
     * 'deposit' = external customer money (Fapshi), 'refund' = order
     * money returned, 'reward' = referral credit, 'adjustment' = admin
     * credit. Only real deposits increment total_deposited — refunds
     * and rewards must never inflate the deposited figure.
     */
    public function deposit(User $user, float $amount, string $description = null, array $metadata = null, string $type = 'deposit', string $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $metadata, $type, $reference) {
            $wallet = $this->lockWallet($user);

            if (!$wallet->is_active) {
                throw new \Exception('Wallet is not active');
            }

            if ($amount <= 0) {
                throw new \Exception('Deposit amount must be positive');
            }

            $transaction = $wallet->deposit($amount, $description ?? 'Wallet deposit', $type, $reference);

            if ($metadata) {
                $transaction->metadata = $metadata;
                $transaction->save();
            }

            $wallet->balance += $amount;
            if ($type === 'deposit') {
                $wallet->total_deposited += $amount;
            }
            $wallet->save();

            // No notification here — deposit() also serves refunds and
            // adjustments. The caller fires the event-specific notice
            // (DepositSuccessful, Refund Issued, …) so labels stay honest.

            Log::info('Wallet credit successful', [
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'transaction_id' => $transaction->transaction_id,
            ]);

            return $transaction;
        });
    }

    /**
     * Debit the wallet. $type must reflect WHY money went out:
     * 'purchase' = number order, 'adjustment' = admin debit,
     * 'withdrawal' = anything else.
     */
    public function withdraw(User $user, float $amount, string $description = null, array $metadata = null, string $type = 'withdrawal', string $reference = null): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $metadata, $type, $reference) {
            $wallet = $this->lockWallet($user);

            if (!$wallet->is_active) {
                throw new \Exception('Wallet is not active');
            }

            if ($amount <= 0) {
                throw new \Exception('Withdrawal amount must be positive');
            }

            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $transaction = $wallet->withdraw($amount, $description ?? 'Wallet withdrawal', $type, $reference);

            if ($metadata) {
                $transaction->metadata = $metadata;
                $transaction->save();
            }

            $wallet->balance -= $amount;
            $wallet->total_withdrawn += $amount;
            $wallet->save();

            Log::info('Wallet debit successful', [
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'transaction_id' => $transaction->transaction_id,
            ]);

            return $transaction;
        });
    }

    public function hasSufficientBalance(User $user, float $amount): bool
    {
        $wallet = $this->getWallet($user);
        return $wallet->balance >= $amount;
    }

    public function getBalance(User $user): float
    {
        $wallet = $this->getWallet($user);
        return $wallet->balance;
    }

    public function getTransactions(User $user, int $limit = 50)
    {
        return WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
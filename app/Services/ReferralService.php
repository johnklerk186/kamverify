<?php

namespace App\Services;

use App\Models\User;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function createReferral(User $referrer, User $referredUser, string $referralCode, ?string $ipAddress = null, ?string $userAgent = null): Referral
    {
        return DB::transaction(function () use ($referrer, $referredUser, $referralCode, $ipAddress, $userAgent) {
            // Prevent self-referral
            if ($referrer->id === $referredUser->id) {
                throw new \Exception('Cannot refer yourself');
            }

            // Check if referral already exists
            $existingReferral = Referral::where('referred_user_id', $referredUser->id)->first();
            if ($existingReferral) {
                throw new \Exception('User already has a referrer');
            }

            $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => $referredUser->id,
                'referral_code' => $referralCode,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'is_active' => true,
            ]);

            // Update referred user's referrer
            $referredUser->update(['referred_by' => $referrer->id]);

            Log::info('Referral created', [
                'referrer_id' => $referrer->id,
                'referred_user_id' => $referredUser->id,
                'referral_code' => $referralCode,
            ]);

            return $referral;
        });
    }

    public function processQualifyingPurchase(Order $order): void
    {
        $user = $order->user;
        
        // Check if user was referred
        if (!$user->referred_by) {
            return;
        }

        $referral = Referral::where('referred_user_id', $user->id)->first();
        
        if (!$referral || !$referral->is_active) {
            return;
        }

        // Check if already qualified
        if ($referral->isQualified()) {
            return;
        }

        // Mark as qualified
        $referral->markAsQualified();

        // Process reward
        $this->processReferralReward($referral, $order);
    }

    protected function processReferralReward(Referral $referral, Order $order): void
    {
        $rewardAmount = $this->calculateRewardAmount($order->selling_price);
        
        if ($rewardAmount <= 0) {
            return;
        }

        DB::transaction(function () use ($referral, $order, $rewardAmount) {
            // Create reward record
            $reward = ReferralReward::create([
                'referral_id' => $referral->id,
                'user_id' => $referral->referrer_id,
                'order_id' => $order->id,
                'reward_amount' => $rewardAmount,
                'reward_type' => 'credit',
                'description' => 'Referral reward for qualifying purchase',
                'status' => 'approved',
                'processed_at' => now(),
            ]);

            // Credit wallet
            $this->walletService->deposit(
                $referral->referrer,
                $rewardAmount,
                'Referral reward',
                [
                    'referral_id' => $referral->id,
                    'reward_id' => $reward->id,
                    'order_id' => $order->id,
                ]
            );

            Log::info('Referral reward processed', [
                'referral_id' => $referral->id,
                'referrer_id' => $referral->referrer_id,
                'reward_amount' => $rewardAmount,
                'order_id' => $order->id,
            ]);
        });
    }

    protected function calculateRewardAmount(float $orderAmount): float
    {
        $rewardType = Setting::get('referral_reward_type', 'percentage');
        $rewardValue = Setting::get('referral_reward_value', 10);

        if ($rewardType === 'percentage') {
            return $orderAmount * ($rewardValue / 100);
        }

        return (float) $rewardValue;
    }

    public function getReferralStats(User $user): array
    {
        $referrals = Referral::where('referrer_id', $user->id)->get();
        
        return [
            'total_referrals' => $referrals->count(),
            'qualified_referrals' => $referrals->where('qualified_at', '!=', null)->count(),
            'pending_referrals' => $referrals->whereNull('qualified_at')->count(),
            'total_rewards' => ReferralReward::where('user_id', $user->id)->where('status', 'approved')->sum('reward_amount'),
            'pending_rewards' => ReferralReward::where('user_id', $user->id)->where('status', 'pending')->sum('reward_amount'),
        ];
    }

    public function getReferralLink(User $user): string
    {
        $referralCode = $user->generateReferralCode();
        $baseUrl = config('app.url');
        return "{$baseUrl}?ref={$referralCode}";
    }

    public function validateReferralCode(string $code): ?User
    {
        return User::where('referral_code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }
}
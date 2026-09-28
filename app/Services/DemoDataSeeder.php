<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Order;
use App\Models\Provider;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\Refund;
use App\Models\Service;
use App\Models\SmsMessage;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\DepositSuccessful;
use App\Notifications\SmsReceived;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Seeds a clearly-marked demo customer account for UI/UX review.
 * Idempotent — safe to call on every /demo entry. All data lives on
 * the dedicated demo user; no real customer data is touched.
 */
class DemoDataSeeder
{
    public const DEMO_EMAIL = 'demo.reviewer@kamverify.test';
    private const TOP_UP_BELOW = 25.00;
    private const TOP_UP_TO = 250.00;

    public function demoUser(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Demo Reviewer',
                'password' => Hash::make(Str::random(32)),
                'role' => 'customer',
                'is_active' => true,
                'phone' => '+15550000000',
            ]
        );

        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        return $user;
    }

    public function seed(User $demo): void
    {
        $this->seedWallet($demo);
        $this->seedOrders($demo);
        $this->seedSupport($demo);
        $this->seedReferrals($demo);
        $this->seedNotifications($demo);
    }

    /** created_at isn't mass-assignable — set it directly then save. */
    protected function backdate($model, Carbon $at): void
    {
        $model->created_at = $at;
        $model->updated_at = $at;
        $model->save();
    }

    /**
     * Keep the demo wallet topped up so the buy flow always works —
     * only tops up when the balance has been nearly exhausted.
     */
    protected function seedWallet(User $demo): void
    {
        $wallet = Wallet::firstOrCreate(
            ['user_id' => $demo->id],
            ['balance' => 0, 'total_deposited' => 0, 'total_withdrawn' => 0, 'currency' => 'USD', 'is_active' => true]
        );

        if ($wallet->balance < self::TOP_UP_BELOW) {
            $needed = round(self::TOP_UP_TO - (float) $wallet->balance, 2);
            $wallet->increment('balance', $needed);
            $wallet->increment('total_deposited', $needed);

            WalletTransaction::create([
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'user_id' => $demo->id,
                'wallet_id' => $wallet->id,
                'amount' => $needed,
                'type' => 'deposit',
                'status' => 'completed',
                'description' => 'Demo wallet top-up',
                'metadata' => ['demo' => true],
            ]);
        }

        if (!WalletTransaction::where('user_id', $demo->id)->where('type', 'deposit')->exists()) {
            foreach ([
                ['amount' => 50.00, 'days' => 6],
                ['amount' => 100.00, 'days' => 3],
            ] as $dep) {
                $tx = WalletTransaction::create([
                    'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                    'user_id' => $demo->id,
                    'wallet_id' => $wallet->id,
                    'amount' => $dep['amount'],
                    'type' => 'deposit',
                    'status' => 'completed',
                    'description' => 'Wallet deposit via Fapshi (demo)',
                    'metadata' => ['demo' => true, 'payment_method' => 'fapshi'],
                ]);
                $this->backdate($tx, now()->subDays($dep['days']));
            }
        }
    }

    /**
     * Seed orders across the full lifecycle: completed (with SMS),
     * refunded, expired — plus one live "waiting_for_sms" order primed
     * in the mock-provider cache so it auto-completes while the
     * reviewer watches.
     */
    protected function seedOrders(User $demo): void
    {
        if (Order::where('user_id', $demo->id)->exists()) {
            return;
        }

        $provider = Provider::first();
        $services = Service::all()->keyBy('slug');
        $countries = Country::all()->keyBy('code');
        if (!$provider || $services->isEmpty() || $countries->isEmpty()) {
            return;
        }

        $wallet = $demo->wallet;
        $pick = fn ($col, $key) => $col->get($key) ?? $col->first();

        $mkOrder = function (array $attrs, Carbon $createdAt) use ($demo, $wallet, $provider) {
            $order = Order::create(array_merge([
                'order_id' => 'ORD-DEMO-' . strtoupper(Str::random(6)),
                'user_id' => $demo->id,
                'provider_id' => $provider->id,
                'purchase_price' => 0.50,
                'selling_price' => 0.65,
                'profit' => 0.15,
            ], $attrs));
            $this->backdate($order, $createdAt);

            $tx = WalletTransaction::create([
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'user_id' => $demo->id,
                'wallet_id' => $wallet->id,
                'amount' => -abs($order->selling_price),
                'type' => 'withdrawal',
                'status' => 'completed',
                'description' => 'Order purchase',
                'metadata' => ['demo' => true, 'order_id' => $order->order_id],
            ]);
            $this->backdate($tx, $createdAt);

            return $order;
        };

        $mkSms = function (Order $order, string $sender, string $message, string $code, Carbon $at) {
            $sms = SmsMessage::create([
                'order_id' => $order->id,
                'sender' => $sender,
                'message' => $message,
                'otp_code' => $code,
                'provider_message_id' => 'demo-' . $order->order_id,
                'received_at' => $at,
            ]);
            $this->backdate($sms, $at);
        };

        $mkRefundTx = function (Order $order, Carbon $at) use ($demo, $wallet) {
            $tx = WalletTransaction::create([
                'transaction_id' => 'TXN-' . strtoupper(uniqid()),
                'user_id' => $demo->id,
                'wallet_id' => $wallet->id,
                'amount' => $order->selling_price,
                'type' => 'refund',
                'status' => 'completed',
                'description' => 'Order refund',
                'metadata' => ['demo' => true, 'order_id' => $order->order_id],
            ]);
            $this->backdate($tx, $at);

            return $tx;
        };

        // Completed orders with received SMS
        $o1 = $mkOrder([
            'service_id' => $pick($services, 'whatsapp')->id,
            'country_id' => $pick($countries, 'US')->id,
            'phone_number' => '+15550104829',
            'provider_activation_id' => 'DEMO-ACT-C1',
            'status' => 'completed',
            'completed_at' => now()->subDays(2)->addMinutes(3),
            'expires_at' => now()->subDays(2)->addMinutes(15),
        ], now()->subDays(2));
        $mkSms($o1, '+15550191001', 'Your WhatsApp verification code is 482910. Do not share this code.', '482910', $o1->created_at->copy()->addMinutes(3));

        $o2 = $mkOrder([
            'service_id' => $pick($services, 'telegram')->id,
            'country_id' => $pick($countries, 'GB')->id,
            'phone_number' => '+447700901234',
            'provider_activation_id' => 'DEMO-ACT-C2',
            'status' => 'completed',
            'completed_at' => now()->subDay()->addMinutes(4),
            'expires_at' => now()->subDay()->addMinutes(15),
        ], now()->subDay());
        $mkSms($o2, 'Telegram', 'Telegram code: 73514', '73514', $o2->created_at->copy()->addMinutes(4));

        // Cancelled + refunded
        $o3 = $mkOrder([
            'service_id' => $pick($services, 'google')->id,
            'country_id' => $pick($countries, 'CA')->id,
            'phone_number' => '+15550102211',
            'provider_activation_id' => 'DEMO-ACT-X1',
            'status' => 'refunded',
            'refund_amount' => 0.65,
            'cancelled_at' => now()->subDays(3)->addMinutes(6),
            'expires_at' => now()->subDays(3)->addMinutes(15),
        ], now()->subDays(3));
        $refundTx = $mkRefundTx($o3, $o3->cancelled_at);
        $refund = Refund::create([
            'refund_id' => 'REF-DEMO-' . strtoupper(Str::random(5)),
            'user_id' => $demo->id,
            'order_id' => $o3->id,
            'wallet_transaction_id' => $refundTx->id,
            'amount' => 0.65,
            'type' => 'order',
            'status' => 'processed',
            'reason' => 'Order cancellation or expiration',
        ]);
        $this->backdate($refund, $o3->cancelled_at);

        // Expired + refunded
        $o4 = $mkOrder([
            'service_id' => $pick($services, 'tiktok')->id,
            'country_id' => $pick($countries, 'DE')->id,
            'phone_number' => '+4915211100044',
            'provider_activation_id' => 'DEMO-ACT-X2',
            'status' => 'expired',
            'refund_amount' => 0.65,
            'expires_at' => now()->subDays(4),
        ], now()->subDays(4)->subMinutes(15));
        $mkRefundTx($o4, $o4->expires_at);

        // Live order: waiting for SMS — primed so the mock provider
        // "delivers" it ~5–35s after the reviewer opens the page.
        $liveActivation = 'DEMO-ACT-LIVE-' . strtoupper(Str::random(6));
        Cache::put("mock_activation:{$liveActivation}", now()->timestamp - 15, 3600);

        $mkOrder([
            'service_id' => $pick($services, 'whatsapp')->id,
            'country_id' => $pick($countries, 'US')->id,
            'phone_number' => '+15550107788',
            'provider_activation_id' => $liveActivation,
            'status' => 'waiting_for_sms',
            'expires_at' => now()->addMinutes(15),
        ], now()->subMinute());
    }

    protected function seedSupport(User $demo): void
    {
        if (SupportTicket::where('user_id', $demo->id)->exists()) {
            return;
        }

        $admin = User::where('role', 'admin')->first();

        $ticket = SupportTicket::create([
            'ticket_id' => 'TKT-DEMO-' . strtoupper(Str::random(5)),
            'user_id' => $demo->id,
            'subject' => 'How long does an activation stay open?',
            'category' => 'order',
            'priority' => 'low',
            'status' => 'resolved',
        ]);
        $this->backdate($ticket, now()->subDays(2));

        $msg = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $demo->id,
            'is_admin' => false,
            'message' => 'Hi — once I get a number, how long do I have to receive the SMS? (demo question)',
        ]);
        $this->backdate($msg, now()->subDays(2));

        if ($admin) {
            $reply = SupportMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $admin->id,
                'is_admin' => true,
                'message' => 'Activations stay open for 15 minutes. If no SMS arrives you can cancel for a full refund, or it auto-refunds on expiry.',
            ]);
            $this->backdate($reply, now()->subDays(2)->addHour());
        }
    }

    protected function seedReferrals(User $demo): void
    {
        if (Referral::where('referrer_id', $demo->id)->exists()) {
            return;
        }

        foreach (['demo.friend1@kamverify.test', 'demo.friend2@kamverify.test'] as $i => $email) {
            $friend = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Demo Referral ' . ($i + 1),
                    'password' => Hash::make(Str::random(32)),
                    'role' => 'customer',
                    'is_active' => true,
                    'referred_by' => $demo->id,
                ]
            );
            if (!$friend->email_verified_at) {
                $friend->email_verified_at = now();
                $friend->save();
            }

            $referral = Referral::create([
                'referrer_id' => $demo->id,
                'referred_user_id' => $friend->id,
                'referral_code' => $demo->referral_code . '-' . ($i + 1),
                'qualified_at' => now()->subDays(2 - $i),
                'is_active' => true,
            ]);
            $this->backdate($referral, now()->subDays(3 - $i));
        }

        $order = Order::where('user_id', $demo->id)->where('status', 'completed')->first();
        $referral = Referral::where('referrer_id', $demo->id)->first();

        if ($referral) {
            $reward = ReferralReward::create([
                'referral_id' => $referral->id,
                'user_id' => $demo->id,
                'order_id' => $order?->id,
                'reward_amount' => 0.50,
                'reward_type' => 'credit',
                'description' => 'Referral reward — friend\'s first purchase',
                'status' => 'approved',
                'processed_at' => now()->subDays(2),
                'metadata' => ['demo' => true],
            ]);
            $this->backdate($reward, now()->subDays(2));
        }
    }

    protected function seedNotifications(User $demo): void
    {
        if ($demo->notifications()->exists()) {
            return;
        }

        $demo->notify(new DepositSuccessful(50.00, 'Fapshi (demo)'));

        $order = Order::where('user_id', $demo->id)->where('status', 'completed')->first();
        if ($order) {
            $demo->notify(new SmsReceived($order, '+15550191001', '482910'));
        }
    }
}

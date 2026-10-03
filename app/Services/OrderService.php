<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Models\Provider;
use App\Notifications\OrderCreated;
use App\Services\AdminMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderService
{
    protected WalletService $walletService;
    protected PricingService $pricingService;
    protected ReferralService $referralService;

    public function __construct(WalletService $walletService, PricingService $pricingService, ReferralService $referralService)
    {
        $this->walletService = $walletService;
        $this->pricingService = $pricingService;
        $this->referralService = $referralService;
    }

    public function createOrder(User $user, Country $country, Service $service, Provider $provider, float $providerCost): Order
    {
        $order = DB::transaction(function () use ($user, $country, $service, $provider, $providerCost) {
            $sellingPrice = $this->pricingService->calculateSellingPrice($providerCost, $country, $service);
            $profit = $this->pricingService->calculateProfit($sellingPrice, $providerCost);

            // Check wallet balance
            if (!$this->walletService->hasSufficientBalance($user, $sellingPrice)) {
                throw new \Exception('Insufficient wallet balance');
            }

            // Deduct from wallet — a purchase debit, not a generic withdrawal
            $purchaseTxn = $this->walletService->withdraw($user, $sellingPrice, 'Order purchase', [
                'order_type' => 'number_purchase',
                'service' => $service->name,
                'country' => $country->name,
            ], 'purchase');

            // Create order
            $order = Order::create([
                'order_id' => 'ORD-' . strtoupper(uniqid()),
                'user_id' => $user->id,
                'country_id' => $country->id,
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'purchase_price' => $providerCost,
                'selling_price' => $sellingPrice,
                'profit' => $profit,
                'status' => 'pending',
                'expires_at' => Carbon::now()->addMinutes(15), // Default 15 minutes
            ]);

            // Backfill the order reference onto the purchase transaction
            // so the ledger links debit → order both ways.
            $purchaseTxn->update(['reference' => $order->order_id]);

            // Send notification
            $user->notify(new OrderCreated($order));

            Log::info('Order created successfully', [
                'order_id' => $order->order_id,
                'user_id' => $user->id,
                'service' => $service->name,
                'country' => $country->name,
                'amount' => $sellingPrice,
            ]);

            return $order;
        });

        app(AdminMailer::class)->send(
            'order.created:' . $order->id,
            'KamVerify — New Number Purchase',
            'A customer purchased a number',
            [
                'Customer' => $user->name . ' <' . $user->email . '>',
                'Order' => $order->order_id,
                'Service' => $service->name,
                'Country' => $country->name,
                'Price' => xaf($order->selling_price),
            ]
        );

        return $order;
    }

    public function assignNumber(Order $order, string $phoneNumber, string $providerActivationId, ?float $actualCost = null): Order
    {
        $order->phone_number = $phoneNumber;
        $order->provider_activation_id = $providerActivationId;

        // When the provider reports the real activation cost, record it —
        // catalog prices are estimates and can drift.
        if ($actualCost !== null && $actualCost > 0 && abs($actualCost - (float) $order->purchase_price) > 0.0001) {
            $order->purchase_price = $actualCost;
            $order->profit = $this->pricingService->calculateProfit((float) $order->selling_price, $actualCost);
        }

        $this->updateStatus($order, 'number_assigned');

        $order->user->notify(new \App\Notifications\KamVerifyNotification(
            'number_assigned',
            'Number Assigned',
            'Your ' . $order->service->name . ' number ' . $phoneNumber . ' has been assigned successfully.',
            url('/orders/' . $order->id),
            'View Order',
            'fa-phone'
        ));

        // A number actually delivered = the customer's first successful
        // purchase — this is what qualifies a referral reward. Orders that
        // fail at the provider (and get refunded) never pay out.
        $this->referralService->processQualifyingPurchase($order);

        \App\Models\PhoneNumber::create([
            'order_id' => $order->id,
            'provider_id' => $order->provider_id,
            'country_id' => $order->country_id,
            'service_id' => $order->service_id,
            'phone_number' => $phoneNumber,
            'provider_activation_id' => $providerActivationId,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        Log::info('Number assigned to order', [
            'order_id' => $order->order_id,
            'phone_number' => $phoneNumber,
            'provider_activation_id' => $providerActivationId,
        ]);

        app(AdminMailer::class)->send(
            'order.assigned:' . $order->id,
            'KamVerify — Number Assigned',
            'A provider assigned a number to an order',
            [
                'Customer' => $order->user->name . ' <' . $order->user->email . '>',
                'Order' => $order->order_id,
                'Service' => $order->service->name,
                'Country' => $order->country->name,
                'Number' => $phoneNumber,
            ]
        );

        return $order;
    }

    /**
     * Move an order through the lifecycle state machine. Transitions are
     * validated against Order::TRANSITIONS — illegal jumps throw. Pass
     * $force only from audited admin overrides.
     */
    public function updateStatus(Order $order, string $status, bool $force = false): Order
    {
        if (!array_key_exists($status, Order::TRANSITIONS)) {
            throw new \Exception('Invalid order status');
        }

        if (!$force && !$order->canTransitionTo($status)) {
            throw new \Exception("Illegal order transition: {$order->status} → {$status}");
        }

        $order->status = $status;

        if ($status === 'completed') {
            $order->completed_at = Carbon::now();
        } elseif ($status === 'cancelled') {
            $order->cancelled_at = Carbon::now();
        }

        $order->save();

        // Customer-facing lifecycle notifications — fired from the single
        // transition funnel so every path (poll, job, cancel, admin) is
        // covered exactly once.
        $notice = match ($status) {
            'completed' => ['order_completed', 'Order Completed',
                'Your verification SMS was received — your ' . $order->service->name . ' order is complete.'],
            'cancelled' => ['order_cancelled', 'Order Cancelled',
                'Your ' . $order->service->name . ' order was cancelled' . ($order->refund_amount ? ' and ' . xaf($order->refund_amount) . ' was refunded to your wallet' : '') . '.'],
            'expired'   => ['order_expired', 'Order Expired',
                'Your ' . $order->service->name . ' order expired without receiving an SMS.'],
            'failed'    => ['order_failed', 'Order Failed',
                'Your ' . $order->service->name . ' order could not be completed.'],
            default     => null,
        };

        if ($notice) {
            $order->user->notify(new \App\Notifications\KamVerifyNotification(
                $notice[0], $notice[1], $notice[2],
                url('/orders/' . $order->id), 'View Order'
            ));
        }

        // Admin-side lifecycle emails — the same funnel, same exactly-once
        // guarantee. 'completed' means the SMS/code arrived (no OTP is ever
        // included). Dedupe keys make redeliveries impossible.
        $adminSubject = match ($status) {
            'completed' => 'KamVerify — Order Completed (SMS received)',
            'cancelled' => 'KamVerify — Order Cancelled',
            'expired'   => 'KamVerify — Order Expired',
            'failed'    => 'KamVerify — Order Failed',
            default     => null,
        };

        if ($adminSubject) {
            app(AdminMailer::class)->send(
                'order.' . $status . ':' . $order->id,
                $adminSubject,
                'Order ' . $status . ($status === 'completed' ? ' — verification code received' : ''),
                array_filter([
                    'Customer' => $order->user->name . ' <' . $order->user->email . '>',
                    'Order' => $order->order_id,
                    'Service' => $order->service->name ?? null,
                    'Country' => $order->country->name ?? null,
                    'Number' => $order->phone_number,
                    'Price' => xaf($order->selling_price),
                    'Refunded' => $order->refund_amount > 0 ? xaf($order->refund_amount) : null,
                ], fn ($v) => $v !== null)
            );
        }

        Log::info('Order status updated', [
            'order_id' => $order->order_id,
            'status' => $status,
        ]);

        return $order;
    }

    /**
     * Cancel an order. $force bypasses the customer-facing canCancel()
     * rule — used internally when a provider purchase fails mid-flight
     * and the order must be wound back with a refund.
     *
     * $providerRefundStatus records what happened provider-side:
     *   released            — provider accepted our cancellation (cost returned)
     *   provider_resolved   — provider reports the activation already finished/cancelled
     *   provider_unreachable— provider call failed; auto-expiry assumed
     *   consumed            — provider kept the cost (SMS delivered)
     *   none                — no activation was ever purchased
     */
    public function cancelOrder(Order $order, bool $refund = true, bool $force = false,
        ?string $reason = 'customer_cancel', ?string $providerRefundStatus = null): Order
    {
        if (!$force && !$order->canCancel()) {
            throw new \Exception('Order cannot be cancelled');
        }

        return DB::transaction(function () use ($order, $refund, $reason, $providerRefundStatus) {
            $order->cancellation_reason = $reason;
            $order->provider_refund_status = $providerRefundStatus;
            $this->updateStatus($order, 'cancelled');

            if ($refund) {
                $this->refundOrder($order, null, $reason, $providerRefundStatus);
            }

            return $order;
        });
    }

    public function refundOrder(Order $order, float $refundAmount = null,
        ?string $reason = 'order_cancellation', ?string $providerRefundStatus = null): Order
    {
        return DB::transaction(function () use ($order, $refundAmount, $reason, $providerRefundStatus) {
            // Lock the row and refuse to refund twice. refund_amount
            // defaults to 0 (not null) in the schema — a processed refund
            // is signalled by a positive amount, a refunded status, or an
            // existing Refund record.
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status === 'refunded'
                || (float) $order->refund_amount > 0
                || \App\Models\Refund::where('order_id', $order->id)->exists()) {
                return $order;
            }

            $refundAmount = $refundAmount ?? $order->selling_price;

            if ($refundAmount > $order->selling_price) {
                throw new \Exception('Refund amount cannot exceed selling price');
            }

            // Provider outcome decides whether KamVerify lost the
            // activation cost. A completed/delivered order is 'consumed'.
            $providerRefundStatus = $providerRefundStatus ?? $order->provider_refund_status
                ?? (in_array($order->status, ['completed', 'sms_received'])
                    ? 'consumed'
                    : ($order->provider_activation_id ? 'released' : 'none'));

            // Refund to wallet — a REFUND credit, never a deposit.
            $transaction = $this->walletService->deposit($order->user, $refundAmount, 'Order refund', [
                'order_id' => $order->order_id,
                'refund_type' => 'order_cancellation',
                'refund_reason' => $reason,
                'provider_refund_status' => $providerRefundStatus,
            ], 'refund', $order->order_id);

            $costLost = $providerRefundStatus === 'consumed' ? (float) $order->purchase_price : 0.0;

            $order->update([
                'refund_amount' => $refundAmount,
                // Expired orders keep their expired status; the refund is recorded via refund_amount
                'status' => $order->status === 'expired' ? 'expired' : 'refunded',
                'provider_refund_status' => $providerRefundStatus,
                'cancellation_reason' => $order->cancellation_reason ?? $reason,
                // P&L truth: money in (selling) − money returned − provider
                // cost that was never recovered. Full refund + provider
                // released → 0; consumed → −purchase_price (a real loss).
                'profit' => round((float) $order->selling_price - $refundAmount - $costLost, 2),
            ]);

            \App\Models\Refund::create([
                'refund_id' => 'REF-' . strtoupper(uniqid()),
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'wallet_transaction_id' => $transaction->id,
                'amount' => $refundAmount,
                'type' => 'order',
                'status' => 'processed',
                'reason' => $reason,
            ]);

            $order->user->notify(new \App\Notifications\KamVerifyNotification(
                'refund_issued',
                'Refund Issued',
                xaf($refundAmount) . ' has been refunded to your wallet.',
                url('/wallet'),
                'View Wallet',
                'fa-rotate-left'
            ));

            app(AdminMailer::class)->send(
                'order.refunded:' . $order->id,
                'KamVerify — Refund Processed',
                'A customer wallet refund was issued',
                [
                    'Customer' => $order->user->name . ' <' . $order->user->email . '>',
                    'Amount' => xaf($refundAmount),
                    'Order' => $order->order_id,
                    'Reason' => $reason,
                    'Provider cost' => $providerRefundStatus,
                ]
            );

            Log::info('Order refunded successfully', [
                'order_id' => $order->order_id,
                'refund_amount' => $refundAmount,
            ]);

            return $order;
        });
    }

    public function expireOrder(Order $order, ?string $reason = 'expired_no_sms',
        ?string $providerRefundStatus = null): Order
    {
        if (!$order->isExpired()) {
            throw new \Exception('Order is not expired');
        }

        return DB::transaction(function () use ($order, $reason, $providerRefundStatus) {
            $order->cancellation_reason = $reason;
            $order->provider_refund_status = $providerRefundStatus;
            $this->updateStatus($order, 'expired');

            // Auto refund on expiration
            $this->refundOrder($order, null, $reason, $providerRefundStatus);

            return $order;
        });
    }

    public function getUserOrders(User $user, array $filters = [])
    {
        $query = Order::where('user_id', $user->id);

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === 'cancelled') {
                $query->whereIn('status', ['cancelled', 'refunded']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
    }

    public function getActiveOrders(User $user)
    {
        return Order::where('user_id', $user->id)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getOrderStats(User $user): array
    {
        return [
            'total_orders' => Order::where('user_id', $user->id)->count(),
            'active_orders' => Order::where('user_id', $user->id)
                ->active()
                ->count(),
            'completed_orders' => Order::where('user_id', $user->id)
                ->where('status', 'completed')
                ->count(),
            'cancelled_orders' => Order::where('user_id', $user->id)
                ->where('status', 'cancelled')
                ->count(),
            'total_spent' => $this->totalSpent($user),
        ];
    }

    /**
     * Customer "Spent" — money actually spent on COMPLETED orders only.
     * Never derive this from wallet debits: cancelled/refunded/expired
     * purchases return the money, so they are not spending.
     */
    public function totalSpent(User $user): float
    {
        return round((float) Order::where('user_id', $user->id)
            ->where('status', 'completed')
            ->sum('selling_price'), 2);
    }
}
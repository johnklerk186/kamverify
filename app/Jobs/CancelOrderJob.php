<?php

namespace App\Jobs;

use App\Exceptions\HeroSmsException;
use App\Models\Order;
use App\Notifications\KamVerifyNotification;
use App\Services\OrderService;
use App\Services\ProviderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deferred order cancellation.
 *
 * HeroSMS rejects cancellations during the first ~2 minutes of an
 * activation (EARLY_CANCEL_DENIED). When a customer asks to cancel inside
 * that window we set orders.cancel_requested_at and this job retries until
 * the provider accepts — giving the customer an immediate, confirmed
 * cancellation UX without weakening the "no cancel after the code arrives"
 * rule: if an SMS lands first, the provider reports OTP_RECEIVED and the
 * cancellation is aborted.
 */
class CancelOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Give up retrying after this many attempts (~6 min of retries). */
    protected const MAX_ATTEMPTS = 8;
    protected const RETRY_DELAY = 45;

    public function __construct(
        protected int $orderId,
        protected int $attempt = 1,
    ) {
    }

    public function handle(ProviderService $providerService, OrderService $orderService): void
    {
        $order = Order::find($this->orderId);

        if (!$order || !$order->cancel_requested_at) {
            return;
        }

        // Order resolved on its own meanwhile (completed/expired/failed/refunded).
        if (!$order->isActive()) {
            $order->update(['cancel_requested_at' => null]);
            return;
        }

        // An SMS already landed — the number did its job, keep the order.
        if ($order->status === 'sms_received') {
            $order->update(['cancel_requested_at' => null]);
            $this->notify($order, 'cancel_aborted', 'Cancellation Stopped',
                'A code was received on your number, so the order can no longer be cancelled.');
            return;
        }

        $provider = $providerService->getProviderForModel($order->provider);

        try {
            $provider->cancelActivation($order->provider_activation_id);
        } catch (HeroSmsException $e) {
            if (in_array($e->errorCode, ['EARLY_CANCEL_DENIED', 'CANCEL_UNCONFIRMED'], true)
                && $this->attempt < self::MAX_ATTEMPTS) {
                static::dispatch($this->orderId, $this->attempt + 1)
                    ->delay(now()->addSeconds(self::RETRY_DELAY));
                return;
            }

            if (in_array($e->errorCode, ['OTP_RECEIVED', 'NEW_OTP_RECEIVED'], true)) {
                $order->update(['cancel_requested_at' => null]);
                $this->notify($order, 'cancel_aborted', 'Cancellation Stopped',
                    'A code was received on your number, so the order can no longer be cancelled.');
                return;
            }

            if (in_array($e->errorCode, ['FINISHED', 'CANCELED', 'REFUNDED'], true)) {
                // Already resolved at the provider — safe to refund locally.
                $this->finish($order, $orderService, 'provider_resolved');
                return;
            }

            $this->failCancel($order, 'The provider declined cancellation for this number.');
            return;
        } catch (\Throwable $e) {
            Log::warning('CancelOrderJob provider error', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);
            $this->failCancel($order, 'Cancellation could not be completed. Please contact support.');
            return;
        }

        $this->finish($order, $orderService, 'released');
    }

    protected function finish(Order $order, OrderService $orderService, string $providerOutcome = 'released'): void
    {
        try {
            $order->update(['cancel_requested_at' => null]);
            $orderService->cancelOrder($order->fresh(), true, false, 'customer_cancel_deferred', $providerOutcome);

            $this->notify($order, 'order_cancelled', 'Order Cancelled',
                'Your order was cancelled and ' . xaf($order->selling_price) . ' was refunded to your wallet.');
        } catch (\Throwable $e) {
            $this->failCancel($order, 'Cancellation could not be completed. Please contact support.');
        }
    }

    protected function failCancel(Order $order, string $message): void
    {
        $order->update(['cancel_requested_at' => null]);
        $this->notify($order, 'cancel_failed', 'Cancellation Failed', $message);
    }

    protected function notify(Order $order, string $type, string $title, string $message): void
    {
        $order->user->notify(new KamVerifyNotification(
            $type,
            $title,
            $message,
            route('orders.show', $order),
            'View Order',
            'fa-basket-shopping'
        ));
    }
}

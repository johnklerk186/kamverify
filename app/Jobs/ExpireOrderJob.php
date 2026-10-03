<?php

namespace App\Jobs;

use App\Exceptions\HeroSmsException;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\ProviderService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(OrderService $orderService, ProviderService $providerService, SmsService $smsService): void
    {
        try {
            if (!$this->order->isExpired() || !in_array($this->order->status, ['number_assigned', 'waiting_for_sms'])) {
                return;
            }

            // Before refunding locally, release the activation at the
            // provider so we're not charged for a number we just refunded.
            // Edge case: if the provider says an OTP already arrived, the
            // activation succeeded — ingest the SMS and COMPLETE instead.
            $providerOutcome = 'released';
            try {
                $provider = $providerService->getProviderForModel($this->order->provider);
                $provider->cancelActivation($this->order->provider_activation_id);
            } catch (HeroSmsException $e) {
                if (in_array($e->errorCode, ['OTP_RECEIVED', 'NEW_OTP_RECEIVED'])) {
                    $this->ingestLateSms($provider, $smsService);
                    return;
                }
                // FINISHED/CANCELED/NO_ACTIVATION etc. — provider-side
                // already resolved; proceed with local expiry+refund.
                $providerOutcome = 'provider_resolved';
                Log::info('Provider cancel on expiry declined', [
                    'order_id' => $this->order->order_id,
                    'code' => $e->errorCode,
                ]);
            } catch (\Throwable $e) {
                // Provider unreachable — still expire locally; HeroSMS
                // auto-refunds un-coded activations at 20 minutes anyway.
                $providerOutcome = 'provider_unreachable';
                Log::warning('Provider cancel on expiry unreachable', [
                    'order_id' => $this->order->order_id,
                ]);
            }

            $orderService->expireOrder($this->order, 'expired_no_sms', $providerOutcome);

            Log::info('Order expired successfully', [
                'order_id' => $this->order->order_id,
                'user_id' => $this->order->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to expire order', [
                'order_id' => $this->order->order_id,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Provider reported a code already received at expiry — pull the SMS
     * and complete the order rather than refunding a successful purchase.
     */
    protected function ingestLateSms($provider, SmsService $smsService): void
    {
        try {
            foreach ($provider->getSms($this->order->provider_activation_id) as $sms) {
                $smsService->receiveSms(
                    $this->order,
                    $sms['sender'],
                    $sms['message'],
                    $sms['id'] ?? null
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Late SMS ingest failed', ['order_id' => $this->order->order_id]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ExpireOrderJob failed', [
            'order_id' => $this->order->order_id,
            'error' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ProviderService;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(ProviderService $providerService, SmsService $smsService): void
    {
        try {
            if (!in_array($this->order->status, ['number_assigned', 'waiting_for_sms'])) {
                return; // Don't check SMS for orders that aren't waiting
            }

            $provider = $providerService->getProviderForModel($this->order->provider);
            $smsData = $provider->getSms($this->order->provider_activation_id);

            foreach ($smsData as $sms) {
                // Check if we already have this SMS
                $existingSms = $this->order->smsMessages()
                    ->where('provider_message_id', $sms['id'] ?? null)
                    ->where('message', $sms['message'])
                    ->first();

                if (!$existingSms) {
                    $smsService->receiveSms(
                        $this->order,
                        $sms['sender'],
                        $sms['message'],
                        $sms['id'] ?? null
                    );
                }
            }

            Log::info('SMS check completed for order', [
                'order_id' => $this->order->order_id,
                'sms_count' => count($smsData),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check SMS for order', [
                'order_id' => $this->order->order_id,
                'error' => $e->getMessage(),
            ]);
            
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CheckSmsJob failed', [
            'order_id' => $this->order->order_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
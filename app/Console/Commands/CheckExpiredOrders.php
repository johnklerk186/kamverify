<?php

namespace App\Console\Commands;

use App\Jobs\ExpireOrderJob;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckExpiredOrders extends Command
{
    protected $signature = 'orders:check-expired';
    protected $description = 'Check for expired orders and process them';

    public function handle(): int
    {
        $expiredOrders = Order::where('expires_at', '<', now())
            ->whereIn('status', ['number_assigned', 'waiting_for_sms'])
            ->get();

        $count = 0;
        
        foreach ($expiredOrders as $order) {
            try {
                ExpireOrderJob::dispatch($order);
                $count++;
                
                $this->info("Dispatched expiration job for order: {$order->order_id}");
            } catch (\Exception $e) {
                Log::error('Failed to dispatch expiration job', [
                    'order_id' => $order->order_id,
                    'error' => $e->getMessage(),
                ]);
                
                $this->error("Failed to dispatch job for order: {$order->order_id}");
            }
        }

        $this->info("Processed {$count} expired orders.");
        
        return self::SUCCESS;
    }
}
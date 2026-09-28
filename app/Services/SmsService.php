<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SmsMessage;
use App\Notifications\SmsReceived;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Ingest an SMS reported by the provider. Idempotent — provider polls
     * can return the same message repeatedly. On the first message the
     * order auto-completes: SMS_RECEIVED → COMPLETED is a backend event,
     * never a customer action.
     */
    public function receiveSms(Order $order, string $sender, string $message, string $providerMessageId = null): ?SmsMessage
    {
        $alreadyStored = SmsMessage::where('order_id', $order->id)
            ->when(
                $providerMessageId,
                fn ($q) => $q->where('provider_message_id', $providerMessageId),
                fn ($q) => $q->where('sender', $sender)->where('message', $message)
            )
            ->exists();

        if ($alreadyStored) {
            return null;
        }

        $otpCode = $this->extractOtpCode($message);

        $smsMessage = SmsMessage::create([
            'order_id' => $order->id,
            'sender' => $sender,
            'message' => $message,
            'otp_code' => $otpCode,
            'received_at' => now(),
            'provider_message_id' => $providerMessageId,
        ]);

        if (in_array($order->status, ['number_assigned', 'waiting_for_sms'])) {
            $orderService = app(OrderService::class);
            $orderService->updateStatus($order, 'sms_received');
            $orderService->updateStatus($order, 'completed');

            $order->user->notify(new SmsReceived($order, $sender, $otpCode));
        }

        Log::info('SMS received', [
            'order_id' => $order->order_id,
            'sender' => $sender,
            'message' => substr($message, 0, 50) . '...',
        ]);

        return $smsMessage;
    }

    public function getOrderSmsMessages(Order $order)
    {
        return SmsMessage::where('order_id', $order->id)
            ->orderBy('received_at', 'desc')
            ->get();
    }

    public function extractOtpCode(string $message): ?string
    {
        // Common OTP patterns
        $patterns = [
            '/\b\d{4,8}\b/', // 4-8 digit codes
            '/\b[A-Z0-9]{6,10}\b/', // Alphanumeric codes
            '/code\s*[:is]?\s*(\d{4,8})/i', // "code: 123456"
            '/verification\s*code\s*[:is]?\s*(\d{4,8})/i', // "verification code: 123456"
            '/otp\s*[:is]?\s*(\d{4,8})/i', // "otp: 123456"
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1] ?? $matches[0];
            }
        }

        return null;
    }

    public function getRecentSms(Order $order, int $limit = 10)
    {
        return SmsMessage::where('order_id', $order->id)
            ->orderBy('received_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
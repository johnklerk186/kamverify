<?php

namespace App\Notifications\Channels;

use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel delivering via Web Push (VAPID) to all
 * of the user's registered browser subscriptions.
 */
class WebPushChannel
{
    public function __construct(protected PushNotificationService $push)
    {
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toWebPush')) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);

        if (empty($payload)) {
            return;
        }

        try {
            $this->push->sendToUser($notifiable, $payload);
        } catch (\Throwable $e) {
            // Push delivery must never break the primary flow — the
            // database notification is already stored regardless.
            \Illuminate\Support\Facades\Log::warning('Web push send failed', [
                'user_id' => $notifiable->id ?? null,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}

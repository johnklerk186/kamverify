<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Real Web Push delivery (VAPID). Silently no-ops when keys are not
 * configured — the in-app notification centre remains the fallback.
 */
class PushNotificationService
{
    public function enabled(): bool
    {
        return !empty(config('services.webpush.public_key'))
            && !empty(config('services.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('services.webpush.public_key');
    }

    /**
     * Send a push payload to every browser subscription a user has.
     * Expired subscriptions (404/410) are pruned automatically.
     */
    public function sendToUser(User $user, array $payload): int
    {
        if (!$this->enabled()) {
            return 0;
        }

        $subscriptions = $user->pushSubscriptions;
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('services.webpush.subject', config('app.url')),
                'publicKey'  => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);
        $webPush->setReuseVAPIDHeaders(true);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => array_filter([
                        'p256dh' => $sub->public_key,
                        'auth'   => $sub->auth_token,
                    ]),
                ]),
                json_encode($payload)
            );
        }

        $sent = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
                continue;
            }

            $status = $report->getResponse()?->getStatusCode();
            if (in_array($status, [404, 410], true)) {
                $endpoint = (string) $report->getRequest()->getUri();
                $subscriptions->firstWhere('endpoint', $endpoint)?->delete();
            }

            Log::info('Web push delivery failed', [
                'user_id' => $user->id,
                'status'  => $status,
            ]);
        }

        return $sent;
    }
}

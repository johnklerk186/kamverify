<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Generic KamVerify customer notification. Stored in the database
 * notification centre and pushed to subscribed browsers via Web Push.
 *
 * Types: deposit_initiated, deposit_pending, deposit_successful,
 * deposit_failed, deposit_cancelled, deposit_expired, order_purchased,
 * number_assigned, sms_received, order_completed, order_cancelled,
 * refund_issued, order_expired, order_failed, announcement, account.
 */
class KamVerifyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $notificationType,
        public string $title,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $actionText = null,
        public ?string $icon = null,
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'        => $this->notificationType,
            'title'       => $this->title,
            'message'     => $this->message,
            'action_url'  => $this->actionUrl,
            'action_text' => $this->actionText ?? 'View',
            'icon'        => $this->icon ?? $this->defaultIcon(),
        ];
    }

    public function toWebPush($notifiable): array
    {
        return [
            'title' => 'KamVerify — ' . $this->title,
            'body'  => $this->message,
            'url'   => $this->actionUrl ?: url('/notifications'),
            'tag'   => 'kamverify-' . $this->notificationType,
        ];
    }

    protected function defaultIcon(): string
    {
        return match (true) {
            str_starts_with($this->notificationType, 'deposit_') => 'fa-wallet',
            str_starts_with($this->notificationType, 'order_')   => 'fa-mobile-screen',
            $this->notificationType === 'number_assigned'        => 'fa-phone',
            $this->notificationType === 'sms_received'           => 'fa-message',
            $this->notificationType === 'refund_issued'          => 'fa-rotate-left',
            $this->notificationType === 'announcement'           => 'fa-bullhorn',
            default                                              => 'fa-bell',
        };
    }
}

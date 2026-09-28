<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SmsReceived extends Notification implements ShouldQueue
{
    use Queueable;

    protected Order $order;
    protected string $sender;
    protected ?string $otpCode;

    public function __construct(Order $order, string $sender, ?string $otpCode)
    {
        $this->order = $order;
        $this->sender = $sender;
        $this->otpCode = $otpCode;
    }

    public function via($notifiable)
    {
        return ['database', WebPushChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('SMS Received - ' . $this->order->order_id)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('You have received a new SMS message for your ' . $this->order->service->name . ' order.')
            ->line('From: ' . $this->sender)
            ->when($this->otpCode, function ($message) {
                return $message->line('OTP Code: ' . $this->otpCode);
            })
            ->action('View Order', url('/orders/' . $this->order->id));
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'sms_received',
            'title' => 'SMS Received',
            'message' => 'Your ' . $this->order->service->name . ' verification SMS has been received'
                . ($this->otpCode ? ' — code: ' . $this->otpCode : '') . '.',
            'order_id' => $this->order->order_id,
            'sender' => $this->sender,
            'otp_code' => $this->otpCode,
            'action_url' => url('/orders/' . $this->order->id),
            'action_text' => 'View Order',
            'icon' => 'fa-message',
        ];
    }

    public function toWebPush($notifiable)
    {
        return [
            'title' => 'KamVerify — SMS Received',
            'body'  => 'Your ' . $this->order->service->name . ' code'
                . ($this->otpCode ? ': ' . $this->otpCode : ' has been received') . '.',
            'url'   => url('/orders/' . $this->order->id),
            'tag'   => 'kamverify-sms-' . $this->order->id,
        ];
    }
}

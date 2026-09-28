<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Order Created - ' . $this->order->order_id)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your order for ' . $this->order->service->name . ' verification has been created.')
            ->line('Country: ' . $this->order->country->name)
            ->line('Amount: ' . xaf($this->order->selling_price))
            ->action('View Order', url('/orders/' . $this->order->id))
            ->line('Your virtual number will be assigned shortly.');
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'order_purchased',
            'title' => 'Number Purchased',
            'message' => 'Your ' . $this->order->service->name . ' order (' . xaf($this->order->selling_price) . ') was placed successfully.',
            'order_id' => $this->order->order_id,
            'service' => $this->order->service->name,
            'country' => $this->order->country->name,
            'amount' => $this->order->selling_price,
            'action_url' => url('/orders/' . $this->order->id),
            'action_text' => 'View Order',
            'icon' => 'fa-mobile-screen',
        ];
    }

    public function toWebPush($notifiable)
    {
        return [
            'title' => 'KamVerify — Number Purchased',
            'body'  => 'Your ' . $this->order->service->name . ' number order was placed successfully.',
            'url'   => url('/orders/' . $this->order->id),
            'tag'   => 'kamverify-order-' . $this->order->id,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DepositSuccessful extends Notification implements ShouldQueue
{
    use Queueable;

    protected $amount;
    protected $paymentMethod;

    public function __construct(float $amount, string $paymentMethod)
    {
        $this->amount = $amount;
        $this->paymentMethod = $paymentMethod;
    }

    public function via($notifiable)
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Deposit Successful')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your deposit of ' . xaf($this->amount) . ' via ' . $this->paymentMethod . ' was successful.')
            ->line('The funds have been added to your wallet.')
            ->action('View Wallet', url('/wallet'))
            ->line('Thank you for using KamVerify!');
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'deposit_successful',
            'title' => 'Deposit Successful',
            'message' => 'Your wallet has been credited with ' . xaf($this->amount) . '.',
            'amount' => $this->amount,
            'payment_method' => $this->paymentMethod,
            'action_url' => url('/wallet'),
            'action_text' => 'View Wallet',
            'icon' => 'fa-wallet',
        ];
    }

    public function toWebPush($notifiable)
    {
        return [
            'title' => 'KamVerify — Deposit Successful',
            'body'  => 'Your wallet has been credited with ' . xaf($this->amount) . '.',
            'url'   => url('/wallet'),
            'tag'   => 'kamverify-deposit',
        ];
    }
}

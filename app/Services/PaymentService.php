<?php

namespace App\Services;

use App\Services\Payments\PaymentInterface;
use App\Services\Payments\MockPaymentProvider;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\KamVerifyNotification;
use App\Notifications\DepositSuccessful;
use App\Services\AdminMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected array $providers = [];
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
        $this->registerProvider('mock', new MockPaymentProvider());
        $this->registerProvider('fapshi', new \App\Services\Payments\FapshiPaymentProvider());
    }

    public function registerProvider(string $name, PaymentInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    public function getProvider(string $name): ?PaymentInterface
    {
        return $this->providers[$name] ?? null;
    }

    public function getDefaultProvider(): PaymentInterface
    {
        return $this->getProvider('mock') ?? throw new \Exception('No default payment provider configured');
    }

    /**
     * Create a payment with a unique KamVerify reference, then hand it to
     * the provider. The reference is generated BEFORE the provider call so
     * it can be sent as the provider's externalId for reconciliation.
     * All amounts are integer XAF.
     */
    public function createPayment(User $user, float $amount, string $providerName = 'mock', array $metadata = []): Payment
    {
        $reference = 'KV-PAY-' . strtoupper(Str::random(10));
        $metadata['external_id'] = $reference;

        $provider = $this->getProvider($providerName) ?? $this->getDefaultProvider();
        $paymentData = $provider->createPayment($amount, 'XAF', $metadata);

        $payment = DB::transaction(function () use ($user, $amount, $providerName, $metadata, $paymentData, $reference) {
            $payment = Payment::create([
                'payment_id' => $reference,
                'user_id' => $user->id,
                'amount' => $amount,
                'currency' => 'XAF',
                // Always starts pending — processSuccessfulPayment() is the single
                // authority that marks a payment completed and credits the wallet.
                'status' => 'pending',
                'provider' => $providerName,
                'provider_payment_id' => $paymentData['payment_id'] ?? null,
                'provider_response' => array_merge($metadata, $paymentData),
            ]);

            $user->notify(new KamVerifyNotification(
                'deposit_initiated',
                'Deposit Initiated',
                'Your ' . xaf($amount) . ' wallet deposit has been initiated.',
                route('wallet.index'),
                'View Wallet',
                'fa-wallet'
            ));

            return $payment;
        });

        Log::info('Payment created', [
            'payment_id' => $payment->payment_id,
            'user_id' => $user->id,
            'amount' => $amount,
            'provider' => $providerName,
        ]);

        return $payment;
    }

    public function processSuccessfulPayment(Payment $payment): void
    {
        $credited = DB::transaction(function () use ($payment) {
            // Re-fetch under a row lock — concurrent webhooks/callbacks
            // must not both pass the completed check and double-credit.
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'completed') {
                return false; // Already processed (idempotent)
            }

            if (in_array($payment->status, ['failed', 'cancelled', 'expired', 'refunded'], true)) {
                // Terminal states never credit — protects against a late
                // "success" arriving after a definitive failure.
                Log::warning('Blocked success on terminal payment', [
                    'payment_id' => $payment->payment_id,
                    'status' => $payment->status,
                ]);
                return false;
            }

            // Credit wallet
            $transaction = $this->walletService->deposit(
                $payment->user,
                $payment->amount,
                'Wallet deposit — ' . $payment->payment_id,
                [
                    'payment_id' => $payment->payment_id,
                    'provider' => $payment->provider,
                    'payment_method' => $payment->provider_response['medium'] ?? $payment->provider_response['payment_method'] ?? null,
                ]
            );

            $payment->update([
                'status' => 'completed',
                'wallet_transaction_id' => $transaction->id,
                'completed_at' => now(),
            ]);

            $payment->user->notify(new DepositSuccessful(
                (float) $payment->amount,
                $this->paymentMethodLabel($payment)
            ));

            Log::info('Payment processed successfully', [
                'payment_id' => $payment->payment_id,
                'user_id' => $payment->user_id,
                'amount' => $payment->amount,
            ]);

            return true;
        });

        if ($credited) {
            app(AdminMailer::class)->send(
                'deposit.completed:' . $payment->id,
                'KamVerify — New Deposit Received',
                'Deposit confirmed & wallet credited',
                [
                    'Customer' => $payment->user->name . ' <' . $payment->user->email . '>',
                    'Amount' => xaf($payment->amount),
                    'Reference' => $payment->payment_id,
                    'Method' => $this->paymentMethodLabel($payment),
                ]
            );
        }
    }

    public function processFailedPayment(Payment $payment, string $reason = null): void
    {
        $marked = DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'failed') {
                return false; // idempotent
            }
            if ($payment->status === 'completed') {
                return false; // never un-complete a credited payment
            }

            $payment->update([
                'status' => 'failed',
                'provider_response' => array_merge($payment->provider_response ?? [], ['failure_reason' => $reason]),
            ]);

            $payment->user->notify(new KamVerifyNotification(
                'deposit_failed',
                'Deposit Failed',
                'Your ' . $this->paymentMethodLabel($payment) . ' payment of ' . xaf($payment->amount) . ' could not be completed.',
                route('wallet.deposit'),
                'Try Again',
                'fa-wallet'
            ));

            return true;
        });

        if ($marked) {
            app(AdminMailer::class)->send(
                'deposit.failed:' . $payment->id,
                'KamVerify — Deposit Failed',
                'A deposit failed at the payment provider',
                [
                    'Customer' => $payment->user->name . ' <' . $payment->user->email . '>',
                    'Amount' => xaf($payment->amount),
                    'Reference' => $payment->payment_id,
                    'Method' => $this->paymentMethodLabel($payment),
                    'Reason' => $reason ?? 'Unknown',
                ]
            );
        }

        Log::info('Payment failed', [
            'payment_id' => $payment->payment_id,
            'user_id' => $payment->user_id,
            'reason' => $reason,
        ]);
    }

    /**
     * Customer abandoned / provider reported cancellation.
     */
    public function processCancelledPayment(Payment $payment): void
    {
        $this->terminalTransition($payment, 'cancelled', 'Deposit Cancelled',
            'Your ' . xaf($payment->amount) . ' deposit was cancelled.');
    }

    /**
     * Provider payment window expired (e.g. initiate-pay link timeout).
     */
    public function processExpiredPayment(Payment $payment): void
    {
        $this->terminalTransition($payment, 'expired', 'Deposit Expired',
            'Your ' . xaf($payment->amount) . ' deposit request expired before payment was confirmed.');
    }

    protected function terminalTransition(Payment $payment, string $status, string $title, string $message): void
    {
        $transitioned = DB::transaction(function () use ($payment, $status, $title, $message) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if (in_array($payment->status, ['completed', $status], true)) {
                return false; // credited payments never downgrade; idempotent
            }

            $payment->update(['status' => $status]);

            $payment->user->notify(new KamVerifyNotification(
                'deposit_' . $status,
                $title,
                $message,
                route('wallet.deposit'),
                'Deposit Again',
                'fa-wallet'
            ));

            return true;
        });

        if ($transitioned) {
            app(AdminMailer::class)->send(
                'deposit.' . $status . ':' . $payment->id,
                'KamVerify — Deposit ' . ucfirst($status),
                'A deposit was ' . $status,
                [
                    'Customer' => $payment->user->name . ' <' . $payment->user->email . '>',
                    'Amount' => xaf($payment->amount),
                    'Reference' => $payment->payment_id,
                    'Method' => $this->paymentMethodLabel($payment),
                ]
            );
        }
    }

    /**
     * Server-to-server webhook entry point. The payload itself is NEVER
     * trusted — we re-verify the transaction status with the provider
     * before changing any state.
     */
    public function handleWebhook(string $providerName, array $payload): void
    {
        $provider = $this->getProvider($providerName);

        if (!$provider) {
            Log::error('Unknown payment provider for webhook', ['provider' => $providerName]);
            return;
        }

        $result = $provider->processWebhook($payload);

        $payment = Payment::where('provider_payment_id', $result['payment_id'] ?? null)
            ->orWhere('payment_id', $result['external_id'] ?? null)
            ->orWhere('payment_id', $result['payment_id'] ?? null)
            ->first();

        if (!$payment) {
            Log::warning('Webhook for unknown payment', ['provider' => $providerName, 'payload' => $result]);
            return;
        }

        $payment->update(['webhook_data' => $payload]);

        // Backend verification is the source of truth.
        $this->verifyAndApplyStatus($payment);
    }

    /**
     * Pull the real status from the provider and apply the transition.
     * Used by the webhook handler and the customer-facing status check.
     */
    public function verifyAndApplyStatus(Payment $payment): string
    {
        $provider = $this->getProvider($payment->provider);
        if (!$provider) {
            return $payment->status;
        }

        $status = $provider->getPaymentStatus($payment->provider_payment_id ?? $payment->payment_id);
        $normalized = $status['status'] ?? null;

        $payment->update([
            'provider_response' => array_merge($payment->provider_response ?? [], ['last_status_check' => $status]),
        ]);

        match ($normalized) {
            'completed', 'success', 'successful' => $this->processSuccessfulPayment($payment),
            'failed'    => $this->processFailedPayment($payment, $status['reason'] ?? 'Provider reported failure'),
            'cancelled' => $this->processCancelledPayment($payment),
            'expired'   => $this->processExpiredPayment($payment),
            default     => null, // pending/created — keep waiting
        };

        return $payment->refresh()->status;
    }

    public function refundPayment(Payment $payment, float $amount = null): void
    {
        $provider = $this->getProvider($payment->provider);

        if (!$provider) {
            throw new \Exception('Payment provider not found');
        }

        $result = $provider->refundPayment($payment->provider_payment_id, $amount);

        $wasCompleted = $payment->status === 'completed';

        $payment->update([
            'status' => 'refunded',
            'provider_response' => array_merge($payment->provider_response ?? [], ['refund_data' => $result]),
        ]);

        // Deduct from wallet only if the deposit was already credited
        if ($wasCompleted) {
            $this->walletService->withdraw(
                $payment->user,
                $amount ?? $payment->amount,
                'Payment refund — ' . $payment->payment_id,
                ['payment_id' => $payment->payment_id]
            );
        }

        app(AdminMailer::class)->send(
            'payment.refunded:' . $payment->id,
            'KamVerify — Refund Processed',
            'A payment refund was processed',
            [
                'Customer' => $payment->user->name . ' <' . $payment->user->email . '>',
                'Amount' => xaf($amount ?? $payment->amount),
                'Reference' => $payment->payment_id,
                'Method' => $this->paymentMethodLabel($payment),
                'Wallet debited' => $wasCompleted ? 'Yes' : 'No (deposit never credited)',
            ]
        );

        Log::info('Payment refunded', [
            'payment_id' => $payment->payment_id,
            'amount' => $amount ?? $payment->amount,
        ]);
    }

    protected function paymentMethodLabel(Payment $payment): string
    {
        $method = $payment->provider_response['payment_method'] ?? $payment->provider ?? 'payment';

        return match ($method) {
            'mtn_momo', 'mobile money', 'fapshi' => 'MTN Mobile Money',
            'orange money' => 'Orange Money',
            'mock' => 'Sandbox',
            default => ucfirst((string) $method),
        };
    }
}
